<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Mollie: the webhook posts a payment id, and the payment is read back from
 * Mollie. A paid payment is recorded once, under Mollie's id for it, whatever
 * the id posted looked like.
 */

beforeEach(function () {
    foreach (['api_key' => 'test_key', 'test_mode' => '1'] as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'mollie', 'setting' => $k], ['value' => $v]);
    }
});

function mwoInvoice(): Invoice
{
    return Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 100.0]);
}

/** Mollie knows one paid payment, tr_abc, of $value for $invoice; any other id is unknown. */
function mwoMollie(Invoice $invoice, string $value): void
{
    Http::fake(function (Request $request) use ($invoice, $value) {
        if (! str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/payments/tr_abc')) {
            return Http::response(['status' => 404, 'title' => 'Not Found'], 404);
        }

        return Http::response(['id' => 'tr_abc', 'status' => 'paid', 'amount' => ['currency' => shop_currency_code(), 'value' => $value],
            'metadata' => ['invoice_id' => $invoice->id]], 200);
    });
}

test('a paid payment posted again, however it is spelled, is recorded once', function () {
    $invoice = mwoInvoice();
    mwoMollie($invoice, '30.00');

    foreach (['tr_abc', 'tr_abc#1', 'tr_abc?x=1', 'tr_abc'] as $id) {
        $this->post('/gateway/mollie/webhook', ['id' => $id])->assertOk();
    }

    expect(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and(Transaction::where('invoice_id', $invoice->id)->value('transaction_id'))->toBe('tr_abc')
        ->and((float) Transaction::where('invoice_id', $invoice->id)->sum('amount_in'))->toBe(30.0)
        ->and($invoice->fresh()->status)->toBe('partially_paid');
});

test('the payment is still recorded once paid', function () {
    $invoice = mwoInvoice();
    mwoMollie($invoice, '100.00');

    $this->post('/gateway/mollie/webhook', ['id' => 'tr_abc'])->assertOk();

    expect($invoice->fresh()->status)->toBe('paid');
});
