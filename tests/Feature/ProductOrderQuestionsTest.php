<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\CustomField;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;

/*
 * A product could not ask anything on its order form - an operating system,
 * a site to migrate, the domain a licence is for. custom_fields has always had
 * type and rel_id; only client fields were ever built.
 */

function poqAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['manage_products', 'view_services', 'manage_services', 'list_services']])->id]);
}

function poqProduct(): Product
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'name' => 'Licence', 'slug' => 'licence-'.uniqid(), 'hidden' => false, 'server_type' => null, 'tax' => false, 'pay_type' => 'recurring']);
    $currency = \App\Models\Currency::getDefault() ?? \App\Models\Currency::factory()->create(['is_default' => true]);
    \App\Models\Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id], ['annually' => 49]);

    return $product;
}

it('lets staff add a question to a product and remove it', function () {
    $product = poqProduct();
    $admin = poqAdmin();

    test()->actingAs($admin, 'admin')->post(route('admin.products.fields.store', $product), [
        'field_name' => 'Licensed domain', 'field_type' => 'text', 'required' => 1,
    ])->assertRedirect();
    $field = CustomField::productFields($product->id)->firstOrFail();
    expect($field)->field_name->toBe('Licensed domain')->required->toBeTrue();

    test()->actingAs($admin, 'admin')->get(route('admin.products.edit', $product))->assertOk()->assertSee('Licensed domain');
    test()->actingAs($admin, 'admin')->delete(route('admin.products.fields.destroy', [$product, $field]))->assertRedirect();
    expect(CustomField::productFields($product->id)->count())->toBe(0);
});

it('asks the question on the order form, requires an answer, and keeps it on the service', function () {
    $product = poqProduct();
    $question = CustomField::create(['type' => 'product', 'rel_id' => $product->id, 'field_name' => 'Licensed domain', 'field_type' => 'text', 'required' => true]);
    $staffNote = CustomField::create(['type' => 'product', 'rel_id' => $product->id, 'field_name' => 'Internal ref', 'field_type' => 'text', 'admin_only' => true]);

    $this->get(route('client.store.configure', $product))->assertOk()->assertSee('Licensed domain')->assertDontSee('Internal ref');

    $base = ['product_id' => $product->id, 'billing_cycle' => 'annually'];
    $this->post(route('client.cart.add'), $base)->assertSessionHasErrors('custom_fields.'.$question->id);
    $this->post(route('client.cart.add'), $base + ['custom_fields' => [$question->id => 'shop.example.test']])->assertRedirect(route('client.cart.index'));

    $client = Client::factory()->create(['tax_exempt' => true]);
    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id, 'domain' => '', 'amount' => 10, 'billing_cycle' => 'Annually',
        'custom_fields' => [$question->id => 'shop.example.test', $staffNote->id => 'should not come from the customer'],
    ]], 'banktransfer');
    $service = Service::where('order_id', $order->id)->firstOrFail();

    expect($question->valueFor($service->id))->toBe('shop.example.test')
        ->and($staffNote->valueFor($service->id))->toBeNull();
});

it('keeps the answer with the cart item', function () {
    $product = poqProduct();
    $question = CustomField::create(['type' => 'product', 'rel_id' => $product->id, 'field_name' => 'OS', 'field_type' => 'select', 'field_options' => "Debian\nUbuntu"]);
    $cart = app(CartService::class)->getOrCreateCart(null);
    app(CartService::class)->addProduct($cart, $product, 'annually', null, [], null, null, [], null, null, [$question->id => 'Ubuntu']);

    $items = collect(json_decode((string) $cart->fresh()->data, true)['items'] ?? []);
    expect($items->firstWhere('product_id', $product->id)['custom_fields'] ?? null)->toBe([$question->id => 'Ubuntu']);
});

it('shows the answers to staff, who can correct them, and to the customer', function () {
    $product = poqProduct();
    $question = CustomField::create(['type' => 'product', 'rel_id' => $product->id, 'field_name' => 'Licensed domain', 'field_type' => 'text']);
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $service = Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'status' => 'active', 'server_id' => null]);
    CustomField::storeValues($service->id, collect([$question]), [$question->id => 'old.example.test']);

    test()->actingAs($user)->get(route('client.services.show', $service))->assertOk()->assertSee('old.example.test');
    test()->actingAs(poqAdmin(), 'admin')->put(route('admin.services.fields', $service), ['custom_fields' => [$question->id => 'new.example.test']])->assertSessionHas('success');

    expect($question->valueFor($service->id))->toBe('new.example.test');
});
