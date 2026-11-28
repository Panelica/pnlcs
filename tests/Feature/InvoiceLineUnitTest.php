<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
});

function invoiceWithUnitLine(): array
{
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'subtotal' => 150,
        'total' => 150,
        'due_date' => now()->addDays(14),
    ]);
    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Other',
        'description' => 'Wsparcie informatyczne',
        'qty' => 3,
        'amount' => 50,
        'taxed' => false,
        'unit' => 'godz.',
    ]);

    return [$invoice, $item];
}

it('shows the unit on an invoice line and saves an edited unit', function () {
    [$invoice, $item] = invoiceWithUnitLine();

    $this->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('name="unit"', false)
        ->assertSee('value="godz."', false);

    $this->put(route('admin.invoices.items.update', [$invoice, $item]), [
        'description' => 'Wsparcie informatyczne',
        'qty' => 3,
        'amount' => 50,
        'unit' => 'mies.',
    ])->assertSessionHas('success');

    expect($item->fresh()->unit)->toBe('mies.');
});

it('keeps the existing unit when an edit does not send one', function () {
    [$invoice, $item] = invoiceWithUnitLine();

    $this->put(route('admin.invoices.items.update', [$invoice, $item]), [
        'description' => 'Wsparcie informatyczne',
        'qty' => 3,
        'amount' => 50,
    ])->assertSessionHas('success');

    expect($item->fresh()->unit)->toBe('godz.');
});
