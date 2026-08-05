<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\InvoicePdfService;

/**
 * The second tax on the documents the customer is given.
 *
 * An invoice carries two taxes - places like Canada and India charge a federal
 * one and a regional one, and the tax screen has had a level for each all
 * along. The admin's own invoice page shows both. The customer's page and the
 * PDF showed only the first, so the lines the customer was given did not add
 * up to the total they were asked to pay.
 */
function twiceTaxedInvoice(): array
{
    $client = Client::factory()->create(['tax_exempt' => false]);
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => 100.00,
        'tax_rate' => 5,
        'tax' => 5.00,
        'tax_rate2' => 8,
        'tax2' => 8.00,
        'credit' => 0,
        'total' => 113.00,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => 100.00,
        'taxed' => true,
    ]);

    return [$user, $invoice];
}

it('shows the second tax on the customer invoice page', function () {
    [$user, $invoice] = twiceTaxedInvoice();

    $html = $this->actingAs($user)
        ->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(money_fmt(8.00));
});

it('shows the second tax on the pdf', function () {
    [, $invoice] = twiceTaxedInvoice();

    $html = view('pdf.invoice', [
        'invoice' => $invoice->fresh()->load('items', 'client'),
        'company' => app(InvoicePdfService::class)->companyDetails(),
    ])->render();

    expect($html)->toContain(money_fmt(8.00));
});
