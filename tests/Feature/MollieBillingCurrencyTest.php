<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Mollie\MollieModule;

/*
 * Mollie: charging an invoice in its billing currency, the same option Stripe has.
 * The shop prices in euros and bills this customer in zloty: a €10.00 invoice
 * raised at 4.30 shows zł43.00, and with the option on that is what is charged.
 * Measured against the API with faked responses only; no live Mollie account.
 */

function mollieShop(bool $billingCurrency = true): void
{
    foreach (['api_key' => 'test_key', 'test_mode' => '1', 'charge_billing_currency' => $billingCurrency ? '1' : '0'] as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'mollie', 'setting' => $k], ['value' => $v]);
    }
    Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    Currency::updateOrCreate(['code' => 'PLN'], ['prefix' => '', 'suffix' => ' zł', 'rate' => 4.35, 'is_default' => false]);
    app()->forgetInstance('pnlcs.currency');
}

function mollieInvoice(bool $inZloty = true): Invoice
{
    return Invoice::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 10.0, 'source_currency' => 'EUR',
    ], $inZloty ? ['billing_currency' => 'PLN', 'exchange_rate' => 4.3] : ['billing_currency' => null, 'exchange_rate' => null]));
}

function molliePayment(Invoice $invoice, string $value, string $currency = 'PLN', array $meta = ['source_currency' => 'EUR', 'source_amount' => '10.00', 'rate' => '4.3']): array
{
    return ['id' => 'tr_1', 'status' => 'paid', 'amount' => ['currency' => $currency, 'value' => $value],
        'metadata' => ['invoice_id' => $invoice->id, 'invoice_num' => 'INV-1'] + $meta];
}

test('switched off, an invoice billed in zloty is charged in euros as before', function () {
    mollieShop(false);
    Http::fake(['*' => Http::response(['id' => 'tr_1', '_links' => ['checkout' => ['href' => 'https://pay']]], 201)]);

    (new MollieModule)->capture(mollieInvoice(), 10.0);

    Http::assertSent(fn (Request $r) => $r['amount'] === ['currency' => 'EUR', 'value' => '10.00'] && ! isset($r['metadata']['rate']));
});

test('switched on, it is charged in zloty at the invoice\'s rate, with what that stands for', function () {
    mollieShop();
    Http::fake(['*' => Http::response(['id' => 'tr_1', '_links' => ['checkout' => ['href' => 'https://pay']]], 201)]);

    (new MollieModule)->capture(mollieInvoice(), 10.0);

    Http::assertSent(fn (Request $r) => $r['amount'] === ['currency' => 'PLN', 'value' => '43.00']
        && $r['metadata']['source_currency'] === 'EUR' && $r['metadata']['source_amount'] === '10.00' && $r['metadata']['rate'] === '4.3');
});

test('an invoice billed in the shop currency is charged in it either way', function () {
    mollieShop();
    Http::fake(['*' => Http::response(['id' => 'tr_1'], 201)]);

    (new MollieModule)->capture(mollieInvoice(false), 10.0);

    Http::assertSent(fn (Request $r) => $r['amount'] === ['currency' => 'EUR', 'value' => '10.00']);
});

test('the pay button names what is due in the currency it will be charged in', function () {
    mollieShop();
    $invoice = mollieInvoice();
    expect((new MollieModule)->getPaymentForm($invoice))->toContain(e(billing_money_fmt(10.0, $invoice)));

    mollieShop(false);
    expect((new MollieModule)->getPaymentForm($invoice))->toContain(e(invoice_money_fmt(10.0, $invoice)));
});

test('a payment in full is recorded as the euro amount, a part is converted back', function (string $paid, float $recorded) {
    mollieShop();
    $invoice = mollieInvoice();
    Http::fake(['*/payments/tr_1' => Http::response(molliePayment($invoice, $paid))]);

    $result = (new MollieModule)->processWebhook(['id' => 'tr_1']);

    expect($result['amount'])->toBe($recorded)->and((int) $result['invoice_id'])->toBe($invoice->id);
})->with(['in full' => ['43.00', 10.0], 'a part' => ['21.50', 5.0]]);

test('a payment in the shop currency is recorded as it came', function () {
    mollieShop();
    $invoice = mollieInvoice(false);
    Http::fake(['*/payments/tr_1' => Http::response(molliePayment($invoice, '10.00', 'EUR', []))]);

    expect((new MollieModule)->processWebhook(['id' => 'tr_1'])['amount'])->toBe(10.0);
});

test('a refund of a zloty payment goes back in zloty, the whole of it exactly', function (float $asked, string $sent) {
    mollieShop();
    $invoice = mollieInvoice();
    Transaction::create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'gateway' => 'mollie', 'transaction_id' => 'tr_1', 'amount_in' => 10.0, 'date' => now()]);
    Http::fake([
        '*/payments/tr_1/refunds' => Http::response(['id' => 're_1'], 201),
        '*/payments/tr_1' => Http::response(molliePayment($invoice, '43.00')),
    ]);

    (new MollieModule)->refund('tr_1', $asked);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/refunds') && $r['amount'] === ['currency' => 'PLN', 'value' => $sent]);
})->with(['in full' => [10.0, '43.00'], 'a part' => [2.5, '10.75']]);

test('a refund for an invoice in the shop currency sends what it always sent', function () {
    mollieShop();
    $invoice = mollieInvoice(false);
    Transaction::create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'gateway' => 'mollie', 'transaction_id' => 'tr_1', 'amount_in' => 10.0, 'date' => now()]);
    Http::fake(['*' => Http::response(['id' => 're_1'], 201)]);

    (new MollieModule)->refund('tr_1', 10.0);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => $r['amount'] === ['currency' => 'EUR', 'value' => '10.00']);
});

test('a billing currency Mollie does not take is charged in the shop currency instead', function () {
    mollieShop();
    $invoice = Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 10.0,
        'source_currency' => 'EUR', 'billing_currency' => 'TRY', 'exchange_rate' => 40,
    ]);
    Http::fake(['*' => Http::response(['id' => 'tr_1'], 201)]);

    (new MollieModule)->capture($invoice, 10.0);
    expect((new MollieModule)->getPaymentForm($invoice))->toContain(e(invoice_money_fmt(10.0, $invoice)));

    Http::assertSent(fn (Request $r) => $r['amount'] === ['currency' => 'EUR', 'value' => '10.00'] && ! isset($r['metadata']['rate']));
});

test('a currency without minor units is written without decimals', function () {
    mollieShop();
    Currency::updateOrCreate(['code' => 'JPY'], ['prefix' => '¥', 'suffix' => '', 'rate' => 162.5, 'is_default' => false]);
    $invoice = Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 10.0,
        'source_currency' => 'EUR', 'billing_currency' => 'JPY', 'exchange_rate' => 162.5,
    ]);
    Http::fake(['*' => Http::response(['id' => 'tr_1'], 201)]);

    (new MollieModule)->capture($invoice, 10.0);

    Http::assertSent(fn (Request $r) => $r['amount'] === ['currency' => 'JPY', 'value' => '1625']);
});
