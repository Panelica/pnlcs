<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewayCustomer;
use App\Models\GatewaySettings;
use App\Models\GatewayVaultSession;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AutoChargeService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Modules\Gateways\PayPal\PayPalModule;

/*
 * PayPal: the idempotency header it was never sent, and the vault it never had.
 *
 * Two things are under test here and they are not the same thing. The first is
 * what name each PayPal call goes out under — PayPal duplicates a POST that
 * carries no PayPal-Request-Id, and answers one that carries a familiar one
 * with the previous request's result, so the wrong key is as dangerous as no
 * key on the calls that move money. The second is the new capability: storing a
 * payer's PayPal account and charging it with nobody watching, which has to
 * obey TokenizableGatewayInterface exactly or the charger will take money it
 * cannot account for.
 */

function paypalConfigured(): void
{
    foreach (['client_id' => 'cid', 'client_secret' => 'csec', 'sandbox' => '1'] as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'paypal', 'setting' => $k], ['value' => $v]);
    }
}

function paypalShopCurrency(string $code, string $prefix = '£'): void
{
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(['code' => $code], ['prefix' => $prefix, 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    app()->forgetInstance('pnlcs.currency');
}

/** A client with a stored PayPal account and an invoice waiting on it. */
function paypalStoredMethod(array $overrides = []): array
{
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'INV-PP-1',
        'status' => 'unpaid',
        'total' => 100.0,
    ]);

    $method = PaymentMethod::create($overrides + [
        'client_id' => $client->id,
        'gateway_name' => 'paypal',
        'payment_type' => 'paypal',
        'description' => 'PayPal payer@example.com',
        'remote_token' => 'vault-token-1',
        'gateway_customer_id' => 'cust-1',
        'status' => PaymentMethod::STATUS_ACTIVE,
    ]);

    return [$client, $invoice, $method];
}

/** PayPal answers the token grant, then whatever the caller names. */
function paypalFakes(array $fakes = []): void
{
    Http::fake($fakes + ['*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200)]);
}

/** A PayPal order that completed and captured, as the full representation. */
function paypalCompletedOrder(string $captureId = 'CAP-1', string $value = '100.00', string $captureStatus = 'COMPLETED'): array
{
    return [
        'id' => 'ORDER-1',
        'status' => 'COMPLETED',
        'purchase_units' => [[
            'reference_id' => 'INV-1',
            'payments' => ['captures' => [[
                'id' => $captureId,
                'status' => $captureStatus,
                'amount' => ['currency_code' => 'GBP', 'value' => $value],
            ]]],
        ]],
    ];
}

/** A PayPal refusal, in the shape Orders v2 actually returns one. */
function paypalRefusal(string $name, string $issue, string $description = 'refused'): array
{
    return [
        'name' => $name,
        'message' => 'The requested action could not be performed.',
        'debug_id' => 'dbg-1',
        'details' => [['issue' => $issue, 'description' => $description]],
    ];
}

/** The single order request that went out, or null. */
function paypalOrderRequest(): ?Request
{
    $found = null;
    Http::recorded(function (Request $request) use (&$found) {
        if (str_contains($request->url(), '/v2/checkout/orders')) {
            $found = $request;
        }

        return false;
    });

    return $found;
}

beforeEach(function () {
    paypalConfigured();
    paypalShopCurrency('GBP');
});

// ===========================================================================
// PIECE ONE — the idempotency header, per call site
// ===========================================================================

test('the charge that processes a payment carries the PayPal-Request-Id PayPal demands', function () {
    // "A PayPal-Request-Id is required if you are trying to process payment for
    // an Order." Without it this call is a 400 and no charge happens at all.
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, [
        'idempotency_key' => 'pnlcs-offsession-abc',
        'replay' => false,
    ]);

    expect(paypalOrderRequest()?->header('PayPal-Request-Id'))->toBe(['pnlcs-offsession-abc']);
});

test('a charge goes out under the caller name and never one of its own', function () {
    // The attempt row writes the key before the first POST and hands the same
    // string back on every repeat. A module that worked one out for itself
    // would send a name PayPal has no saved result for, and the "replay" would
    // charge the payer a second time.
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, [
        'idempotency_key' => 'caller-owns-this-name',
        'replay' => true,
    ]);

    expect(paypalOrderRequest()?->header('PayPal-Request-Id'))->toBe(['caller-owns-this-name']);
});

test('a replay that cannot name the key sends nothing at all and says the outcome is unknown', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['replay' => true]);

    expect(paypalOrderRequest())->toBeNull()
        ->and($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($result['success'])->toBeFalse()
        ->and($result['retryable'] ?? false)->toBeFalse();
});

test('the browser pay form opens an order with no PayPal-Request-Id', function () {
    // A key here would hand a returning customer the previous order — possibly
    // one already completed — and an approve link that cannot take money.
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100.0]);
    paypalFakes(['*/v2/checkout/orders' => Http::response(['id' => 'O-1', 'links' => []], 201)]);

    app(PayPalModule::class)->capture($invoice, 100.0);

    expect(paypalOrderRequest()?->hasHeader('PayPal-Request-Id'))->toBeFalse();
});

test('a refund carries no PayPal-Request-Id, because nothing here can tell two partial refunds apart', function () {
    // PayPal honours a request id on this endpoint for forty-five days. Keyed
    // on the capture and the amount — all this method is given — the second
    // genuine partial refund of the same size would be answered with the
    // first's result, reported as success, and written to the books as money
    // that never left.
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*/refund' => Http::response(['id' => 'RE-1', 'status' => 'COMPLETED'], 201),
    ]);

    app(PayPalModule::class)->refund('CAP-1', 25.0);

    $refund = null;
    Http::recorded(function (Request $request) use (&$refund) {
        if (str_contains($request->url(), '/refund')) {
            $refund = $request;
        }

        return false;
    });

    expect($refund)->not->toBeNull()
        ->and($refund->hasHeader('PayPal-Request-Id'))->toBeFalse();
});

test('two identical partial refunds both reach PayPal as their own request', function () {
    // The proof that the decision above is the safe one: nothing collapses
    // them, so PayPal moves the money twice because the operator asked twice.
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*/refund' => Http::response(['id' => 'RE-1', 'status' => 'COMPLETED'], 201),
    ]);

    app(PayPalModule::class)->refund('CAP-1', 25.0);
    app(PayPalModule::class)->refund('CAP-1', 25.0);

    $names = [];
    Http::recorded(function (Request $request) use (&$names) {
        if (str_contains($request->url(), '/refund')) {
            $names[] = $request->header('PayPal-Request-Id');
        }

        return false;
    });

    expect($names)->toBe([[], []]);
});

test('the token grant carries no PayPal-Request-Id', function () {
    Http::fake(['*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200)]);

    app(PayPalModule::class)->refund('CAP-1', 1.0);

    $grant = null;
    Http::recorded(function (Request $request) use (&$grant) {
        if (str_contains($request->url(), '/v1/oauth2/token')) {
            $grant = $request;
        }

        return false;
    });

    expect($grant)->not->toBeNull()
        ->and($grant->hasHeader('PayPal-Request-Id'))->toBeFalse();
});

test('a vault exchange is named after the setup token, so the same approval mints one token', function () {
    $client = Client::factory()->create();
    GatewayVaultSession::remember('paypal', (int) $client->id, 'SETUP-A');
    GatewayVaultSession::remember('paypal', (int) $client->id, 'SETUP-B');

    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v3/vault/setup-tokens/*' => Http::response(['id' => 'x', 'status' => 'APPROVED'], 200),
        '*/v3/vault/payment-tokens' => Http::response(['id' => 'pt-1', 'customer' => ['id' => 'cust-1']], 201),
    ]);

    $module = app(PayPalModule::class);
    $module->confirmVaulting($client, 'SETUP-A');
    $module->confirmVaulting($client, 'SETUP-A');
    $module->confirmVaulting($client, 'SETUP-B');

    $names = [];
    Http::recorded(function (Request $request) use (&$names) {
        if (str_ends_with($request->url(), '/v3/vault/payment-tokens')) {
            $names[] = $request->header('PayPal-Request-Id')[0] ?? null;
        }

        return false;
    });

    expect($names)->toHaveCount(3)
        ->and($names[0])->toBe($names[1])
        ->and($names[2])->not->toBe($names[0]);
});

test('detaching a stored method carries no PayPal-Request-Id', function () {
    [, , $method] = paypalStoredMethod();
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v3/vault/payment-tokens/*' => Http::response([], 204),
    ]);

    app(PayPalModule::class)->detachStoredMethod($method);

    $delete = null;
    Http::recorded(function (Request $request) use (&$delete) {
        if ($request->method() === 'DELETE') {
            $delete = $request;
        }

        return false;
    });

    expect($delete)->not->toBeNull()
        ->and($delete->hasHeader('PayPal-Request-Id'))->toBeFalse();
});

// ===========================================================================
// CURRENCY — PayPal's own table, and no multiplying by a hundred
// ===========================================================================

test('a pound charge is sent in pounds and pence', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 10.99, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest()['purchase_units'][0]['amount'])
        ->toBe(['currency_code' => 'GBP', 'value' => '10.99']);
});

test('a yen charge carries no decimal point, because PayPal refuses one', function () {
    // "This currency does not support decimals. If you pass a decimal amount,
    // an error occurs." — HUF, JPY and TWD, and only those three.
    paypalShopCurrency('JPY', '¥');
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder('CAP-1', '1990'), 201)]);

    app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 1990.0, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest()['purchase_units'][0]['amount'])
        ->toBe(['currency_code' => 'JPY', 'value' => '1990']);
});

test('a forint charge is rounded to whole forint rather than truncated or refused', function () {
    paypalShopCurrency('HUF', 'Ft');
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder('CAP-1', '1991'), 201)]);

    app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 1990.6, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest()['purchase_units'][0]['amount']['value'])->toBe('1991');
});

test('a won charge keeps its decimals, because PayPal is not Stripe', function () {
    // KRW is zero-decimal to Stripe and is not on PayPal's no-decimal list at
    // all. Copying Stripe's table across would have sent a hundredth of it.
    paypalShopCurrency('KRW', '₩');
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder('CAP-1', '5000.00'), 201)]);

    app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 5000.0, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest()['purchase_units'][0]['amount']['value'])->toBe('5000.00');
});

test('nothing is ever multiplied by a hundred on the way out or divided by it on the way back', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder('CAP-1', '100.00'), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest()['purchase_units'][0]['amount']['value'])->toBe('100.00')
        ->and($result['amount'])->toBe(100.0);
});

test('a yen refund is sent without decimals', function () {
    paypalShopCurrency('JPY', '¥');
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*/refund' => Http::response(['id' => 'RE-1', 'status' => 'COMPLETED'], 201),
    ]);

    app(PayPalModule::class)->refund('CAP-1', 1990.0);

    $refund = null;
    Http::recorded(function (Request $request) use (&$refund) {
        if (str_contains($request->url(), '/refund')) {
            $refund = $request;
        }

        return false;
    });

    expect($refund['amount'])->toBe(['value' => '1990', 'currency_code' => 'JPY']);
});

test('a yen pay-form order is sent without decimals', function () {
    paypalShopCurrency('JPY', '¥');
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 1990.0]);
    paypalFakes(['*/v2/checkout/orders' => Http::response(['id' => 'O-1', 'links' => []], 201)]);

    app(PayPalModule::class)->capture($invoice, 1990.0);

    expect(paypalOrderRequest()['purchase_units'][0]['amount']['value'])->toBe('1990');
});

// ===========================================================================
// THE MERCHANT-INITIATED DECLARATION
// ===========================================================================

test('an unattended charge declares itself merchant initiated against the stored token', function () {
    // "pass these payment indicators to avoid rejected transactions" — omit
    // them and the charge is a customer-initiated one the payer is not present
    // for, which is what a card network declines or holds the merchant liable
    // for.
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    $sent = paypalOrderRequest();

    expect($sent['payment_source']['paypal']['vault_id'])->toBe('vault-token-1')
        ->and($sent['payment_source']['paypal']['stored_credential'])->toBe([
            'payment_initiator' => 'MERCHANT',
            'usage' => 'SUBSEQUENT',
            'usage_pattern' => 'UNSCHEDULED_PREPAID',
        ])
        ->and($sent['intent'])->toBe('CAPTURE');
});

test('the charge asks for the full order back, because a minimal one names no capture', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest()?->header('Prefer'))->toBe(['return=representation']);
});

// ===========================================================================
// WHAT THE CHARGE MAY SAY ABOUT SOMEBODY'S MONEY
// ===========================================================================

test('a completed capture settles the invoice under the capture id', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder('CAP-REAL', '100.00'), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['status'])->toBe('succeeded')
        ->and($result['success'])->toBeTrue()
        // The capture id and not the order id: the webhook credits the same
        // invoice under the capture id, and PaymentService dedupes on it.
        ->and($result['transaction_id'])->toBe('CAP-REAL')
        ->and($result['amount'])->toBe(100.0);
});

test('a completed order that names no capture is an unknown outcome, never a success', function () {
    // Reporting success with no id would credit the invoice with nothing to
    // dedupe against, and the webhook a second later would credit it again.
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response([
        'id' => 'ORDER-1',
        'status' => 'COMPLETED',
        'purchase_units' => [['payments' => ['captures' => []]]],
    ], 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['success'])->toBeFalse()
        ->and($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($result['transaction_id'] ?? null)->toBeNull();
});

test('a pending capture is an unknown outcome, not a payment', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder('CAP-1', '100.00', 'PENDING'), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['status'])->toBe('failed')
        ->and($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($result['transaction_id'] ?? null)->toBeNull();
});

test('an order PayPal accepted but did not complete is an unknown outcome', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(['id' => 'O-1', 'status' => 'CREATED'], 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($result['transaction_id'] ?? null)->toBeNull();
});

test('PayPal asking for the payer is an action required, not a decline', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(
        paypalRefusal('UNPROCESSABLE_ENTITY', 'PAYER_ACTION_REQUIRED', 'instruct the buyer to return to PayPal'), 422
    )]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['status'])->toBe('requires_action')
        ->and($result['success'])->toBeFalse()
        ->and($result['retryable'])->toBeFalse()
        ->and($method->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('a vault token PayPal can no longer find sends the customer back to store one again', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(
        paypalRefusal('UNPROCESSABLE_ENTITY', 'INVALID_VAULT_ID', 'The specified Vault ID is invalid or could not be found'), 422
    )]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeFalse()
        ->and($result['decline_code'])->toBe('INVALID_VAULT_ID')
        ->and($method->fresh()->status)->toBe(PaymentMethod::STATUS_REQUIRES_UPDATE);
});

test('an ordinary funding decline is worth another morning and does not condemn the stored method', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(
        paypalRefusal('UNPROCESSABLE_ENTITY', 'INSTRUMENT_DECLINED', 'The instrument presented was either declined by the processor or bank'), 422
    )]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeTrue()
        ->and($result['outcome_unknown'] ?? false)->toBeFalse()
        ->and($method->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('PayPal refusing outright is not retried and does not condemn the stored method', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(
        paypalRefusal('UNPROCESSABLE_ENTITY', 'TRANSACTION_REFUSED', 'The request was refused'), 422
    )]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeFalse()
        ->and($method->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('a server error is an unknown outcome and is never scheduled as a fresh charge', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(['name' => 'INTERNAL_SERVER_ERROR'], 500)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($result['retryable'])->toBeFalse();
});

test('PayPal saying a previous request is still in progress is an unknown outcome even on a first send', function () {
    // "PayPal processes the first request and might fail the second request" —
    // so a 409 says somebody's charge is running, not that this one failed.
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(
        paypalRefusal('RESOURCE_CONFLICT', 'PREVIOUS_REQUEST_IN_PROGRESS', 'A previous request on this resource is currently in progress'), 409
    )]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['outcome_unknown'] ?? false)->toBeTrue();
});

test('a rate limit on a first send is an ordinary retry, not an unknown outcome', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(['name' => 'RATE_LIMIT_REACHED'], 429)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['outcome_unknown'] ?? false)->toBeFalse()
        ->and($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeTrue();
});

test('the same rate limit on a replay says nothing about the charge in flight', function () {
    // A rate limiter answers without the saved record being consulted, so on a
    // replay it is silence. Believed as a retryable decline it would schedule a
    // fresh charge on top of one nobody may assume failed.
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(['name' => 'RATE_LIMIT_REACHED'], 429)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, [
        'idempotency_key' => 'k',
        'replay' => true,
    ]);

    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($result['retryable'])->toBeFalse();
});

test('a processor decline on a replay does resolve the row, so crashed declines are still collected', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(
        paypalRefusal('UNPROCESSABLE_ENTITY', 'INSTRUMENT_DECLINED', 'declined by the processor or bank'), 422
    )]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, [
        'idempotency_key' => 'k',
        'replay' => true,
    ]);

    expect($result['outcome_unknown'] ?? false)->toBeFalse()
        ->and($result['status'])->toBe('failed');
});

test('a validation refusal on a replay is silence, because PayPal can write one without reading the record', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(
        paypalRefusal('UNPROCESSABLE_ENTITY', 'INVALID_VAULT_ID', 'could not be found'), 422
    )]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, [
        'idempotency_key' => 'k',
        'replay' => true,
    ]);

    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        // And the stored method is NOT condemned on an answer that was never
        // about it.
        ->and($method->fresh()->status)->toBe(PaymentMethod::STATUS_ACTIVE);
});

test('a connection dropped while sending the order is an unknown outcome', function () {
    [, $invoice, $method] = paypalStoredMethod();
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v2/checkout/orders' => fn () => throw new ConnectionException('timed out'),
    ]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($result['retryable'])->toBeFalse();
});

test('a connection dropped while fetching a bearer token is a plain retryable failure', function () {
    // Nothing was built, let alone sent: the order never existed, so the honest
    // answer is that nothing was taken and tomorrow is worth trying.
    [, $invoice, $method] = paypalStoredMethod();
    Http::fake(['*/v1/oauth2/token' => fn () => throw new ConnectionException('timed out')]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect($result['outcome_unknown'] ?? false)->toBeFalse()
        ->and($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeTrue();
});

// ===========================================================================
// THE GUARDS ABOVE THE POST
// ===========================================================================

test('a stored method belonging to another client is never presented', function () {
    [, $invoice] = paypalStoredMethod();
    [, , $strangersMethod] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $strangersMethod, 100.0, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest())->toBeNull()
        ->and($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeFalse();
});

test('a stored method the customer removed is never presented, however live its token still is', function () {
    [, $invoice, $method] = paypalStoredMethod();
    $method->delete();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest())->toBeNull()
        ->and($result['status'])->toBe('failed');
});

test('a stored method already needing the customer is not presented again', function () {
    [, $invoice, $method] = paypalStoredMethod(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest())->toBeNull()
        ->and($result['status'])->toBe('failed');
});

test('a method stored with another gateway is refused outright', function () {
    [, $invoice, $method] = paypalStoredMethod(['gateway_name' => 'stripe']);
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest())->toBeNull()
        ->and($result['status'])->toBe('failed');
});

test('a refusal above the POST on a replay is an unknown outcome, not a failure', function () {
    // "We declined to send anything this time" is true and irrelevant: the
    // question is what became of the charge that WAS sent.
    [, $invoice, $method] = paypalStoredMethod();
    $method->delete();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, [
        'idempotency_key' => 'k',
        'replay' => true,
    ]);

    expect($result['outcome_unknown'] ?? false)->toBeTrue()
        ->and($result['retryable'])->toBeFalse();
});

test('a legacy paypal row that never came from the vault is never presented', function () {
    // payment_methods has carried gateway_name 'paypal' since long before any
    // of this: imports, and records of how a customer pays. Implementing the
    // capability puts every one of them inside the charger's candidate query,
    // and none of them is a vault token.
    [, $invoice, $method] = paypalStoredMethod(['payment_type' => 'card']);
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 100.0, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest())->toBeNull()
        ->and($result['status'])->toBe('failed')
        ->and($result['retryable'])->toBeFalse();
});

test('a vault session whose id already belongs to somebody else is not opened', function () {
    $mine = Client::factory()->create();
    $theirs = Client::factory()->create();
    GatewayVaultSession::remember('paypal', (int) $theirs->id, 'SETUP-CLASH');

    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v3/vault/setup-tokens' => Http::response([
            'id' => 'SETUP-CLASH',
            'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/agree']],
        ], 200),
    ]);

    $session = app(PayPalModule::class)->beginVaulting($mine);

    expect($session['success'])->toBeFalse()
        ->and($session['redirect_url'] ?? null)->toBeNull()
        ->and(GatewayVaultSession::wasOpenedBy('paypal', (int) $mine->id, 'SETUP-CLASH'))->toBeFalse();
});

test('an invoice with nothing left on it is not charged', function () {
    [, $invoice, $method] = paypalStoredMethod();
    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder(), 201)]);

    $result = app(PayPalModule::class)->chargeStoredMethod($invoice, $method, 0.0, ['idempotency_key' => 'k']);

    expect(paypalOrderRequest())->toBeNull()
        ->and($result['status'])->toBe('failed');
});

// ===========================================================================
// STORING AND REMOVING A PAYMENT METHOD
// ===========================================================================

test('opening a vault session hands back PayPal approve link and writes the session down', function () {
    $client = Client::factory()->create();
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v3/vault/setup-tokens' => Http::response([
            'id' => 'SETUP-1',
            'status' => 'PAYER_ACTION_REQUIRED',
            'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/agree']],
        ], 200),
    ]);

    $session = app(PayPalModule::class)->beginVaulting($client);

    expect($session['success'])->toBeTrue()
        ->and($session['setup_intent_id'])->toBe('SETUP-1')
        ->and($session['redirect_url'])->toBe('https://paypal.test/agree')
        ->and(GatewayVaultSession::wasOpenedBy('paypal', (int) $client->id, 'SETUP-1'))->toBeTrue();
});

test('a vault session opened for another account stores nothing and asks PayPal nothing', function () {
    $mine = Client::factory()->create();
    $theirs = Client::factory()->create();
    GatewayVaultSession::remember('paypal', (int) $theirs->id, 'SETUP-THEIRS');

    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v3/vault/setup-tokens/*' => Http::response(['id' => 'SETUP-THEIRS', 'status' => 'APPROVED'], 200),
        '*/v3/vault/payment-tokens' => Http::response(['id' => 'pt-1'], 201),
    ]);

    $result = app(PayPalModule::class)->confirmVaulting($mine, 'SETUP-THEIRS');

    expect($result['success'])->toBeFalse()
        ->and(PaymentMethod::where('client_id', $mine->id)->count())->toBe(0);

    Http::assertNothingSent();
});

test('a vault session nobody opened stores nothing', function () {
    $client = Client::factory()->create();
    Http::fake(['*' => Http::response([], 200)]);

    $result = app(PayPalModule::class)->confirmVaulting($client, 'SETUP-INVENTED');

    expect($result['success'])->toBeFalse();
    Http::assertNothingSent();
});

test('a vault session the payer has not approved stores nothing', function () {
    $client = Client::factory()->create();
    GatewayVaultSession::remember('paypal', (int) $client->id, 'SETUP-1');

    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v3/vault/setup-tokens/*' => Http::response(['id' => 'SETUP-1', 'status' => 'PAYER_ACTION_REQUIRED'], 200),
        '*/v3/vault/payment-tokens' => Http::response(['id' => 'pt-1'], 201),
    ]);

    $result = app(PayPalModule::class)->confirmVaulting($client, 'SETUP-1');

    expect($result['success'])->toBeFalse()
        ->and(PaymentMethod::where('client_id', $client->id)->count())->toBe(0);
});

test('an approved session becomes a stored PayPal account the charger can use', function () {
    $client = Client::factory()->create();
    GatewayVaultSession::remember('paypal', (int) $client->id, 'SETUP-1');

    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v3/vault/setup-tokens/*' => Http::response(['id' => 'SETUP-1', 'status' => 'APPROVED'], 200),
        '*/v3/vault/payment-tokens' => Http::response([
            'id' => 'pt-live',
            'customer' => ['id' => 'cust-9'],
            'payment_source' => ['paypal' => ['email_address' => 'payer@example.com']],
        ], 201),
    ]);

    $result = app(PayPalModule::class)->confirmVaulting($client, 'SETUP-1');
    $stored = PaymentMethod::where('client_id', $client->id)->sole();

    expect($result['success'])->toBeTrue()
        ->and($stored->remote_token)->toBe('pt-live')
        ->and($stored->gateway_name)->toBe('paypal')
        // Not 'cc': a PayPal account has no expiry, and the monthly expiry
        // alert must not queue a warning for it that can never be written.
        ->and($stored->payment_type)->toBe('paypal')
        ->and($stored->description)->toBe('PayPal payer@example.com')
        ->and($stored->is_default)->toBeTrue()
        ->and($stored->status)->toBe(PaymentMethod::STATUS_ACTIVE)
        ->and(GatewayCustomer::idFor('paypal', (int) $client->id))->toBe('cust-9');
});

test('confirming the same session twice leaves one stored method', function () {
    $client = Client::factory()->create();
    GatewayVaultSession::remember('paypal', (int) $client->id, 'SETUP-1');

    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v3/vault/setup-tokens/*' => Http::response(['id' => 'SETUP-1', 'status' => 'APPROVED'], 200),
        '*/v3/vault/payment-tokens' => Http::response([
            'id' => 'pt-live',
            'customer' => ['id' => 'cust-9'],
        ], 201),
    ]);

    $module = app(PayPalModule::class);
    $module->confirmVaulting($client, 'SETUP-1');
    $module->confirmVaulting($client, 'SETUP-1');

    expect(PaymentMethod::where('client_id', $client->id)->count())->toBe(1);
});

test('a payment method PayPal is no longer holding is a finished detach', function () {
    [, , $method] = paypalStoredMethod();
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v3/vault/payment-tokens/*' => Http::response(
            paypalRefusal('RESOURCE_NOT_FOUND', 'INVALID_RESOURCE_ID', 'Specified resource ID does not exist'), 404
        ),
    ]);

    expect(app(PayPalModule::class)->detachStoredMethod($method)['success'])->toBeTrue();
});

test('PayPal having a bad minute leaves the detach outstanding', function () {
    [, , $method] = paypalStoredMethod();
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v3/vault/payment-tokens/*' => Http::response(['name' => 'INTERNAL_SERVER_ERROR'], 500),
    ]);

    $result = app(PayPalModule::class)->detachStoredMethod($method);

    expect($result['success'])->toBeFalse()
        ->and($result['retryable'])->toBeTrue();
});

test('a row with no token has nothing for PayPal to let go of', function () {
    [, , $method] = paypalStoredMethod(['remote_token' => null]);
    Http::fake(['*' => Http::response([], 200)]);

    expect(app(PayPalModule::class)->detachStoredMethod($method)['success'])->toBeTrue();
    Http::assertNothingSent();
});

test('a method stored elsewhere is not detached at PayPal', function () {
    [, , $method] = paypalStoredMethod(['gateway_name' => 'stripe']);
    Http::fake(['*' => Http::response([], 200)]);

    $result = app(PayPalModule::class)->detachStoredMethod($method);

    expect($result['success'])->toBeFalse()
        ->and($result['retryable'])->toBeFalse();
    Http::assertNothingSent();
});

// ===========================================================================
// THROUGH THE ENGINE, AND THE SHOP THAT HAS NOT ASKED FOR ANY OF THIS
// ===========================================================================

test('the charger picks up a stored PayPal account and settles the invoice under the capture id', function () {
    // The engine is gateway-agnostic and chooses by capability, so this is the
    // proof that the capability was implemented in the shape it expects: the
    // row's own key goes out as the PayPal-Request-Id, and the capture id is
    // what the ledger records — the same pair the PayPal webhook would use, so
    // the two can never credit the invoice twice.
    Setting::set('AutoChargeEnabled', '1');
    GatewaySettings::updateOrCreate(['gateway' => 'paypal', 'setting' => 'active'], ['value' => '1']);

    $client = Client::factory()->create(['auto_charge' => true]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => 100.0,
        'total' => 100.0,
        'due_date' => now()->addDay(),
    ]);
    PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'paypal',
        'payment_type' => 'paypal',
        'description' => 'PayPal payer@example.com',
        'remote_token' => 'vault-token-1',
        'gateway_customer_id' => 'cust-1',
        'is_default' => true,
        'status' => PaymentMethod::STATUS_ACTIVE,
    ]);

    paypalFakes(['*/v2/checkout/orders' => Http::response(paypalCompletedOrder('CAP-ENGINE', '100.00'), 201)]);

    Sleep::fake();
    app(AutoChargeService::class)->run();
    Sleep::fake(false);

    $row = InvoiceChargeAttempt::forInvoice($invoice);
    $transaction = Transaction::where('invoice_id', $invoice->id)->sole();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and($transaction->gateway)->toBe('paypal')
        ->and($transaction->transaction_id)->toBe('CAP-ENGINE')
        ->and((float) $transaction->amount_in)->toBe(100.0)
        ->and($row?->last_transaction_id)->toBe('CAP-ENGINE')
        // The name the order went out under is the row's own, never one the
        // module worked out for itself.
        ->and(paypalOrderRequest()?->header('PayPal-Request-Id'))->toBe([$row->idempotency_key]);
});

test('a shop that configured PayPal before Stripe still gets its Stripe card form', function () {
    // tokenisedGateways() is built from the gateway_settings rows in whatever
    // order the table hands them over. PayPal's rows are written first here on
    // purpose: before the flow guard, array_key_first() answered 'paypal' and
    // the page — which draws only what it recognises — rendered without a card
    // field at all. Nothing failed; customers simply stopped being able to
    // store a card.
    Setting::set('AutoChargeEnabled', '1');
    GatewaySettings::updateOrCreate(['gateway' => 'paypal', 'setting' => 'active'], ['value' => '1']);

    foreach (['active' => '1', 'publishable_key' => 'pk_test_visible', 'secret_key' => 'sk_test_secret'] as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $k], ['value' => $v]);
    }

    Http::fake([
        '*/v1/setup_intents' => Http::response(['id' => 'seti_1', 'client_secret' => 'seti_1_secret'], 200),
        '*/v1/customers*' => Http::response(['id' => 'cus_1'], 200),
    ]);

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $this->actingAs($user)
        ->get(route('client.payment-methods.add-card'))
        ->assertOk()
        ->assertSee('pk_test_visible', false)
        ->assertSee('pn-card-element', false);
});

test('with automatic payment switched off, a PayPal shop sees no card storage at all', function () {
    // Invariant zero: the default every installation is in. The capability
    // exists in the module and changes nothing anybody can reach.
    GatewaySettings::updateOrCreate(['gateway' => 'paypal', 'setting' => 'active'], ['value' => '1']);

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $this->actingAs($user)->get(route('client.payment-methods.add-card'))->assertNotFound();
    $this->actingAs($user)
        ->get(route('client.payment-methods.index'))
        ->assertOk()
        ->assertDontSee(__('client.payment_methods.add_card_button'));
});

test('a stored PayPal account is described to its owner rather than shown as a card', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    PaymentMethod::create([
        'client_id' => $client->id,
        'gateway_name' => 'paypal',
        'payment_type' => 'paypal',
        'description' => 'PayPal payer@example.com',
        'remote_token' => 'pt-1',
        'status' => PaymentMethod::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
        ->get(route('client.payment-methods.index'))
        ->assertOk()
        ->assertSee(__('client.payment_methods.type_paypal'))
        ->assertDontSee('client.payment_methods.type_paypal');
});
