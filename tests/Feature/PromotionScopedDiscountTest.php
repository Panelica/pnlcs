<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Service;
use App\Services\InvoiceGenerationService;

/**
 * A promotion restricted to particular products (applies_to) only used that
 * restriction as a door: once any covered product was in the order, the
 * discount was computed on the WHOLE subtotal. A "50% off product A" code on
 * a mixed invoice (A at 10, B at 90) took 50 off instead of 5 - the operator's
 * scoped promotion leaked onto everything else in the cart.
 */
function mixedInvoice(): array
{
    $group = ProductGroup::factory()->create();
    $covered = Product::factory()->create(['group_id' => $group->id, 'name' => 'Covered']);
    $other = Product::factory()->create(['group_id' => $group->id, 'name' => 'Other']);

    $client = Client::factory()->create();
    $serviceA = Service::factory()->create(['client_id' => $client->id, 'product_id' => $covered->id, 'status' => 'active']);
    $serviceB = Service::factory()->create(['client_id' => $client->id, 'product_id' => $other->id, 'status' => 'active']);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'unpaid',
        'subtotal' => 100, 'total' => 100, 'due_date' => now()->addDays(14),
    ]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Hosting', 'rel_id' => $serviceA->id, 'description' => 'Covered plan', 'amount' => 10, 'taxed' => false]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Hosting', 'rel_id' => $serviceB->id, 'description' => 'Other plan', 'amount' => 90, 'taxed' => false]);

    return [$invoice, $covered, $other];
}

function discountLine(Invoice $invoice): ?InvoiceItem
{
    return $invoice->fresh()->items()->where('type', 'Discount')->first();
}

it('discounts only the lines the promotion covers, not the whole invoice', function () {
    [$invoice, $covered] = mixedInvoice();
    Promotion::create(['code' => 'HALF-A', 'type' => 'percentage', 'value' => 50, 'applies_to' => json_encode([$covered->id])]);

    $applied = app(InvoiceGenerationService::class)->applyPromotion($invoice, 'HALF-A');

    expect($applied)->toBeTrue()
        ->and((float) discountLine($invoice)->amount)->toBe(-5.0);
});

it('caps a scoped fixed discount at the covered lines, not the invoice', function () {
    [$invoice, $covered] = mixedInvoice();
    Promotion::create(['code' => 'OFF-25', 'type' => 'fixed', 'value' => 25, 'applies_to' => json_encode([$covered->id])]);

    app(InvoiceGenerationService::class)->applyPromotion($invoice, 'OFF-25');

    // The covered line is 10; a 25 fixed code cannot take more off than that.
    expect((float) discountLine($invoice)->amount)->toBe(-10.0);
});

it('still discounts the whole invoice when the code covers everything', function () {
    [$invoice] = mixedInvoice();
    Promotion::create(['code' => 'ALL-10', 'type' => 'percentage', 'value' => 10, 'applies_to' => null]);

    app(InvoiceGenerationService::class)->applyPromotion($invoice, 'ALL-10');

    expect((float) discountLine($invoice)->amount)->toBe(-10.0);
});
