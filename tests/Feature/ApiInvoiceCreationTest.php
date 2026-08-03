<?php

use App\Events\InvoicePaid;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * Creating an invoice through the API.
 *
 * The endpoint took a customer and some dates and made an invoice with no
 * lines on it, for nothing, and there is no other endpoint that could add
 * one. An integration was handed an invoice id for a document that could
 * never be worth anything — and if it then marked it paid, it recorded a
 * payment of zero.
 */
function invoiceApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

test('an invoice created through the api has the lines it was given', function () {
    Mail::fake();

    $client = Client::factory()->create(['tax_exempt' => true]);

    $response = $this->withHeaders(invoiceApiHeaders())
        ->postJson('/api/v1/createinvoice', [
            'userid' => $client->id,
            'itemdescription1' => 'Consultancy',
            'itemamount1' => 150,
            'itemdescription2' => 'Setup',
            'itemamount2' => 50,
        ])->assertSuccessful();

    $body = $response->json();
    $invoice = Invoice::findOrFail($body['data']['invoiceid'] ?? $body['invoiceid']);

    expect($invoice->items)->toHaveCount(2)
        ->and((float) $invoice->subtotal)->toEqual(200.0)
        ->and((float) $invoice->total)->toEqual(200.0);
});

test('an invoice with no lines is refused rather than created empty', function () {
    Mail::fake();

    $client = Client::factory()->create();

    $this->withHeaders(invoiceApiHeaders())
        ->postJson('/api/v1/createinvoice', ['userid' => $client->id])
        ->assertStatus(422);

    expect(Invoice::where('client_id', $client->id)->count())->toBe(0);
});

test('the lines can be sent as an array too', function () {
    Mail::fake();

    $client = Client::factory()->create(['tax_exempt' => true]);

    $response = $this->withHeaders(invoiceApiHeaders())
        ->postJson('/api/v1/createinvoice', [
            'userid' => $client->id,
            'items' => [
                ['description' => 'Migration work', 'amount' => 80, 'taxed' => false],
            ],
        ])->assertSuccessful();

    $body = $response->json();
    $invoice = Invoice::findOrFail($body['data']['invoiceid'] ?? $body['invoiceid']);

    expect((float) $invoice->total)->toEqual(80.0)
        ->and($invoice->items->first()->description)->toBe('Migration work');
});

test('a line with no amount is refused', function () {
    Mail::fake();

    $client = Client::factory()->create();

    $this->withHeaders(invoiceApiHeaders())
        ->postJson('/api/v1/createinvoice', [
            'userid' => $client->id,
            'itemdescription1' => 'Something',
        ])->assertStatus(422);
});

/**
 * Recording a payment through the API.
 *
 * The endpoint wrote a transaction and flipped the invoice to paid by hand
 * instead of going through PaymentService, so none of what a payment sets off
 * happened: the same reference could be banked twice, an overpayment vanished
 * instead of becoming credit, and nothing listening for a paid invoice - a
 * suspended service waiting to come back, an order waiting to be provisioned -
 * ever heard about it.
 */
function apiPayableInvoice(float $total = 100.0): Invoice
{
    $client = Client::factory()->create();

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => $total,
        'total' => $total,
    ]);

    $invoice->items()->create([
        'client_id' => $client->id,
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => $total,
        'taxed' => false,
    ]);

    return $invoice;
}

test('a payment recorded through the api tells the rest of the system', function () {
    Mail::fake();
    Event::fake([InvoicePaid::class]);

    $invoice = apiPayableInvoice();

    $this->withHeaders(invoiceApiHeaders())
        ->postJson('/api/v1/addinvoicepayment', [
            'invoiceid' => $invoice->id,
            'transid' => 'API-TX-1',
            'amount' => 100,
            'gateway' => 'banktransfer',
        ])->assertSuccessful();

    expect(strtolower($invoice->fresh()->status))->toBe('paid');
    Event::assertDispatched(InvoicePaid::class);
});

test('the same payment reference is not recorded twice', function () {
    Mail::fake();

    $invoice = apiPayableInvoice();

    foreach ([1, 2] as $ignored) {
        $this->withHeaders(invoiceApiHeaders())
            ->postJson('/api/v1/addinvoicepayment', [
                'invoiceid' => $invoice->id,
                'transid' => 'API-TX-DUP',
                'amount' => 100,
                'gateway' => 'banktransfer',
            ])->assertSuccessful();
    }

    expect(Transaction::where('transaction_id', 'API-TX-DUP')->count())->toBe(1);
});

test('paying more than the invoice asks for leaves the rest as credit', function () {
    Mail::fake();

    $invoice = apiPayableInvoice();
    $client = $invoice->client;

    $this->withHeaders(invoiceApiHeaders())
        ->postJson('/api/v1/addinvoicepayment', [
            'invoiceid' => $invoice->id,
            'transid' => 'API-TX-OVER',
            'amount' => 130,
            'gateway' => 'banktransfer',
        ])->assertSuccessful();

    expect(strtolower($invoice->fresh()->status))->toBe('paid')
        ->and(round((float) $client->fresh()->credit, 2))->toBe(30.00);
});
