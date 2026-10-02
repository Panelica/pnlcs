<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Cart;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Service;
use App\Services\CartService;
use App\Services\InvoiceGenerationService;

/*
 * A promotion that requires products: it only holds when every one of them is
 * in the basket, which is how a set ("hosting + SSL, 20% off") is sold.
 *
 * promotions.requires was stored and never read, so such a code went to
 * anyone buying just one of the products.
 */

function prqProducts(): array
{
    $group = ProductGroup::factory()->create();

    return [Product::factory()->create(['group_id' => $group->id, 'name' => 'Hosting']), Product::factory()->create(['group_id' => $group->id, 'name' => 'SSL'])];
}

function prqCart(array $productIds): Cart
{
    $cart = Cart::create(['session_id' => 'prq-'.uniqid(), 'data' => json_encode(['items' => array_map(fn ($id) => [
        'type' => 'product', 'product_id' => $id, 'product_name' => 'P'.$id, 'billing_cycle' => 'monthly', 'price' => 10.0, 'addons' => [], 'config_options' => [],
    ], $productIds)])]);

    return $cart;
}

it('accepts the code only when every required product is in the basket', function () {
    [$hosting, $ssl] = prqProducts();
    Promotion::create(['code' => 'SET20', 'type' => 'percentage', 'value' => 20, 'requires' => $hosting->id.','.$ssl->id]);
    $carts = app(CartService::class);

    expect($carts->applyPromoCode(prqCart([$hosting->id]), 'SET20')['success'])->toBeFalse()
        ->and($carts->applyPromoCode(prqCart([$hosting->id, $ssl->id]), 'SET20')['success'])->toBeTrue();
});

it('drops the discount when a required product is taken out of the basket', function () {
    [$hosting, $ssl] = prqProducts();
    Promotion::create(['code' => 'SET20', 'type' => 'percentage', 'value' => 20, 'requires' => $hosting->id.','.$ssl->id]);
    $carts = app(CartService::class);
    $cart = prqCart([$hosting->id, $ssl->id]);
    $carts->applyPromoCode($cart, 'SET20');
    expect($carts->calculateTotal($cart)['discount'])->toBeGreaterThan(0);

    $data = json_decode($cart->fresh()->data, true);
    array_pop($data['items']);
    $cart->update(['data' => json_encode($data)]);

    expect($carts->calculateTotal($cart->fresh())['discount'])->toEqual(0);
});

it('refuses the code at the order when the invoice lacks a required product', function () {
    [$hosting, $ssl] = prqProducts();
    Promotion::create(['code' => 'SET20', 'type' => 'percentage', 'value' => 20, 'requires' => $hosting->id.','.$ssl->id]);
    $client = Client::factory()->create();
    $service = Service::factory()->create(['client_id' => $client->id, 'product_id' => $hosting->id, 'status' => 'pending']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => 10, 'total' => 10, 'due_date' => now()->addDays(7)]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Hosting', 'rel_id' => $service->id, 'description' => 'Hosting', 'amount' => 10, 'taxed' => false]);

    expect(app(InvoiceGenerationService::class)->applyPromotion($invoice, 'SET20'))->toBeFalse();
});

it('is set from the promotions screen', function () {
    [$hosting, $ssl] = prqProducts();
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['manage_promotions']])->id]);

    test()->actingAs($admin, 'admin')->get(route('admin.config.promotions'))->assertOk()->assertSee('name="requires[]"', false);
    test()->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), [
        'code' => 'SET20', 'type' => 'percentage', 'value' => 20, 'requires' => [$hosting->id, $ssl->id],
    ])->assertSessionHas('success');

    expect(Promotion::where('code', 'SET20')->sole()->requiredProductIds())->toBe([$hosting->id, $ssl->id]);
});
