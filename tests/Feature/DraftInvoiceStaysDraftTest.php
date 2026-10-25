<?php

use App\Mail\InvoiceCreatedMail;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Mail;

/*
 * The API can create an invoice as a draft (createinvoice status=draft), the
 * way an integration prepares one before issuing it. The draft was treated as
 * issued: the customer was mailed "invoice created" with the PDF, their
 * balance was spent on it, and it stood in their list of invoices. It now waits
 * until updateinvoice publishes it (status=unpaid), and all three happen then.
 */

function dsdHeaders(): array
{
    return ['X-API-Key' => ApiCredential::factory()->create()->identifier, 'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET];
}

function dsdCustomer(float $credit = 0): array
{
    $client = Client::factory()->create(['tax_exempt' => true, 'credit' => $credit]);
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return [$client, $user];
}

test('a draft is not mailed, not paid from the balance, and not shown to the customer', function () {
    Mail::fake();
    [$client, $user] = dsdCustomer(credit: 30);

    $id = $this->withHeaders(dsdHeaders())->postJson('/api/v1/createinvoice', [
        'userid' => $client->id, 'status' => 'draft', 'itemdescription1' => 'Consultancy', 'itemamount1' => 100,
    ])->assertSuccessful()->json('invoiceid');
    $draft = Invoice::findOrFail($id);

    Mail::assertNotQueued(InvoiceCreatedMail::class);
    expect($draft->status)->toBe('draft')
        ->and((float) $draft->credit)->toBe(0.0)
        ->and((float) $client->fresh()->credit)->toBe(30.0);

    $this->actingAs($user)->get(route('client.invoices.index'))->assertOk()->assertDontSee($draft->invoice_num);
    $this->actingAs($user)->get(route('client.invoices.show', $draft))->assertNotFound();
    $this->actingAs($user)->get(route('client.invoices.pdf', $draft))->assertNotFound();
});

test('publishing the draft issues it: mailed, the balance applied, listed', function () {
    Mail::fake();
    [$client, $user] = dsdCustomer(credit: 30);
    $headers = dsdHeaders();
    $id = $this->withHeaders($headers)->postJson('/api/v1/createinvoice', [
        'userid' => $client->id, 'status' => 'draft', 'itemdescription1' => 'Consultancy', 'itemamount1' => 100,
    ])->json('invoiceid');

    $this->withHeaders($headers)->postJson('/api/v1/updateinvoice', ['invoiceid' => $id, 'status' => 'unpaid'])->assertSuccessful();

    $invoice = Invoice::findOrFail($id);
    Mail::assertQueued(InvoiceCreatedMail::class, 1);
    expect($invoice->status)->toBe('unpaid')
        ->and((float) $invoice->credit)->toBe(30.0)
        ->and((float) $client->fresh()->credit)->toBe(0.0);
    $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk();
});

test('an invoice created unpaid is mailed and credited at once, as before', function () {
    Mail::fake();
    [$client] = dsdCustomer(credit: 10);

    $id = $this->withHeaders(dsdHeaders())->postJson('/api/v1/createinvoice', [
        'userid' => $client->id, 'itemdescription1' => 'Consultancy', 'itemamount1' => 100,
    ])->json('invoiceid');

    Mail::assertQueued(InvoiceCreatedMail::class, 1);
    expect((float) Invoice::findOrFail($id)->credit)->toBe(10.0);
});
