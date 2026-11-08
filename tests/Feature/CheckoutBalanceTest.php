<?php

use App\Http\Controllers\Client\CartController;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
 * The account balance is applied to the order invoice when it is created, so
 * the checkout shows it and what is left to pay. When the balance covers the
 * whole order no gateway is needed at all; a partial balance leaves the rest
 * to the chosen gateway.
 */

beforeEach(fn () => Mail::fake());

function balanceProduct(float $monthly = 100): Product
{
    $currency = Currency::firstOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'pay_type' => 'recurring', 'hidden' => false, 'retired' => false,
        'tax' => false, 'show_domain_options' => false, 'server_type' => null, 'auto_setup' => 'payment',
    ]);

    Pricing::create([
        'type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id,
        'monthly' => $monthly, 'quarterly' => -1, 'semiannually' => -1, 'annually' => -1, 'biennially' => -1, 'triennially' => -1,
    ]);

    return $product->fresh();
}

function balanceShopper(float $credit): User
{
    $client = Client::factory()->create(['tax_exempt' => true, 'credit' => $credit]);
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return $user;
}

function addToCart(User $user, Product $product): void
{
    test()->actingAs($user)
        ->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly'])
        ->assertSessionHasNoErrors();
}

test('the checkout shows the balance and the amount left to pay', function () {
    $user = balanceShopper(60);
    addToCart($user, balanceProduct(100));

    test()->actingAs($user)->get(route('client.cart.checkout'))
        ->assertOk()
        ->assertSee(__('client.checkout.account_balance'))
        ->assertSee(__('client.cart.amount_to_pay'));
});

test('an order covered by the balance is placed without a gateway', function () {
    $user = balanceShopper(150);
    addToCart($user, balanceProduct(100));

    $response = test()->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'credit',
        'terms' => '1',
    ]);

    $order = Order::latest('id')->firstOrFail();
    $invoice = Invoice::findOrFail($order->invoice_id);

    $response->assertRedirect(route('client.services.index'));

    expect(strtolower((string) $invoice->status))->toBe('paid')
        ->and((float) $invoice->credit)->toBe(100.0)
        ->and((float) $invoice->total)->toBe(0.0)
        ->and((float) $invoice->client->fresh()->credit)->toBe(50.0);
});

test('a partial balance is applied and the rest is left to the gateway', function () {
    $user = balanceShopper(40);
    addToCart($user, balanceProduct(100));

    $methods = (fn () => $this->getAvailablePaymentMethods())->call(app(CartController::class));

    test()->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => array_key_first($methods),
        'terms' => '1',
    ])->assertRedirect();

    $order = Order::latest('id')->firstOrFail();
    $invoice = Invoice::findOrFail($order->invoice_id);

    expect((float) $invoice->credit)->toBe(40.0)
        ->and((float) $invoice->total)->toBe(60.0)
        ->and(strtolower((string) $invoice->status))->toBe('unpaid')
        ->and((float) $invoice->client->fresh()->credit)->toBe(0.0);
});

test('the balance cannot be chosen for an order it does not cover', function () {
    $user = balanceShopper(10);
    addToCart($user, balanceProduct(100));

    test()->actingAs($user)->post(route('client.cart.process'), [
        'payment_method' => 'credit',
        'terms' => '1',
    ])->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
});
