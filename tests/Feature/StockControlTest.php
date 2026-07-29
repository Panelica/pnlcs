<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * Stock control.
 *
 * The columns have been in the products table from the start — stock_control
 * and stock_qty — with nothing reading them and no way to set them. A plan
 * limited to the four servers you have could be sold to any number of
 * customers, and you would find out when provisioning failed.
 */
function stockedProduct(bool $control, int $qty): Product
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create(['hidden' => false])->id,
        'name' => 'Limited Plan',
        'server_type' => null,
        'auto_setup' => 'payment',
        'tax' => false,
        'hidden' => false,
        'retired' => false,
        'stock_control' => $control,
        'stock_qty' => $qty,
    ]);

    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 30]
    );

    return $product;
}

function buyer(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

function placeOrder(User $user, Product $product): TestResponse
{
    test()->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
        'domain' => 'stock-test-'.uniqid().'.com',
    ]);

    return test()->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms' => '1',
    ]);
}

test('a product without stock control sells as many as you like', function () {
    Mail::fake();
    $product = stockedProduct(false, 0);

    placeOrder(buyer(), $product);
    placeOrder(buyer(), $product);
    placeOrder(buyer(), $product);

    expect(Order::count())->toBe(3);
});

test('the last one in stock can be sold', function () {
    Mail::fake();
    $product = stockedProduct(true, 1);

    placeOrder(buyer(), $product);

    expect(Order::count())->toBe(1)
        ->and($product->fresh()->stock_qty)->toBe(0);
});

test('there is no next one once it has gone', function () {
    Mail::fake();
    $product = stockedProduct(true, 1);

    placeOrder(buyer(), $product);
    placeOrder(buyer(), $product);

    expect(Order::count())->toBe(1);
});

test('a product out of stock cannot even go in the cart', function () {
    $product = stockedProduct(true, 0);

    $this->actingAs(buyer())->post(route('client.cart.add'), [
        'product_id' => $product->id,
        'billing_cycle' => 'monthly',
        'domain' => 'sold-out.com',
    ])->assertSessionHasErrors();
});

test('the shop says when something has sold out', function () {
    $soldOut = stockedProduct(true, 0);

    $this->actingAs(buyer())->get(route('client.store'))
        ->assertOk()
        ->assertSee('Limited Plan')
        ->assertSee(__('client.cart.out_of_stock'));
});

test('an operator can set the stock on the product screen', function () {
    $admin = Admin::factory()->create();
    $product = stockedProduct(false, 0);

    $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product), [
        'name' => $product->name,
        'group_id' => $product->group_id,
        'type' => 'hosting',
        'pay_type' => 'recurring',
        'stock_control' => '1',
        'stock_qty' => '7',
    ])->assertRedirect();

    $product->refresh();

    expect((bool) $product->stock_control)->toBeTrue()
        ->and($product->stock_qty)->toBe(7);
});
