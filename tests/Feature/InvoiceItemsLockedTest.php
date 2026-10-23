<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;

/**
 * Line items on a paid, cancelled or refunded invoice could still be added,
 * edited and removed, and each change recalculated the total under the
 * payments and refunds already recorded against it.
 */
function closedInvoice(string $status): array
{
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => $status,
        'subtotal' => 20, 'total' => 20, 'due_date' => now()->addDays(14),
    ]);
    $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Other', 'description' => 'Work', 'qty' => 1, 'amount' => 20, 'taxed' => false]);

    return [$invoice, $item];
}

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('keeps the lines of a closed invoice as they are', function (string $status) {
    [$invoice, $item] = closedInvoice($status);

    $this->put(route('admin.invoices.items.update', [$invoice, $item]), ['description' => 'Changed', 'qty' => 3, 'amount' => 99])
        ->assertSessionHas('error', __('admin.invoices.items_locked'));
    $this->post(route('admin.invoices.items.store', $invoice), ['description' => 'Extra'])
        ->assertSessionHas('error');
    $this->delete(route('admin.invoices.items.destroy', [$invoice, $item]))
        ->assertSessionHas('error');

    expect($invoice->fresh()->items()->count())->toBe(1)
        ->and($item->fresh()->description)->toBe('Work')
        ->and((float) $invoice->fresh()->total)->toBe(20.0);
})->with(['paid', 'cancelled', 'refunded']);

it('still edits the lines of an open invoice', function () {
    [$invoice, $item] = closedInvoice('unpaid');

    $this->put(route('admin.invoices.items.update', [$invoice, $item]), ['description' => 'Changed', 'qty' => 2, 'amount' => 15])
        ->assertSessionHas('success');

    expect($item->fresh()->description)->toBe('Changed')
        ->and((float) $invoice->fresh()->total)->toBe(30.0);
});

it('hides the line controls on a paid invoice', function () {
    [$invoice] = closedInvoice('paid');

    $this->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee('action="'.route('admin.invoices.items.store', $invoice).'"', false)
        ->assertDontSee(__('admin.invoices.delete_item'));
});
