<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Mail;

/*
 * A free product, ordered the way an operator sets it up and a customer buys
 * it (forum thread 270):
 *
 * - "Free" with the prices left as the product form starts them (-1 on every
 *   cycle, "not sold") had no cycle to offer: the store said "Contact us" and
 *   checkout refused the order for want of a billing cycle.
 * - Ordered at no charge, the invoice is paid when the order is placed, yet the
 *   customer was asked for a payment method, sent to the paid invoice and told
 *   "Please complete payment".
 * - A gateway switched on without its keys is left out of checkout, and the
 *   operator was not told why.
 * - The client page's unpaid total was red at zero, which read as a status.
 */

function freeCurrency(): Currency
{
    return Currency::firstOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
}

/** A product with one price row; $prices are cycle => value, every other cycle -1 as the form starts it. */
function freeProduct(string $payType = 'free', array $prices = [], array $over = []): Product
{
    $product = Product::factory()->create(array_merge([
        'group_id' => ProductGroup::factory()->create()->id,
        'pay_type' => $payType, 'hidden' => false, 'retired' => false, 'tax' => false,
        'show_domain_options' => false, 'server_type' => null, 'auto_setup' => 'payment',
    ], $over));
    $row = ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => freeCurrency()->id];
    foreach (['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'] as $cycle) {
        $row[$cycle] = $prices[$cycle] ?? -1;
    }
    Pricing::create($row);

    return $product->fresh();
}

function freeShopper(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create(['tax_exempt' => true])->id);

    return $user;
}

// --- the price ---------------------------------------------------------------

test('a free product with its prices left as the form starts them is sold monthly at no charge', function () {
    $product = freeProduct();

    expect($product->priceFor('monthly'))->toBe(0.0)
        ->and($product->priceFor('annually'))->toBeNull()
        ->and($product->pricedCycles())->toBe(['monthly' => 0.0]);
});

test('a free product with no price row at all is sold monthly at no charge', function () {
    freeCurrency();
    $product = Product::factory()->create(['pay_type' => 'free', 'server_type' => null]);

    expect($product->pricedCycles())->toBe(['monthly' => 0.0]);
});

test('a free product offered on chosen cycles keeps exactly those, as before', function () {
    $product = freeProduct('free', ['annually' => 0]);

    expect($product->pricedCycles())->toBe(['annually' => 0.0]);
});

test('a paid product that is sold on no cycle is still not sold', function () {
    $product = freeProduct('recurring');

    expect($product->pricedCycles())->toBe([])
        ->and($product->priceFor('monthly'))->toBeNull();
});

// --- what the customer sees ------------------------------------------------------

test('the store and the order page say Free for a free product', function () {
    $product = freeProduct();

    $store = $this->get(route('client.store'))->assertOk()->getContent();
    expect($store)->toContain($product->name)
        ->and($store)->toContain('<div class="pn-product-price">'.e(__('common.billing.free')).'</div>')
        ->and($store)->not->toContain('<div class="pn-product-price">'.e(__('client.store.contact_us')).'</div>');

    $this->get(route('client.store.configure', $product))->assertOk()
        ->assertSee('name="billing_cycle" value="monthly"', false)
        ->assertSee(e(__('common.billing.free')), false);
});

test('a free order is placed without a payment method and goes to the services, not to a payment', function () {
    Mail::fake();
    $user = freeShopper();
    $product = freeProduct();

    $this->actingAs($user)->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly'])
        ->assertSessionHasNoErrors();

    $checkout = $this->actingAs($user)->get(route('client.cart.checkout'))->assertOk()->getContent();
    expect($checkout)->toContain(e(__('client.checkout.nothing_to_pay')))
        ->and($checkout)->not->toContain('class="pn-pay-option');

    $methods = (fn () => $this->getAvailablePaymentMethods())->call(app(\App\Http\Controllers\Client\CartController::class));
    $response = $this->actingAs($user)->post(route('client.cart.process'), ['payment_method' => array_key_first($methods), 'terms' => '1']);

    $order = Order::latest('id')->firstOrFail();
    $response->assertRedirect(route('client.services.index'))
        ->assertSessionHas('success', __('messages.success.order_placed_free', ['num' => $order->order_num]));

    expect(strtolower((string) Invoice::find($order->invoice_id)->status))->toBe('paid')
        ->and(Service::where('order_id', $order->id)->value('status'))->toBe('active');
});

test('a free order whose product is set up by hand says so', function () {
    Mail::fake();
    $user = freeShopper();
    $product = freeProduct('free', [], ['auto_setup' => 'manual']);

    $this->actingAs($user)->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);
    $methods = (fn () => $this->getAvailablePaymentMethods())->call(app(\App\Http\Controllers\Client\CartController::class));
    $response = $this->actingAs($user)->post(route('client.cart.process'), ['payment_method' => array_key_first($methods), 'terms' => '1']);

    $order = Order::latest('id')->firstOrFail();
    $response->assertSessionHas('success', __('messages.success.order_placed_free_review', ['num' => $order->order_num]));
    expect(Service::where('order_id', $order->id)->value('status'))->toBe('pending');
});

test('a paid product brought to zero by a promotion still asks how its renewals will be paid', function () {
    $user = freeShopper();
    $product = freeProduct('recurring', ['monthly' => 10]);
    Promotion::create(['code' => 'ALLFREE', 'type' => 'percentage', 'value' => 100, 'recurring' => false]);

    $cart = app(CartService::class)->getOrCreateCart($user->clients()->first()->id);
    app(CartService::class)->addProduct($cart, $product, 'monthly', null);
    app(CartService::class)->applyPromoCode($cart, 'ALLFREE');

    $checkout = $this->actingAs($user)->get(route('client.cart.checkout'))->assertOk()->getContent();
    expect($checkout)->toContain('class="pn-pay-option')
        ->and($checkout)->not->toContain(e(__('client.checkout.nothing_to_pay')));
});

test('a test account settled without payment still lands on its invoice', function () {
    Mail::fake();
    $user = freeShopper();
    Setting::set('AutoApproveOrderEmails', $user->clients()->first()->email);
    $product = freeProduct('recurring', ['monthly' => 10]);

    $cart = app(CartService::class)->getOrCreateCart($user->clients()->first()->id);
    app(CartService::class)->addProduct($cart, $product, 'monthly', null);
    $methods = (fn () => $this->getAvailablePaymentMethods())->call(app(\App\Http\Controllers\Client\CartController::class));
    $response = $this->actingAs($user)->post(route('client.cart.process'), ['payment_method' => array_key_first($methods), 'terms' => '1']);

    $order = Order::latest('id')->firstOrFail();
    $response->assertRedirect(route('client.invoices.show', $order->invoice_id))
        ->assertSessionHas('success', __('messages.success.order_placed', ['num' => $order->order_num]));
});

// --- what the operator sees --------------------------------------------------------

function freeAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('a gateway switched on without its keys says it is not offered, and which fields are missing', function () {
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'active'], ['value' => '1']);
    $missing = app(ModuleRegistry::class)->missingGatewaySettings(app(ModuleRegistry::class)->getGatewayModule('stripe'), ['active' => '1']);
    expect($missing)->not->toBe([]);

    $page = $this->actingAs(freeAdmin(), 'admin')->get(route('admin.config.gateways'))->assertOk()->getContent();
    expect($page)->toContain(e(__('admin.gateways.not_offered')))
        ->and($page)->toContain(e(__('admin.gateways.missing_settings', ['fields' => implode(', ', $missing)])))
        ->and(app(ModuleRegistry::class)->usableGateways())->not->toContain('stripe');
});

test('a gateway with its keys, or one switched off, carries no warning', function () {
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'active'], ['value' => '1']);
    foreach (app(ModuleRegistry::class)->getGatewayModule('stripe')->getConfigFields() as $field) {
        if ($field['required'] ?? false) {
            GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $field['name']], ['value' => 'test-value']);
        }
    }

    $page = $this->actingAs(freeAdmin(), 'admin')->get(route('admin.config.gateways'))->assertOk()->getContent();
    expect($page)->not->toContain(e(__('admin.gateways.not_offered')))
        ->and(app(ModuleRegistry::class)->usableGateways())->toContain('stripe');
});

test('the client page shows the unpaid total in red only when something is owed', function () {
    $admin = freeAdmin();
    $client = Client::factory()->create();

    $page = $this->actingAs($admin, 'admin')->get(route('admin.clients.show', $client))->assertOk()->getContent();
    $redCard = '#<div class="stat-card"\s+style="border-color:\#d9534f;"\s*>#';
    expect($page)->toContain(__('admin.clients.unpaid_invoices'))
        ->and(preg_match($redCard, $page))->toBe(0);

    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => 25, 'total' => 25]);
    $page = $this->actingAs($admin, 'admin')->get(route('admin.clients.show', $client))->assertOk()->getContent();
    expect(preg_match($redCard, $page))->toBe(1);
});
