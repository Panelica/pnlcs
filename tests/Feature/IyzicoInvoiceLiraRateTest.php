<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewayLog;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Iyzico\IyzicoModule;

/*
 * An invoice billed in lira says what it comes to: €10.00 at the rate frozen the
 * day it was raised (40) is ₺400.00, and that is the figure the customer sees.
 * iyzico settles in lira and used to convert at today's stored rate instead,
 * so a customer billed ₺400.00 was charged ₺420.00 once the rate had moved.
 */

function liraShop(float $todaysRate = 42.0): void
{
    foreach (['active' => '1', 'api_key' => 'k', 'secret_key' => 's', 'sandbox' => '1', 'installments' => '1', 'save_cards' => '0'] as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'iyzico', 'setting' => $k], ['value' => $v]);
    }
    Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '₺', 'suffix' => '', 'rate' => $todaysRate, 'is_default' => false]);
    app()->forgetInstance('pnlcs.currency');
}

function liraInvoice(bool $billedInLira = true, float $total = 10.0): Invoice
{
    $client = Client::factory()->create(['country' => 'TR', 'city' => 'Istanbul', 'address1' => '1 Test', 'tax_id' => '11111111111', 'phone_number' => '05401112233']);

    return Invoice::factory()->create(array_merge(
        ['client_id' => $client->id, 'status' => 'unpaid', 'total' => $total, 'source_currency' => 'EUR'],
        $billedInLira ? ['billing_currency' => 'TRY', 'exchange_rate' => 40] : ['billing_currency' => null, 'exchange_rate' => null]
    ));
}

test('an invoice billed in lira is charged the lira on the invoice, not today\'s conversion', function () {
    liraShop(42.0);
    Http::fake(['*' => Http::response(['status' => 'success', 'token' => 't1'], 200)]);

    (new IyzicoModule)->capture(liraInvoice(), 10.0);

    Http::assertSent(fn ($r) => $r['price'] === '400.00' && $r['paidPrice'] === '400.00' && $r['currency'] === 'TRY');
    $log = json_decode(GatewayLog::latest('id')->first()->data, true);
    expect($log['try_amount'])->toEqual(400.0)->and($log['due_amount'])->toEqual(10.0)->and($log['rate'])->toEqual(40.0);
});

test('an invoice not billed in lira still converts at today\'s rate', function () {
    liraShop(42.0);
    Http::fake(['*' => Http::response(['status' => 'success', 'token' => 't1'], 200)]);

    (new IyzicoModule)->capture(liraInvoice(false), 10.0);

    Http::assertSent(fn ($r) => $r['price'] === '420.00');
});

test('a stored card is charged the lira on the invoice too, even with no lira rate stored', function () {
    liraShop(42.0);
    Currency::where('code', 'TRY')->update(['rate' => 0]);
    $invoice = liraInvoice();
    $card = PaymentMethod::create([
        'client_id' => $invoice->client_id, 'gateway_name' => 'iyzico', 'payment_type' => 'card', 'description' => 'iyzico VISA',
        'remote_token' => json_encode(['cardUserKey' => 'cuk_1', 'cardToken' => 'ctk_1']), 'last_four' => '4242', 'status' => PaymentMethod::STATUS_ACTIVE,
    ]);
    Http::fake(['*/payment/auth' => Http::response(['status' => 'success', 'paymentId' => 'p1', 'paidPrice' => 400.0], 200)]);

    $result = (new IyzicoModule)->chargeStoredMethod($invoice, $card, 10.0, ['replay' => false, 'idempotency_key' => 'pnlcs-offsession-lira']);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payment/auth') && $r['price'] === '400.00');
    expect($result['success'] ?? false)->toBeTrue();
});

test('refunding what was paid gives back the lira that were taken', function (float $asked, float $expected) {
    liraShop(42.0);
    $invoice = liraInvoice();
    Transaction::create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'gateway' => 'iyzico', 'transaction_id' => 'pay_1', 'amount_in' => 10.0, 'date' => now()]);
    Http::fakeSequence()
        ->push(['status' => 'success', 'paidPrice' => 400.0, 'currency' => 'TRY', 'itemTransactions' => [['paymentTransactionId' => 'ptx_1']]], 200)
        ->push(['status' => 'success', 'paymentId' => 'r1'], 200);

    $result = (new IyzicoModule)->refund('pay_1', $asked);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payment/refund') && $r['price'] === number_format($expected, 2, '.', ''));
    expect($result['amount'])->toEqual($expected);
})->with([
    'in full' => [10.0, 400.0],
    'a part, at the invoice\'s rate' => [2.5, 100.0],
]);

test('a payment taken at the stored rate before this change is refunded what it took', function (float $asked, float $expected) {
    liraShop(42.0);
    $invoice = liraInvoice();
    // Charged at the stored rate (42) although the invoice says 40: ₺420 for €10.
    Transaction::create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'gateway' => 'iyzico', 'transaction_id' => 'pay_old', 'amount_in' => 10.0, 'date' => now()]);
    Http::fakeSequence()
        ->push(['status' => 'success', 'paidPrice' => 420.0, 'currency' => 'TRY', 'itemTransactions' => [['paymentTransactionId' => 'ptx_1']]], 200)
        ->push(['status' => 'success', 'paymentId' => 'r1'], 200);

    $result = (new IyzicoModule)->refund('pay_old', $asked);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payment/refund') && $r['price'] === number_format($expected, 2, '.', ''));
    expect($result['amount'])->toEqual($expected);
})->with([
    'in full' => [10.0, 420.0],
    'a part, in proportion' => [2.5, 105.0],
]);

test('a second part refund still converts at the rate the payment was taken at', function () {
    liraShop(42.0);
    $invoice = liraInvoice();
    $payment = Transaction::create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'gateway' => 'iyzico', 'transaction_id' => 'pay_old', 'amount_in' => 10.0, 'date' => now()]);
    // The first part refund, written the way PaymentService writes it: iyzico
    // answers a refund with the payment's id, so it carries the same one.
    Transaction::create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'gateway' => 'iyzico', 'transaction_id' => 'pay_old', 'amount_in' => 0, 'amount_out' => 2.5, 'refund_id' => $payment->id, 'date' => now()]);
    Http::fakeSequence()
        ->push(['status' => 'success', 'paidPrice' => 420.0, 'currency' => 'TRY', 'itemTransactions' => [['paymentTransactionId' => 'ptx_1']]], 200)
        ->push(['status' => 'success', 'paymentId' => 'pay_old'], 200);

    $result = (new IyzicoModule)->refund('pay_old', 2.5);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payment/refund') && $r['price'] === '105.00');
    expect($result['amount'])->toEqual(105.0);
});
