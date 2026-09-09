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

it('quotes the scoped discount in the cart the way the invoice will charge it', function () {
    // The cart quoted the percentage off the whole basket while the invoice
    // took it off the covered product only: the customer saw 50 off and was
    // billed 5 off.
    $group = ProductGroup::factory()->create();
    $covered = Product::factory()->create(['group_id' => $group->id, 'name' => 'Covered', 'tax' => false]);
    $other = Product::factory()->create(['group_id' => $group->id, 'name' => 'Other', 'tax' => false]);
    \App\Models\Pricing::create(['type' => 'product', 'currency_id' => \App\Models\Currency::getDefault()?->id ?? \App\Models\Currency::factory()->create(['is_default' => true])->id, 'rel_id' => $covered->id, 'monthly' => 10]);
    \App\Models\Pricing::create(['type' => 'product', 'currency_id' => \App\Models\Currency::getDefault()->id, 'rel_id' => $other->id, 'monthly' => 90]);
    Promotion::create(['code' => 'HALF-A', 'type' => 'percentage', 'value' => 50, 'applies_to' => json_encode([$covered->id])]);

    $client = Client::factory()->create(['tax_exempt' => true]);
    $carts = app(\App\Services\CartService::class);
    $cart = $carts->getOrCreateCart($client->id);
    $carts->addProduct($cart, $covered, 'monthly', 'a.example.com');
    $carts->addProduct($cart, $other, 'monthly', 'b.example.com');
    $carts->applyPromoCode($cart, 'HALF-A');

    $quoted = $carts->calculateTotal($cart->fresh());
    $order = $carts->checkout($cart->fresh(), $client->id, 'banktransfer');

    expect($quoted['discount'])->toBe(5.0)
        ->and((float) $order->invoice->total)->toBe($quoted['total'])
        ->and((float) $order->invoice->total)->toBe(95.0);
});
