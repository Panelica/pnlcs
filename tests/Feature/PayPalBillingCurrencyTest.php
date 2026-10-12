<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\PayPal\PayPalModule;

/*
 * PayPal: charging an invoice in its billing currency, the option Stripe and
 * Mollie have. The shop prices in euros and bills this customer in zloty: a
 * €10.00 invoice raised at 4.30 shows zł43.00, and with the option on that is
 * what is charged. Nothing about the conversion is written on the order (the
 * browser builds it); it is worked out from the invoice and the recorded
 * payment. Measured against the API with faked responses only.
 */

function ppbShop(bool $billingCurrency = true): void
{
    foreach (['client_id' => 'cid', 'client_secret' => 'csec', 'sandbox' => '1', 'charge_billing_currency' => $billingCurrency ? '1' : '0'] as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'paypal', 'setting' => $k], ['value' => $v]);
    }
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    Currency::updateOrCreate(['code' => 'PLN'], ['prefix' => '', 'suffix' => ' zł', 'rate' => 4.35, 'is_default' => false]);
    app()->forgetInstance('pnlcs.currency');
}

function ppbInvoice(string $billing = 'PLN', float $rate = 4.3): Invoice
{
    return Invoice::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 10.0, 'source_currency' => 'EUR',
    ], $billing === 'EUR' ? ['billing_currency' => null, 'exchange_rate' => null] : ['billing_currency' => $billing, 'exchange_rate' => $rate]));
}

function ppbCapture(string $value, string $currency = 'PLN', array $more = []): void
{
    Http::fake($more + [
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*' => Http::response(['status' => 'COMPLETED', 'amount' => ['value' => $value, 'currency_code' => $currency]], 200),
        '*' => Http::response(['id' => 'ORDER-1', 'status' => 'COMPLETED'], 201),
    ]);
}

function ppbPayer(Invoice $invoice): User
{
    $user = User::factory()->create();
    $user->clients()->attach($invoice->client_id);

    return $user;
}

function ppbOrder(): ?Request
{
    $found = null;
    Http::recorded(function (Request $r) use (&$found) {
        if (str_contains($r->url(), '/v2/checkout/orders')) {
            $found = $r;
        }

        return false;
    });

    return $found;
}

test('switched off, an invoice billed in zloty is ordered in euros as before', function () {
    ppbShop(false);
    ppbCapture('10.00', 'EUR');

    (new PayPalModule)->capture(ppbInvoice(), 10.0);

    expect(ppbOrder()['purchase_units'][0]['amount'])->toBe(['currency_code' => 'EUR', 'value' => '10.00']);
});

test('switched on, it is ordered in zloty at the invoice\'s rate', function () {
    ppbShop();
    ppbCapture('43.00');

    (new PayPalModule)->capture(ppbInvoice(), 10.0);

    expect(ppbOrder()['purchase_units'][0]['amount'])->toBe(['currency_code' => 'PLN', 'value' => '43.00']);
});

test('a billing currency PayPal does not take, and one in the shop currency, are ordered in euros', function (string $billing) {
    ppbShop();
    ppbCapture('10.00', 'EUR');

    (new PayPalModule)->capture(ppbInvoice($billing, 38.0), 10.0);

    expect(ppbOrder()['purchase_units'][0]['amount'])->toBe(['currency_code' => 'EUR', 'value' => '10.00']);
})->with(['lira' => 'TRY', 'euro' => 'EUR']);

test('the buttons load and order in the currency the invoice is charged in, without decimals for yen', function () {
    ppbShop();
    $form = (new PayPalModule)->getPaymentForm(ppbInvoice());
    expect($form)->toContain('&currency=PLN')->toContain('value: "43.00", currency_code: "PLN"');

    Currency::updateOrCreate(['code' => 'JPY'], ['prefix' => '¥', 'suffix' => '', 'rate' => 162.5, 'is_default' => false]);
    $form = (new PayPalModule)->getPaymentForm(ppbInvoice('JPY', 162.37));
    expect($form)->toContain('value: "1624", currency_code: "JPY"');
});

test('a capture of what was due in zloty pays the invoice in euros', function () {
    ppbShop();
    ppbCapture('43.00');
    $invoice = ppbInvoice();

    $this->actingAs(ppbPayer($invoice))->postJson("/gateway/paypal/capture/{$invoice->id}", ['capture_id' => 'CAP-PLN'])
        ->assertJson(['success' => true]);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) Transaction::where('invoice_id', $invoice->id)->value('amount_in'))->toBe(10.0);
});

test('a part paid in zloty is converted back at the invoice\'s rate', function () {
    ppbShop();
    ppbCapture('21.50');
    $invoice = ppbInvoice();

    $verified = (new PayPalModule)->verifyInvoiceCapture($invoice, 'CAP-PART');

    expect($verified['success'])->toBeTrue()->and($verified['amount'])->toBe(5.0);
});

test('with the option on, a capture in euros still pays the invoice at face value', function () {
    // A pay page opened before the option was switched on built its order in
    // euros; the customer has paid, and euros are the invoice's own currency.
    ppbShop();
    ppbCapture('10.00', 'EUR');
    $invoice = ppbInvoice();

    $this->actingAs(ppbPayer($invoice))->postJson("/gateway/paypal/capture/{$invoice->id}", ['capture_id' => 'CAP-EUR'])
        ->assertJson(['success' => true]);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) Transaction::where('invoice_id', $invoice->id)->value('amount_in'))->toBe(10.0);
});

test('with the option on, a capture in any third currency is refused', function () {
    ppbShop();
    ppbCapture('10.00', 'USD');

    expect((new PayPalModule)->verifyInvoiceCapture(ppbInvoice(), 'CAP-USD')['success'])->toBeFalse();
});

test('the invoice\'s rate covers what was due; zloty above it is converted at today\'s rate', function () {
    // The buttons build the order in the browser, so its amount is the
    // browser's to choose. zł43.00 was due (€10.00 at 4.30); zł43.00 more is
    // worth what zloty are worth today (4.35), not what they were worth then.
    ppbShop();
    ppbCapture('86.00');

    $verified = (new PayPalModule)->verifyInvoiceCapture(ppbInvoice(), 'CAP-DOUBLE');

    expect($verified['success'])->toBeTrue()->and($verified['amount'])->toBe(round(10 + 43 / 4.35, 2));
});

test('zloty paid on an old, settled invoice become credit at today\'s rate, not at that invoice\'s', function () {
    ppbShop();
    $invoice = ppbInvoice('PLN', 4.0);
    Transaction::create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'gateway' => 'paypal', 'transaction_id' => 'CAP-OLD', 'amount_in' => 10.0, 'date' => now()]);
    $invoice->update(['status' => 'paid']);
    $before = (float) $invoice->client->credit;
    ppbCapture('435.00');

    $this->actingAs(ppbPayer($invoice))->postJson("/gateway/paypal/capture/{$invoice->id}", ['capture_id' => 'CAP-NEW'])
        ->assertJson(['success' => true]);

    expect((float) $invoice->client->fresh()->credit - $before)->toBe(100.0);
});

test('with the option off, a capture in zloty is refused as before', function () {
    ppbShop(false);
    ppbCapture('43.00');

    expect((new PayPalModule)->verifyInvoiceCapture(ppbInvoice(), 'CAP-PLN')['success'])->toBeFalse();
});

test('the webhook holds the capture to the invoice\'s currency and reports it in euros', function () {
    ppbShop();
    ppbCapture('43.00');
    $invoice = ppbInvoice();

    $result = (new PayPalModule)->processWebhook([
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => ['id' => 'CAP-PLN', 'purchase_units' => [['reference_id' => 'INV-'.$invoice->id]]],
    ]);

    expect($result['success'])->toBeTrue()->and($result['amount'])->toBe(10.0);
});

test('an unattended charge goes out in zloty and settles in euros', function () {
    ppbShop();
    $invoice = ppbInvoice();
    $method = PaymentMethod::create([
        'client_id' => $invoice->client_id, 'gateway_name' => 'paypal', 'payment_type' => 'paypal', 'description' => 'PayPal',
        'remote_token' => 'vault-1', 'gateway_customer_id' => 'cust-1', 'status' => PaymentMethod::STATUS_ACTIVE,
    ]);
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v2/checkout/orders' => Http::response(['id' => 'ORDER-1', 'status' => 'COMPLETED', 'purchase_units' => [[
            'payments' => ['captures' => [['id' => 'CAP-AUTO', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'PLN', 'value' => '43.00']]]],
        ]]], 201),
    ]);

    $result = (new PayPalModule)->chargeStoredMethod($invoice, $method, 10.0, ['idempotency_key' => 'k']);

    expect(ppbOrder()['purchase_units'][0]['amount'])->toBe(['currency_code' => 'PLN', 'value' => '43.00'])
        ->and($result['success'])->toBeTrue()
        ->and($result['amount'])->toBe(10.0);
});

test('a renewal answered after the option was switched off is still read at the invoice\'s rate', function () {
    // Sent in zloty while the option was on, with no answer heard; the option
    // was then switched off. The replay goes out under the same
    // PayPal-Request-Id, and PayPal answers it out of the first request's
    // record: a capture of zł43.00, which is €10.00 and not €43.00.
    ppbShop(false);
    $invoice = ppbInvoice();
    $method = PaymentMethod::create([
        'client_id' => $invoice->client_id, 'gateway_name' => 'paypal', 'payment_type' => 'paypal', 'description' => 'PayPal',
        'remote_token' => 'vault-1', 'gateway_customer_id' => 'cust-1', 'status' => PaymentMethod::STATUS_ACTIVE,
    ]);
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v2/checkout/orders' => Http::response(['id' => 'ORDER-1', 'status' => 'COMPLETED', 'purchase_units' => [[
            'payments' => ['captures' => [['id' => 'CAP-AUTO', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'PLN', 'value' => '43.00']]]],
        ]]], 201),
    ]);

    $result = (new PayPalModule)->chargeStoredMethod($invoice, $method, 10.0, ['replay' => true, 'idempotency_key' => 'k']);

    expect($result['success'])->toBeTrue()->and($result['amount'])->toBe(10.0);
});

test('a refund of a zloty capture is sent in zloty at the rate it was taken at', function (float $asked, string $sent) {
    ppbShop();
    $invoice = ppbInvoice();
    Transaction::create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'gateway' => 'paypal', 'transaction_id' => 'CAP-PLN', 'amount_in' => 10.0, 'date' => now()]);
    ppbCapture('43.00', 'PLN', ['*/refund' => Http::response(['id' => 'R-1', 'status' => 'COMPLETED'], 201)]);

    (new PayPalModule)->refund('CAP-PLN', $asked);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/refund') && $r['amount'] === ['value' => $sent, 'currency_code' => 'PLN']);
})->with(['in full' => [10.0, '43.00'], 'a part' => [2.5, '10.75']]);

test('a refund of a euro capture sends euros, with no look-up for an invoice billed in euros', function () {
    ppbShop();
    $invoice = ppbInvoice('EUR');
    Transaction::create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'gateway' => 'paypal', 'transaction_id' => 'CAP-EUR', 'amount_in' => 10.0, 'date' => now()]);
    ppbCapture('10.00', 'EUR', ['*/refund' => Http::response(['id' => 'R-1', 'status' => 'COMPLETED'], 201)]);

    (new PayPalModule)->refund('CAP-EUR', 10.0);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/refund') && $r['amount'] === ['value' => '10.00', 'currency_code' => 'EUR']);
    Http::assertNotSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), '/v2/payments/captures/'));
});
