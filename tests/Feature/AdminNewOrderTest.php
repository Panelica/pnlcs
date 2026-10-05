<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Support\Facades\Mail;

/*
 * Staff place an order for a customer.
 *
 * Orders could be accepted, cancelled and deleted from the admin area, but not
 * raised there: a sale agreed on the phone meant signing in as the customer or
 * using the API. "New order" in the Add New menu went to the order list.
 */

function noAdmin(array $permissions = ['list_orders', 'view_orders', 'manage_orders']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function noProduct(float $monthly = 10.0): Product
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'type' => 'other', 'server_type' => null, 'hidden' => false, 'retired' => false, 'tax' => false]);
    Pricing::where('rel_id', $product->id)->where('type', 'product')->delete();
    Pricing::create(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => \App\Models\Currency::getDefault()?->id ?? \App\Models\Currency::factory()->create(['is_default' => true])->id,
        'monthly' => $monthly, 'quarterly' => -1, 'semiannually' => -1, 'annually' => -1, 'biennially' => -1, 'triennially' => -1]);

    return $product;
}

beforeEach(function () {
    Mail::fake();
    GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'active'], ['value' => '1']);
});

it('places an order at the product price, with its invoice', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = noProduct(12.5);

    test()->actingAs(noAdmin(), 'admin')->get(route('admin.orders.create', ['client' => $client->id]))
        ->assertOk()->assertSee($client->email)->assertSee($product->name);

    test()->actingAs(noAdmin(), 'admin')->post(route('admin.orders.store'), [
        'client' => $client->email, 'product_id' => $product->id, 'billing_cycle' => 'monthly',
        'domain' => 'licence-7', 'payment_method' => 'banktransfer',
    ])->assertRedirect();

    $order = Order::where('client_id', $client->id)->latest('id')->firstOrFail();
    expect($order->status)->toBe('pending')
        ->and((float) $order->invoice->total)->toBe(12.5)
        ->and($order->services()->first()->product_id)->toBe($product->id);
});

it('takes a price set by staff, and accepts the order when asked', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = noProduct(12.5);

    test()->actingAs(noAdmin(), 'admin')->post(route('admin.orders.store'), [
        'client' => (string) $client->id, 'product_id' => $product->id, 'billing_cycle' => 'monthly',
        'price' => '7', 'payment_method' => 'banktransfer', 'accept' => '1',
    ])->assertRedirect();

    $order = Order::where('client_id', $client->id)->latest('id')->firstOrFail();
    expect((float) $order->invoice->total)->toBe(7.0)->and($order->fresh()->status)->toBe('active');
});

it('refuses a cycle the product is not sold on, an unknown customer, and a gateway that is not set up', function () {
    $client = Client::factory()->create();
    $product = noProduct();
    $admin = noAdmin();

    test()->actingAs($admin, 'admin')->post(route('admin.orders.store'), ['client' => (string) $client->id, 'product_id' => $product->id, 'billing_cycle' => 'annually', 'payment_method' => 'banktransfer'])
        ->assertSessionHasErrors('billing_cycle');
    test()->actingAs($admin, 'admin')->post(route('admin.orders.store'), ['client' => 'nobody@example.test', 'product_id' => $product->id, 'billing_cycle' => 'monthly', 'payment_method' => 'banktransfer'])
        ->assertSessionHasErrors('client');
    test()->actingAs($admin, 'admin')->post(route('admin.orders.store'), ['client' => (string) $client->id, 'product_id' => $product->id, 'billing_cycle' => 'monthly', 'payment_method' => 'nogateway'])
        ->assertSessionHasErrors('payment_method');
    expect(Order::where('client_id', $client->id)->exists())->toBeFalse();
});

it('is for staff who manage orders, and the menu leads to it', function () {
    test()->actingAs(noAdmin(['list_orders', 'view_orders']), 'admin')->get(route('admin.orders.create'))->assertForbidden();

    $html = test()->actingAs(noAdmin(), 'admin')->get(route('admin.orders.index'))->assertOk()->getContent();
    expect($html)->toContain('href="'.route('admin.orders.create').'"');
});

it('books the shop price for a customer whose account is in another currency', function () {
    $product = noProduct(12.5);
    $other = \App\Models\Currency::factory()->create(['code' => 'PLN', 'is_default' => false, 'rate' => 4]);
    Pricing::create(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $other->id,
        'monthly' => 49.99, 'quarterly' => -1, 'semiannually' => -1, 'annually' => -1, 'biennially' => -1, 'triennially' => -1]);
    $client = Client::factory()->create(['tax_exempt' => true, 'currency_id' => $other->id]);

    test()->actingAs(noAdmin(), 'admin')->post(route('admin.orders.store'), [
        'client' => (string) $client->id, 'product_id' => $product->id, 'billing_cycle' => 'monthly', 'payment_method' => 'banktransfer',
    ])->assertRedirect();

    $order = Order::where('client_id', $client->id)->latest('id')->firstOrFail();
    expect((float) $order->invoice->total)->toBe(12.5)
        ->and((float) $order->services()->first()->amount)->toBe(12.5);
});

it('still prices a customer in another currency when that currency has no price set', function () {
    $product = noProduct(12.5);
    $other = \App\Models\Currency::factory()->create(['code' => 'GBP', 'is_default' => false, 'rate' => 0.8]);
    Pricing::create(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $other->id,
        'monthly' => -1, 'quarterly' => -1, 'semiannually' => -1, 'annually' => -1, 'biennially' => -1, 'triennially' => -1]);
    $client = Client::factory()->create(['tax_exempt' => true, 'currency_id' => $other->id]);

    test()->actingAs(noAdmin(), 'admin')->post(route('admin.orders.store'), [
        'client' => (string) $client->id, 'product_id' => $product->id, 'billing_cycle' => 'monthly', 'payment_method' => 'banktransfer',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect((float) Order::where('client_id', $client->id)->latest('id')->firstOrFail()->invoice->total)->toBe(12.5);
});
