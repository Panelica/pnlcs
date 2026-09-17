<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewayCustomer;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Iyzico\IyzicoModule;

/*
 * CHARGING A CARD IYZICO IS HOLDING, WITH NOBODY WATCHING.
 *
 * The whole file turns on one sentence of iyzico's own documentation:
 * "Majority of iyzico services have designed non-idempotent to ensure
 * predictable and consistent behavior when making repeated requests"
 * (https://docs.iyzico.com/en/getting-started/preliminaries/idempotency,
 * fetched 2026-09-17). There is no Idempotency-Key header anywhere in the API,
 * so the protection StripeModule buys from Stripe has to be built here, and it
 * is built out of the one thing iyzico does offer: POST /payment/detail can be
 * asked by paymentConversationId, the reference we chose ourselves
 * (https://docs.iyzico.com/ek-servisler/odeme-sorgulama).
 *
 * Hence the rule every test below is really about: A REPLAY MUST NEVER REACH
 * /payment/auth. If one ever does, a customer is debited twice.
 */

/** iyzico switched on with keys it can sign with. */
function storedCardShop(string $shopCurrency = 'TRY', ?float $tryRate = null): void
{
    foreach ([
        'active' => '1',
        'api_key' => 'test-api-key',
        'secret_key' => 'test-secret-key',
        'sandbox' => '1',
        'installments' => '1,3,6',
        'save_cards' => '1',
    ] as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'iyzico', 'setting' => $setting], ['value' => $value]);
    }

    Currency::updateOrCreate(
        ['code' => $shopCurrency],
        ['prefix' => $shopCurrency === 'TRY' ? '₺' : '$', 'rate' => 1, 'name' => $shopCurrency, 'default' => 1]
    );

    app()->instance('pnlcs.currency', ['currency' => Currency::where('code', $shopCurrency)->first()]);

    if ($tryRate !== null) {
        Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '₺', 'rate' => $tryRate, 'name' => 'Turkish Lira']);
    }
}

/** An unpaid invoice and one iyzico card stored against its client. */
function storedCardPair(float $total = 100): array
{
    $client = Client::factory()->create(['country' => 'TR', 'city' => 'Istanbul', 'address1' => '1 Test', 'tax_id' => '11111111111']);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => $total,
    ]);

    $card = PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'iyzico',
        'payment_type' => 'card',
        'description' => 'iyzico VISA',
        'remote_token' => json_encode(['cardUserKey' => 'cuk_1', 'cardToken' => 'ctk_1']),
        'last_four' => '4242',
        'status' => PaymentMethod::STATUS_ACTIVE,
    ]);

    return [$invoice, $card];
}

function iyzicoStored(): IyzicoModule
{
    return new IyzicoModule;
}

/** How many times a given iyzico path was posted to. */
function timesCalled(string $path): int
{
    $n = 0;
    Http::recorded(function ($request) use ($path, &$n) {
        if (str_contains($request->url(), $path)) {
            $n++;
        }

        return false;
    });

    return $n;
}

/** iyzico's shape for "the payment you are asking about went through". */
function detailSuccess(float $paidTry = 100): array
{
    return [
        'status' => 'success',
        'paymentStatus' => 'SUCCESS',
        'paymentId' => 'pay_traced_1',
        'paidPrice' => $paidTry,
        'currency' => 'TRY',
    ];
}

// =============================================================================
// THE CENTRAL RULE: A REPLAY ASKS, IT DOES NOT CHARGE.
// =============================================================================

test('a replay never presents the card again — it asks iyzico what became of the charge already sent', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/detail' => Http::response(detailSuccess(), 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => true,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    // THE ASSERTION THIS WHOLE INTEGRATION EXISTS FOR. iyzico has no
    // idempotency layer, so a second /payment/auth is a second real debit.
    expect(timesCalled('/payment/auth'))->toBe(0)
        ->and(timesCalled('/payment/detail'))->toBe(1);

    // And the unknown outcome is RESOLVED rather than parked: this is what the
    // inquiry endpoint buys that Stripe's replay cannot.
    expect($result['status'])->toBe('succeeded')
        ->and($result['success'])->toBeTrue()
        ->and($result['transaction_id'])->toBe('pay_traced_1');
});

test('the inquiry goes out under the reference the caller minted, not one the module invented', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/detail' => Http::response(detailSuccess(), 200)]);

    iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => true,
        'idempotency_key' => 'pnlcs-offsession-theonlyhandle',
    ]);

    Http::assertSent(fn ($request) => $request['paymentConversationId'] === 'pnlcs-offsession-theonlyhandle');
});

test('a traced charge that the issuer refused is reported as a refusal, so crashed declines keep being collected', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/detail' => Http::response([
        'status' => 'success',
        'paymentStatus' => 'FAILURE',
        'paymentId' => 'pay_x',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => true,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['status'])->toBe('failed')
        ->and($result['outcome_unknown'] ?? false)->toBeFalse()
        // Known to have taken nothing, so a fresh attempt later is a first
        // charge rather than a second debit.
        ->and($result['retryable'])->toBeTrue();
});

test('iyzico having no record of the charge yet is NOT read as proof it never happened', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    // 5087, "Üye İşyerine Ait Ödeme Kaydı Bulunamadı"
    // (https://docs.iyzico.com/ek-bilgiler/hata-kodlari, fetched 2026-09-17).
    Http::fake(['*/payment/detail' => Http::response([
        'status' => 'failure',
        'errorCode' => '5087',
        'errorMessage' => 'Üye İşyerine Ait Ödeme Kaydı Bulunamadı',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => true,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    // "Not found" has two meanings this end cannot tell apart: it never
    // arrived, or it arrived and has not been written yet. Treating it as the
    // first is what charges a customer twice.
    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($result['success'])->toBeFalse()
        ->and($result['retryable'])->toBeFalse()
        ->and($result['transaction_id'] ?? null)->toBeNull()
        ->and(timesCalled('/payment/auth'))->toBe(0);
});

test('a payment that was merely late is found on a later sweep, with no card presented in between', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    // THE RACE, PLAYED OUT. First inquiry: not on iyzico's books. Second: it is
    // — because it was being written the whole time.
    Http::fakeSequence()
        ->push(['status' => 'failure', 'errorCode' => '5087'], 200)
        ->push(detailSuccess(), 200);

    $module = iyzicoStored();
    $params = ['replay' => true, 'idempotency_key' => 'pnlcs-offsession-abc'];

    $first = $module->chargeStoredMethod($invoice, $card, 100, $params);
    $second = $module->chargeStoredMethod($invoice, $card, 100, $params);

    expect($first['outcome_unknown'] ?? false)->toBeTrue()
        ->and($second['status'])->toBe('succeeded')
        ->and($second['transaction_id'])->toBe('pay_traced_1')
        // Two sweeps, two reads, and the card was never asked for money again.
        ->and(timesCalled('/payment/auth'))->toBe(0);
});

test('a payment iyzico has but has not finished is never credited on a maybe', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/detail' => Http::response([
        'status' => 'success',
        'paymentStatus' => 'INIT_THREEDS',
        'paymentId' => 'pay_unfinished',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => true,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        // The id is deliberately withheld: on an in-flight row a transaction id
        // tells the caller's rescue to credit the invoice.
        ->and($result['transaction_id'] ?? null)->toBeNull();
});

test('a replay whose reference this end cannot name sends nothing at all', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*' => Http::response(detailSuccess(), 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, ['replay' => true]);

    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and(timesCalled('/payment/auth'))->toBe(0)
        ->and(timesCalled('/payment/detail'))->toBe(0);
});

// =============================================================================
// THE FIRST SEND, AND WHAT IS AND IS NOT AN ANSWER.
// =============================================================================

test('a first send charges the stored card with the two keys and no card number', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/auth' => Http::response([
        'status' => 'success',
        'paymentId' => 'pay_1',
        'paidPrice' => 100.0,
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['status'])->toBe('succeeded')
        ->and($result['transaction_id'])->toBe('pay_1');

    Http::assertSent(function ($request) {
        return $request['paymentCard']['cardUserKey'] === 'cuk_1'
            && $request['paymentCard']['cardToken'] === 'ctk_1'
            && ! isset($request['paymentCard']['cardNumber'])
            && $request['conversationId'] === 'pnlcs-offsession-abc'
            // iyzico takes decimal strings, not minor units.
            && $request['price'] === '100.00'
            && $request['currency'] === 'TRY'
            // Nobody is here to choose instalments.
            && $request['installment'] === 1;
    });
});

test('a dropped connection is never reported as a decline', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(fn () => throw new ConnectionException('timed out'));

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($result['retryable'])->toBeFalse();
});

test('a 500 is indeterminate, because nothing says the charge did not execute', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/auth' => Http::response('gateway fell over', 500)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['outcome_unknown'] ?? false)->toBeTrue();
});

test('an answer that is not iyzico speaking its own protocol decides nothing', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    // A 200 from something in front of the API. No status, no errorCode.
    Http::fake(['*/payment/auth' => Http::response(['message' => 'ok'], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['outcome_unknown'] ?? false)->toBeTrue();
});

test('a success iyzico did not name is not credited', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/auth' => Http::response(['status' => 'success', 'paidPrice' => 100.0], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['outcome_unknown'] ?? false)->toBeTrue();
});

test('a charge that comes back asking for the cardholder is neither money nor a decline', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    // "threeDSHtmlContent ... Base64-encoded HTML content of the 3DS
    // verification screen" (https://docs.iyzico.com/en/payment-methods/api/3ds/
    // 3ds-implementation/init-3ds). It is NOT in the documented /payment/auth
    // schema; it is handled because a redirect arriving here would otherwise be
    // read as one of the two things it certainly is not.
    Http::fake(['*/payment/auth' => Http::response([
        'status' => 'success',
        'paymentId' => 'pay_3ds',
        'threeDSHtmlContent' => 'PGh0bWw+',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['status'])->toBe('requires_action')
        ->and($result['success'])->toBeFalse()
        ->and($result['retryable'])->toBeFalse()
        ->and($result['decline_code'])->toBe('authentication_required');

    // The card is fine; its owner was simply not there.
    expect($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

// =============================================================================
// NON-3DS IS NOT ON BY DEFAULT, AND THAT MUST NOT LOOK LIKE A CARD DECLINE.
// =============================================================================

test('an account that is not cleared for this transaction is an operator problem, not a card problem', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    // errorGroup NOT_PERMITTED_TO_TERMINAL, code 10058 "Terminalin bu işlemi
    // yapmaya yetkisi yok". NON-3DS is off until iyzico switch it on:
    // "NON-3DS kullanımı için iyzico hesabınızda bu özelliğin olması
    // gerekmektedir" (https://docs.iyzico.com/odeme-metotlari/api/non-3ds).
    Http::fake(['*/payment/auth' => Http::response([
        'status' => 'failure',
        'errorCode' => '10058',
        'errorMessage' => 'Terminalin bu işlemi yapmaya yetkisi yok',
        'errorGroup' => 'NOT_PERMITTED_TO_TERMINAL',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['status'])->toBe('failed')
        // No number of tomorrows changes an account setting.
        ->and($result['retryable'])->toBeFalse()
        // And the operator is told where to write.
        ->and($result['message'])->toContain('entegrasyon@iyzico.com')
        ->and($result['message'])->toContain('NON-3DS');

    // THE CARD WAS NEVER THE PROBLEM, so it is not written off and the customer
    // is not sent to replace a card that works perfectly well.
    expect($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('a bank exchange that did not complete is not a decline either', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/auth' => Http::response([
        'status' => 'failure',
        'errorCode' => '10220',
        'errorMessage' => 'Ödeme alınamadı',
        'errorGroup' => 'COMMUNICATION_OR_SYSTEM_ERROR',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    // A timeout between acquirer and issuer is the classic way a card is
    // debited by a transaction the merchant is told failed. The next sweep asks
    // rather than guessing.
    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('an issuer refusal that will not change its mind is written on the card', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/auth' => Http::response([
        'status' => 'failure',
        'errorCode' => '10054',
        'errorMessage' => 'Vadesi dolmuş kart',
        'errorGroup' => 'EXPIRED_CARD',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeFalse()
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_REQUIRES_UPDATE);
});

test('an ordinary decline is worth another morning and leaves the card alone', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/auth' => Http::response([
        'status' => 'failure',
        'errorCode' => '10051',
        'errorMessage' => 'Kart limiti yetersiz, yetersiz bakiye',
        'errorGroup' => 'NOT_SUFFICIENT_FUNDS',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['retryable'])->toBeTrue()
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('a refusal carrying no group at all is not sent again three days later', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    // A validation refusal: the request was wrong, not the card.
    Http::fake(['*/payment/auth' => Http::response([
        'status' => 'failure',
        'errorCode' => '5008',
        'errorMessage' => 'İşlem geçersiz',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeFalse();
});

test('a stored card iyzico no longer holds is marked for the customer rather than retried', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/auth' => Http::response([
        'status' => 'failure',
        'errorCode' => '3006',
        'errorMessage' => 'cardToken bulunamadı',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false,
        'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['retryable'])->toBeFalse()
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_REQUIRES_UPDATE);
});

// =============================================================================
// REFUSED BEFORE ANYTHING GOES OUT.
// =============================================================================

test('a charge with no reference to send it under is never fired', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    Http::fake(['*' => Http::response(['status' => 'success', 'paymentId' => 'p'], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, ['replay' => false]);

    // Without a reference there is no way ever to ask iyzico what became of it,
    // so it is not sent at all.
    expect($result['status'])->toBe('failed')
        ->and(timesCalled('/payment/auth'))->toBe(0);
});

test('a card belonging to somebody else is never charged', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();
    $card->update(['client_id' => Client::factory()->create()->id]);

    Http::fake(['*' => Http::response(['status' => 'success', 'paymentId' => 'p'], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card->fresh(), 100, [
        'replay' => false, 'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['status'])->toBe('failed')
        ->and(timesCalled('/payment/auth'))->toBe(0);
});

test('a card the customer removed is never charged', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();
    $card->delete();

    Http::fake(['*' => Http::response(['status' => 'success', 'paymentId' => 'p'], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, PaymentMethod::withTrashed()->find($card->id), 100, [
        'replay' => false, 'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['status'])->toBe('failed')
        ->and(timesCalled('/payment/auth'))->toBe(0);
});

test('every refusal made before the POST becomes an unknown outcome on a replay', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();
    $card->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);

    Http::fake(['*' => Http::response(detailSuccess(), 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card->fresh(), 100, [
        'replay' => true, 'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    // "We declined to send anything this time" says nothing about the charge
    // that DID go out, and reported as a failure it would either schedule a
    // second real debit or write the row off unread.
    expect($result['outcome_unknown'] ?? false)->toBeTrue();
});

// =============================================================================
// CURRENCY. IYZICO TAKES DECIMAL STRINGS AND SETTLES IN LIRA.
// =============================================================================

test('a shop that does not price in lira is not charged its own numbers labelled TRY', function () {
    // No TRY rate stored at all.
    storedCardShop('USD');
    [$invoice, $card] = storedCardPair();

    Http::fake(['*' => Http::response(['status' => 'success', 'paymentId' => 'p'], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false, 'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    // Unattended, nobody sees "₺100" where "₺4,000" belongs. So it refuses.
    expect($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeTrue()
        ->and(timesCalled('/payment/auth'))->toBe(0);
});

test('an invoice priced in another currency is sent in lira and credited back in its own', function () {
    storedCardShop('USD', 40.0);
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/auth' => Http::response([
        'status' => 'success',
        'paymentId' => 'pay_1',
        'paidPrice' => 4000.0,
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false, 'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    Http::assertSent(fn ($request) => $request['price'] === '4000.00' && $request['currency'] === 'TRY');

    // Credited in the currency the books are kept in, through the same rate it
    // was sent under.
    expect($result['amount'])->toBe(100.0);
});

// =============================================================================
// DETACHING, AND THE OTHER HALF OF THE CONTRACT.
// =============================================================================

test('detaching a card iyzico has already forgotten is a success, not a failure retried for ever', function () {
    storedCardShop();
    [, $card] = storedCardPair();

    Http::fake(['*/cardstorage/card' => Http::response([
        'status' => 'failure',
        'errorCode' => '3006',
        'errorMessage' => 'cardToken bulunamadı',
    ], 200)]);

    $result = iyzicoStored()->detachStoredMethod($card);

    expect($result['success'])->toBeTrue();
});

test('detaching sends both keys iyzico requires to delete a stored card', function () {
    storedCardShop();
    [, $card] = storedCardPair();

    Http::fake(['*/cardstorage/card' => Http::response(['status' => 'success'], 200)]);

    expect(iyzicoStored()->detachStoredMethod($card)['success'])->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request['cardUserKey'] === 'cuk_1'
        && $request['cardToken'] === 'ctk_1');
});

test('a gateway that cannot be reached leaves the detach outstanding rather than lying about it', function () {
    storedCardShop();
    [, $card] = storedCardPair();

    Http::fake(fn () => throw new ConnectionException('down'));

    $result = iyzicoStored()->detachStoredMethod($card);

    expect($result['success'])->toBeFalse()
        ->and($result['retryable'])->toBeTrue();
});

test('a card stored with another gateway is not handed to iyzico', function () {
    storedCardShop();
    [, $card] = storedCardPair();
    $card->update(['gateway_name' => 'stripe']);

    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

    expect(iyzicoStored()->detachStoredMethod($card->fresh())['success'])->toBeFalse()
        ->and(timesCalled('/cardstorage/card'))->toBe(0);
});

// =============================================================================
// VAULTING.
// =============================================================================

test('iyzico says plainly that it cannot open a card form, and makes no request doing so', function () {
    storedCardShop();
    $client = Client::factory()->create();

    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

    $result = iyzicoStored()->beginVaulting($client);

    // Declining has to be free: PaymentMethodController walks the tokenising
    // gateways and a shop running iyzico beside Stripe must fall past this one
    // inside a customer's own click.
    expect($result['success'])->toBeFalse();
    Http::assertNothingSent();
});

test('confirming a card from a payment that belongs to another client stores nothing', function () {
    storedCardShop();
    [$invoice] = storedCardPair();
    $stranger = Client::factory()->create();

    Http::fake(['*/checkoutform/auth/ecom/detail' => Http::response([
        'status' => 'success',
        'paymentStatus' => 'SUCCESS',
        'paymentId' => 'p1',
        'conversationId' => (string) $invoice->id,
        'cardUserKey' => 'cuk_new',
        'cardToken' => 'ctk_new',
        'lastFourDigits' => '1111',
    ], 200)]);

    $result = iyzicoStored()->confirmVaulting($stranger, 'token-from-browser');

    expect($result['success'])->toBeFalse()
        ->and(PaymentMethod::where('client_id', $stranger->id)->count())->toBe(0);
});

test('confirming a card the customer really paid with stores it and remembers their iyzico user key', function () {
    storedCardShop();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 50]);

    Http::fake(['*/checkoutform/auth/ecom/detail' => Http::response([
        'status' => 'success',
        'paymentStatus' => 'SUCCESS',
        'paymentId' => 'p1',
        'conversationId' => (string) $invoice->id,
        'cardUserKey' => 'cuk_new',
        'cardToken' => 'ctk_new',
        'lastFourDigits' => '1111',
        'cardAssociation' => 'MASTER_CARD',
    ], 200)]);

    $result = iyzicoStored()->confirmVaulting($client, 'token-from-browser');

    $card = PaymentMethod::where('client_id', $client->id)->first();

    expect($result['success'])->toBeTrue()
        ->and($card)->not->toBeNull()
        ->and($card->gateway_customer_id)->toBe('cuk_new')
        ->and($card->status)->toBe(PaymentMethod::STATUS_ACTIVE)
        // One client, one cardUserKey — so their saved cards do not scatter.
        ->and(GatewayCustomer::idFor('iyzico', (int) $client->id))->toBe('cuk_new');

    // And the card number is nowhere in what we kept.
    expect($card->remote_token)->not->toContain('cardNumber');
});

test('confirming a payment where the customer did not tick "save my card" stores nothing', function () {
    storedCardShop();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 50]);

    Http::fake(['*/checkoutform/auth/ecom/detail' => Http::response([
        'status' => 'success',
        'paymentStatus' => 'SUCCESS',
        'paymentId' => 'p1',
        'conversationId' => (string) $invoice->id,
    ], 200)]);

    expect(iyzicoStored()->confirmVaulting($client, 'tok')['success'])->toBeFalse()
        ->and(PaymentMethod::where('client_id', $client->id)->count())->toBe(0);
});

// =============================================================================
// INVARIANT ZERO.
// =============================================================================

test('every gateway module still satisfies the contract it always did', function () {
    // THIS ONCE LISTED THE MODULES THAT MUST NOT VAULT, AND NAMED PAYPAL AMONG
    // THEM. PayPal then gained the capability on purpose and the test failed for
    // a reason that had nothing to do with what it is for. Capability is meant
    // to spread; the invariant is that gaining it never costs a module the
    // contract every gateway has always had to satisfy.
    $registry = app(ModuleRegistry::class);

    foreach ($registry->getGatewayModules() as $name) {
        $module = $registry->getGatewayModule($name);

        expect($module)->toBeInstanceOf(App\Contracts\GatewayModuleInterface::class, "{$name} no longer satisfies GatewayModuleInterface");

        // A module that claims it can keep a card has to be able to answer all
        // four questions, not merely carry the marker.
        if ($module instanceof App\Contracts\TokenizableGatewayInterface) {
            foreach (['beginVaulting', 'confirmVaulting', 'detachStoredMethod', 'chargeStoredMethod'] as $method) {
                expect(method_exists($module, $method))->toBeTrue("{$name} claims the vaulting capability without {$method}()");
            }
        }
    }
});

test('iyzico refunds still work exactly as they did', function () {
    storedCardShop();

    Http::fakeSequence()
        ->push(['status' => 'success', 'paidPrice' => 100.0, 'currency' => 'TRY',
            'itemTransactions' => [['paymentTransactionId' => 'ptx_1']]], 200)
        ->push(['status' => 'success', 'paymentId' => 'refund_1'], 200);

    $result = iyzicoStored()->refund('pay_1', 100);

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('succeeded');
});

test('an unattended charge is never split into instalments, whatever the operator offers a live customer', function () {
    storedCardShop();
    // The operator offers 3, 6 and 9 on the payment page. None of those is a
    // thing an absent cardholder agreed to.
    GatewaySettings::updateOrCreate(['gateway' => 'iyzico', 'setting' => 'installments'], ['value' => '3,6,9']);
    [$invoice, $card] = storedCardPair();

    Http::fake(['*/payment/auth' => Http::response(['status' => 'success', 'paymentId' => 'p', 'paidPrice' => 100.0], 200)]);

    iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => false, 'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    Http::assertSent(fn ($request) => $request['installment'] === 1);
});

// =============================================================================
// THE CARD FORM, WHICH IYZICO MUST NOT TAKE OVER.
//
// Before this feature exactly one module implemented the tokenising interface,
// so "the first tokenising gateway" was always Stripe. It is not any more, and
// PaymentMethodController picked its gateway out of GatewaySettings in
// insertion order — so a shop that saved iyzico's keys before Stripe's would
// have handed the card form to a gateway that cannot draw one.
// =============================================================================

test('a shop running iyzico beside Stripe can still add a Stripe card', function () {
    Setting::set('AutoChargeEnabled', '1');

    // iyzico saved FIRST, which is what used to decide it.
    storedCardShop();

    foreach (['active' => '1', 'publishable_key' => 'pk_test_visible', 'secret_key' => 'sk_test_secret'] as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $k], ['value' => $v]);
    }

    Http::fake([
        '*/v1/setup_intents' => Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200),
        '*/v1/customers*' => Http::response(['id' => 'cus_1'], 200),
    ]);

    $user = App\Models\User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $this->actingAs($user)->get(route('client.payment-methods.add-card'))
        ->assertOk()
        ->assertSee('seti_1_secret', false)
        // And the form says which gateway it belongs to, so the confirmation
        // cannot be sent to the other one.
        ->assertSee('name="gateway" value="stripe"', false);
});

test('a card confirmation is never sent to a gateway the page was not drawn with', function () {
    Setting::set('AutoChargeEnabled', '1');
    storedCardShop();

    $user = App\Models\User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

    // 'stripe' is not a tokenising gateway on this shop at all.
    $this->actingAs($user)->post(route('client.payment-methods.store-card'), [
        'session_id' => 'seti_1',
        'gateway' => 'stripe',
        'consent' => '1',
    ])->assertNotFound();
});

test('a traced charge iyzico says FAILED is not re-opened as unknown because the bank link timed out', function () {
    storedCardShop();
    [$invoice, $card] = storedCardPair();

    // paymentStatus FAILURE is iyzico's own verdict on the charge. The reason
    // being a comms error explains WHY it failed; it does not unsay THAT it
    // failed, and reading it as "we do not know" would ask four more times and
    // then fetch a person over a settled matter.
    Http::fake(['*/payment/detail' => Http::response([
        'status' => 'success',
        'paymentStatus' => 'FAILURE',
        'errorCode' => '10220',
        'errorGroup' => 'COMMUNICATION_OR_SYSTEM_ERROR',
    ], 200)]);

    $result = iyzicoStored()->chargeStoredMethod($invoice, $card, 100, [
        'replay' => true, 'idempotency_key' => 'pnlcs-offsession-abc',
    ]);

    expect($result['outcome_unknown'] ?? false)->toBeFalse()
        ->and($result['status'])->toBe('failed');
});

test('removing an iyzico card now asks iyzico to let it go, which nothing could do before', function () {
    // WHAT CHANGED, AND IT IS A BEHAVIOUR CHANGE RATHER THAN A SIDE EFFECT.
    // PaymentMethod::requestGatewayDetach() refuses to record a request no
    // module could satisfy, and until iyzico implemented the tokenising
    // interface it was one of those: a customer who removed their card had it
    // dropped at this end while iyzico went on holding it for ever. Two tests
    // in CardVaultingTest used iyzico as their example of exactly that, and
    // they now use PayPal, which really cannot.
    //
    // Note what is NOT consulted: AutoChargeEnabled. ModuleRegistry's
    // canDetachStoredMethods() is a capability question by design, so a shop
    // that never turned automatic charging on still gets its customers' cards
    // deleted at iyzico — which is what the customer asked for either way.
    storedCardShop();
    [, $card] = storedCardPair();

    $card->requestGatewayDetach();
    $card->delete();

    expect(PaymentMethod::withTrashed()->find($card->id)->detach_requested_at)->not->toBeNull();

    Http::fake(['*/cardstorage/card' => Http::response(['status' => 'success'], 200)]);

    expect(Illuminate\Support\Facades\Artisan::call('pnlcs:detach-payment-methods'))->toBe(0);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/cardstorage/card'));

    expect(PaymentMethod::withTrashed()->find($card->id)->detached_at)->not->toBeNull();
});

test('a stored card that cannot be deleted is never reported as deleted', function () {
    storedCardShop();
    [, $card] = storedCardPair();

    // A legacy row: the token is there, the cardUserKey is not. iyzico needs
    // both to delete a card (error 5111, "cardUserKey bilgisi cardToken ile
    // birlikte gönderilmelidir"), so this cannot be asked for at all.
    $card->update(['remote_token' => 'ctk_only', 'gateway_customer_id' => null]);

    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

    $result = iyzicoStored()->detachStoredMethod($card->fresh());

    expect($result['success'])->toBeFalse()
        // Said once and then dropped, rather than retried every five minutes
        // for the life of the installation.
        ->and($result['retryable'])->toBeFalse()
        ->and(timesCalled('/cardstorage/card'))->toBe(0);
});

test('a row that never stored anything at iyzico is finished, not left waiting', function () {
    storedCardShop();
    [, $card] = storedCardPair();
    $card->update(['remote_token' => null, 'gateway_customer_id' => null]);

    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

    expect(iyzicoStored()->detachStoredMethod($card->fresh())['success'])->toBeTrue()
        ->and(timesCalled('/cardstorage/card'))->toBe(0);
});
