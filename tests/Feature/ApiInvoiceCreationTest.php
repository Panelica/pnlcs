<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Invoice;
use Database\Factories\ApiCredentialFactory;
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
