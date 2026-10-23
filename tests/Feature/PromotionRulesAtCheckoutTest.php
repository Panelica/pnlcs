<?php

use App\Models\Client;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\OrderService;

/**
 * The order is created before its invoice, and the code's rules are checked
 * on that invoice. The order just created counted as an earlier one: a
 * new-customers-only code never worked on a first order, a once-per-customer
 * code was already "used" by the order it came with, and an existing-customers
 * code let a brand-new customer through.
 */
function placeWithCode(Client $client, string $code): \App\Models\Order
{
    $product = Product::factory()->create(['tax' => false]);

    return app(OrderService::class)->processOrder($client, [
        ['type' => 'service', 'product_id' => $product->id, 'domain' => uniqid().'.example.com', 'amount' => 50.00, 'billing_cycle' => 'Monthly'],
    ], 'banktransfer', $code);
}

function orderDiscount(\App\Models\Order $order): float
{
    return (float) InvoiceItem::where('invoice_id', $order->invoice_id)->where('type', 'Discount')->sum('amount');
}

it('gives a new-customers-only code to a first order', function () {
    Promotion::factory()->create(['code' => 'WELCOME', 'type' => 'fixed', 'value' => 10, 'max_uses' => 0, 'uses' => 0, 'new_signups_only' => true]);
    $client = Client::factory()->create(['tax_exempt' => true]);

    $first = placeWithCode($client, 'WELCOME');
    $second = placeWithCode($client, 'WELCOME');

    expect(orderDiscount($first))->toBe(-10.0)
        ->and(orderDiscount($second))->toBe(0.0);
});

it('lets a once-per-customer code be used once', function () {
    Promotion::factory()->create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 5, 'max_uses' => 0, 'uses' => 0, 'apply_once' => true]);
    $client = Client::factory()->create(['tax_exempt' => true]);

    $first = placeWithCode($client, 'ONCE');
    $second = placeWithCode($client, 'ONCE');

    expect(orderDiscount($first))->toBe(-5.0)
        ->and(orderDiscount($second))->toBe(0.0);
});

it('refuses an existing-customers code on a first order', function () {
    Promotion::factory()->create(['code' => 'LOYAL', 'type' => 'fixed', 'value' => 5, 'max_uses' => 0, 'uses' => 0, 'existing_client' => true]);
    $client = Client::factory()->create(['tax_exempt' => true]);

    $first = placeWithCode($client, 'LOYAL');
    $second = placeWithCode($client, 'LOYAL');

    expect(orderDiscount($first))->toBe(0.0)
        ->and(orderDiscount($second))->toBe(-5.0);
});

it('does not keep a refused code on the order', function () {
    Promotion::factory()->create(['code' => 'LOYAL', 'type' => 'fixed', 'value' => 5, 'max_uses' => 0, 'uses' => 0, 'existing_client' => true]);
    $client = Client::factory()->create(['tax_exempt' => true]);

    $order = placeWithCode($client, 'LOYAL');

    expect($order->fresh()->promo_code)->toBeNull();
});
