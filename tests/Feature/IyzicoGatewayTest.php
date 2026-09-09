<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewayLog;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Http;

/*
 * iyzico, the card gateway for Turkey.
 *
 * The card never touches this server - iyzico's own form runs 3D Secure - so
 * what has to be right here is everything around it: the request is signed, the
 * outcome is asked back from iyzico rather than believed from the browser, and
 * the amount credited comes from what was written down when the form was
 * opened, not from anything the customer's browser posted.
 *
 * The callback is the dangerous door. It carries no session cookie (iyzico
 * POSTs it cross-site), so it cannot be authenticated, and a forged token must
 * therefore be worth nothing at all.
 */
function iyzicoConfigured(bool $sandbox = true): void
{
    foreach ([
        'api_key' => 'test-api-key',
        'secret_key' => 'test-secret-key',
        'sandbox' => $sandbox ? '1' : '0',
        'installments' => '1,3,6',
        'save_cards' => '1',
    ] as $setting => $value) {
        GatewaySettings::updateOrCreate(
            ['gateway' => 'iyzico', 'setting' => $setting],
            ['value' => $value]
        );
    }
}

function iyzicoInvoice(float $total = 100): Invoice
{
    $client = Client::factory()->create([
        'country' => 'TR',
        'address1' => '12 Market Street',
        'city' => 'Istanbul',
        'postcode' => '34000',
        'tax_id' => '1234567890',
        'phone_number' => '05401112233',
    ]);

    return Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => $total,
    ]);
}

function iyzico(): object
{
    return app(ModuleRegistry::class)->getGatewayModule('iyzico');
}

test('the gateway is registered and offered like every other one', function () {
    expect(iyzico())->not->toBeNull()
        ->and(iyzico()->getModuleName())->toBe('iyzico')
        ->and(iyzico()->isTokenised())->toBeTrue();
});

test('it refuses to talk to iyzico before an operator has entered their keys', function () {
    Http::fake();

    $result = iyzico()->capture(iyzicoInvoice(), 100);

    expect($result['success'])->toBeFalse();
    Http::assertNothingSent();
});

test('opening the form signs the request the way iyzico expects', function () {
    iyzicoConfigured();
    Http::fake(['*iyzipay.com/*' => Http::response([
        'status' => 'success', 'token' => 'form-token-1', 'checkoutFormContent' => '<script></script>',
    ], 200)]);

    iyzico()->capture(iyzicoInvoice(), 100);

    Http::assertSent(function ($request) {
        // Sandbox while the sandbox switch is on: a live charge during a test
        // run is not a recoverable mistake.
        return str_starts_with($request->url(), 'https://sandbox-api.iyzipay.com')
            && str_starts_with($request->header('Authorization')[0], 'IYZWSv2 ')
            && $request->header('x-iyzi-rnd')[0] !== ''
            // The basket total has to equal the price exactly or iyzico refuses.
            && $request['price'] === $request['paidPrice']
            && $request['basketItems'][0]['price'] === $request['price']
            && $request['currency'] === 'TRY';
    });
});

test('it sends the buyer address the invoice was issued to', function () {
    iyzicoConfigured();
    Http::fake(['*' => Http::response(['status' => 'success', 'token' => 't'], 200)]);

    $invoice = iyzicoInvoice();
    $invoice->update([
        'buyer_address1' => '9 Frozen Road',
        'buyer_city' => 'Ankara',
        'buyer_country' => 'TR',
        'buyer_postcode' => '06000',
        'buyer_tax_id' => '9999999999',
    ]);
    // The customer moves house after the invoice was issued.
    $invoice->client->update(['address1' => 'Somewhere else', 'city' => 'Izmir']);

    iyzico()->capture($invoice->fresh(), 100);

    Http::assertSent(function ($request) {
        return $request['buyer']['registrationAddress'] === '9 Frozen Road'
            && $request['buyer']['city'] === 'Ankara'
            && $request['buyer']['country'] === 'Turkey'
            && $request['buyer']['identityNumber'] === '9999999999'
            && $request['billingAddress']['address'] === '9 Frozen Road';
    });
});

test('opening the form writes down what to credit later', function () {
    iyzicoConfigured();
    Http::fake(['*' => Http::response(['status' => 'success', 'token' => 'tok-99'], 200)]);

    $invoice = iyzicoInvoice(80);
    iyzico()->capture($invoice, 80);

    $log = GatewayLog::where('gateway', 'iyzico')->latest('id')->first();
    $data = json_decode((string) $log->data, true);

    expect($log->result)->toBe('initialized')
        ->and($data['token'])->toBe('tok-99')
        ->and($data['invoice_id'])->toBe($invoice->id)
        ->and((float) $data['due_amount'])->toBe(80.0);
});

test('a forged callback token pays nothing', function () {
    iyzicoConfigured();
    // iyzico is asked about the token and says no.
    Http::fake(['*' => Http::response(['status' => 'failure', 'errorMessage' => 'Invalid token'], 200)]);

    $invoice = iyzicoInvoice();

    $this->post(route('gateway.iyzico.callback'), ['token' => 'made-up-token'])->assertOk();

    expect($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('gateway', 'iyzico')->count())->toBe(0);
});

test('the callback credits the amount that was written down, not one the browser sent', function () {
    iyzicoConfigured();
    $invoice = iyzicoInvoice(100);

    GatewayLog::create([
        'gateway' => 'iyzico',
        'date' => now(),
        'data' => json_encode([
            'token' => 'good-token',
            'invoice_id' => $invoice->id,
            'try_amount' => 3500.0,
            'due_amount' => 100.0,
            'rate' => 35.0,
        ]),
        'result' => 'initialized',
    ]);

    Http::fake(['*' => Http::response([
        'status' => 'success',
        'paymentStatus' => 'SUCCESS',
        'paymentId' => 'pay-1',
        'conversationId' => (string) $invoice->id,
        'paidPrice' => 3500.0,
        'currency' => 'TRY',
    ], 200)]);

    // The browser claims a different, much smaller amount. It is ignored.
    $this->post(route('gateway.iyzico.callback'), [
        'token' => 'good-token',
        'paidPrice' => '1',
        'amount' => '1',
    ])->assertOk();

    $transaction = Transaction::where('gateway', 'iyzico')->first();

    expect($transaction)->not->toBeNull()
        ->and((float) $transaction->amount_in)->toBe(100.0)
        ->and($invoice->fresh()->status)->toBe('paid');
});

test('a stored card keeps only the keys iyzico gives back, never a card number', function () {
    iyzicoConfigured();
    $invoice = iyzicoInvoice(50);

    GatewayLog::create([
        'gateway' => 'iyzico',
        'date' => now(),
        'data' => json_encode([
            'token' => 'card-token', 'invoice_id' => $invoice->id,
            'try_amount' => 1750.0, 'due_amount' => 50.0, 'rate' => 35.0,
        ]),
        'result' => 'initialized',
    ]);

    Http::fake(['*' => Http::response([
        'status' => 'success', 'paymentStatus' => 'SUCCESS', 'paymentId' => 'pay-2',
        'conversationId' => (string) $invoice->id, 'paidPrice' => 1750.0,
        'cardUserKey' => 'cuk-1', 'cardToken' => 'ct-1',
        'lastFourDigits' => '4242', 'cardAssociation' => 'VISA',
    ], 200)]);

    $this->post(route('gateway.iyzico.callback'), ['token' => 'card-token'])->assertOk();

    $method = PaymentMethod::where('client_id', $invoice->client_id)->where('gateway_name', 'iyzico')->first();
    $stored = json_decode((string) $method->remote_token, true);

    expect($method->last_four)->toBe('4242')
        ->and($stored)->toBe(['cardUserKey' => 'cuk-1', 'cardToken' => 'ct-1']);
});

test('money taken for an invoice that cannot be found is announced, not just logged', function () {
    iyzicoConfigured();

    Http::fake(['*' => Http::response([
        'status' => 'success', 'paymentStatus' => 'SUCCESS', 'paymentId' => 'pay-orphan',
        'conversationId' => '999999', 'paidPrice' => 100.0,
    ], 200)]);

    $dispatched = [];
    app()->bind(\App\Services\NotificationService::class, function () use (&$dispatched) {
        return new class($dispatched) extends \App\Services\NotificationService
        {
            public function __construct(private &$seen) {}

            public function dispatch(string $eventType, array $data = []): void
            {
                $this->seen[] = $eventType;
            }
        };
    });

    $this->post(route('gateway.iyzico.callback'), ['token' => 'orphan-token'])->assertOk();

    expect($dispatched)->toContain('payment.failed');
});

test('starting a payment on somebody elses invoice is refused', function () {
    iyzicoConfigured();

    $mine = User::factory()->create();
    $mine->clients()->attach(Client::factory()->create()->id);

    $theirs = iyzicoInvoice();

    $this->actingAs($mine)->post(route('gateway.iyzico.init', $theirs->id))->assertForbidden();
});

test('the invoice page offers a card button once the gateway is configured', function () {
    iyzicoConfigured();

    $html = iyzico()->getPaymentForm(iyzicoInvoice(120));

    expect($html)->toContain(route('gateway.iyzico.init', Invoice::latest('id')->first()->id))
        ->and($html)->toContain(__('messages.iyzico.secure_note'));
});

test('the amount is converted with the stored TRY rate when the shop prices in something else', function () {
    iyzicoConfigured();
    Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '₺', 'rate' => 40, 'name' => 'Turkish Lira']);
    Http::fake(['*' => Http::response(['status' => 'success', 'token' => 't'], 200)]);

    iyzico()->capture(iyzicoInvoice(10), 10);

    Http::assertSent(function ($request) {
        return shop_currency_code() === 'TRY'
            ? $request['price'] === '10.00'
            : $request['price'] === '400.00';
    });
});
