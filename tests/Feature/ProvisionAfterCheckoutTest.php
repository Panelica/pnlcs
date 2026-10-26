<?php

use App\Http\Controllers\Client\CartController;
use App\Jobs\ProvisionOrderedService;
use App\Models\Cart;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ProvisioningService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/*
 * A paid order built every one of its servers inside the request that paid
 * for it - the checkout, or the card payment's confirm - one after the other:
 * a Panelica account and its app, a Proxmox clone, resize and boot. On a live
 * install an order of three services kept the checkout spinning for one and a
 * half to three minutes. Accepting the order automatically now queues the
 * setup (ProvisionOrderedService); staff accepting by hand still wait for it
 * and see the result. A second press while the order is being placed does not
 * place it again.
 */

beforeEach(fn () => Mail::fake());

function pacProduct(array $over = []): Product
{
    $currency = Currency::firstOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $product = Product::factory()->create(array_merge([
        'group_id' => ProductGroup::factory()->create()->id, 'pay_type' => 'free', 'hidden' => false, 'retired' => false,
        'tax' => false, 'show_domain_options' => false, 'server_type' => null, 'auto_setup' => 'payment',
    ], $over));
    Pricing::create(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id,
        'monthly' => -1, 'quarterly' => -1, 'semiannually' => -1, 'annually' => -1, 'biennially' => -1, 'triennially' => -1]);

    return $product->fresh();
}

function pacShopper(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create(['tax_exempt' => true])->id);

    return $user;
}

test('a paid order is accepted at once and its servers are set up by a job', function () {
    Bus::fake([ProvisionOrderedService::class]);
    $user = pacShopper();
    $product = pacProduct(['server_type' => 'panelica']);

    $this->actingAs($user)->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly'])->assertSessionHasNoErrors();
    $methods = (fn () => $this->getAvailablePaymentMethods())->call(app(CartController::class));
    $response = $this->actingAs($user)->post(route('client.cart.process'), ['payment_method' => array_key_first($methods), 'terms' => '1']);

    $order = Order::latest('id')->firstOrFail();
    $service = Service::where('order_id', $order->id)->sole();
    $response->assertRedirect(route('client.services.index'))
        ->assertSessionHas('success', __('messages.success.order_placed_setting_up', ['num' => $order->order_num]));
    expect($service->status)->toBe('pending');
    Bus::assertDispatched(ProvisionOrderedService::class, fn ($job) => $job->serviceId === $service->id);
});

test('the job sets the service up once, and leaves alone one that is no longer pending', function () {
    $service = Service::factory()->create(['status' => 'pending', 'product_id' => Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'panelica'])->id]);
    $active = Service::factory()->create(['status' => 'active']);
    $orders = Mockery::mock(OrderService::class);
    $orders->shouldReceive('provisionOrderedService')->once()->withArgs(fn ($s) => $s->id === $service->id);

    (new ProvisionOrderedService($service->id))->handle($orders);
    (new ProvisionOrderedService($active->id))->handle($orders);
    (new ProvisionOrderedService(999999))->handle($orders);
});

test('staff accepting an order by hand still set it up right away', function () {
    Bus::fake([ProvisionOrderedService::class]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'panelica', 'auto_setup' => 'payment']);
    $order = Order::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'pending', 'order_num' => strtoupper(substr(md5(microtime()), 0, 8))]);
    $service = Service::factory()->create(['order_id' => $order->id, 'client_id' => $order->client_id, 'product_id' => $product->id, 'status' => 'pending']);
    $provisioning = Mockery::mock(ProvisioningService::class)->makePartial();
    $provisioning->shouldReceive('createAccount')->once()->andReturn(['success' => true, 'message' => 'ok']);
    app()->instance(ProvisioningService::class, $provisioning);

    app(OrderService::class)->acceptOrder($order, manual: true);

    Bus::assertNotDispatched(ProvisionOrderedService::class);
});

test('a second press while the order is being placed does not place it again', function () {
    $user = pacShopper();
    $product = pacProduct();
    $this->actingAs($user)->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);
    $cart = Cart::where('user_id', $user->clients()->first()->id)->firstOrFail();
    $methods = (fn () => $this->getAvailablePaymentMethods())->call(app(CartController::class));
    $before = Order::count();

    $held = Cache::lock('checkout-cart-'.$cart->id, 120);
    $held->get();
    $this->actingAs($user)->post(route('client.cart.process'), ['payment_method' => array_key_first($methods), 'terms' => '1'])
        ->assertRedirect(route('client.services.index'))->assertSessionHas('info', __('messages.info.order_already_placing'));
    $held->release();

    expect(Order::count())->toBe($before);
});
