<?php

use App\Mail\SslConfigurationRequiredMail;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\SslOrder;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Selling a certificate.
 *
 * A product can be marked as type ssl and given an SSL module, and the whole
 * certificate flow — configure, submit the CSR, validate, download — hangs off
 * an ssl_orders row. Nothing created that row when a customer bought and paid
 * for one: the only place in the codebase that did was an API endpoint. The
 * customer paid for a certificate that was never ordered and never asked for.
 */
function sslShop(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'name' => 'PositiveSSL',
        'slug' => 'positive-ssl',
        'type' => 'ssl',
        'ssl_module' => 'gogetssl',
        'server_type' => null,
        'auto_setup' => 'payment',
        'tax' => false,
        'hidden' => false,
        'retired' => false,
    ]);

    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 0, 'annually' => 45]
    );

    $user = User::factory()->create();
    $client = Client::factory()->create(['tax_exempt' => true]);
    $user->clients()->attach($client->id);

    return compact('product', 'client', 'user');
}

test('paying for a certificate actually orders one', function () {
    Mail::fake();
    $fx = sslShop();
    $admin = Admin::factory()->create();

    $this->actingAs($fx['user'])->post(route('client.cart.add'), [
        'product_id' => $fx['product']->id,
        'billing_cycle' => 'annually',
        'domain' => 'secure-me.com',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($fx['user'])->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer',
        'terms' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $order = Order::where('client_id', $fx['client']->id)->firstOrFail();

    // Nothing yet: it has not been paid for.
    expect(SslOrder::count())->toBe(0);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.invoices.mark-paid', Invoice::findOrFail($order->invoice_id)), ['gateway' => 'banktransfer'])
        ->assertRedirect();

    $service = Service::where('order_id', $order->id)->firstOrFail();
    $sslOrder = SslOrder::where('service_id', $service->id)->first();

    expect($sslOrder)->not->toBeNull()
        ->and($sslOrder->client_id)->toBe($fx['client']->id)
        ->and($sslOrder->module)->toBe('gogetssl')
        ->and($sslOrder->domain)->toBe('secure-me.com')
        ->and($sslOrder->status)->toBe('Awaiting Configuration');
});

test('the customer is told the certificate needs configuring', function () {
    Mail::fake();
    $fx = sslShop();
    $admin = Admin::factory()->create();

    $this->actingAs($fx['user'])->post(route('client.cart.add'), [
        'product_id' => $fx['product']->id,
        'billing_cycle' => 'annually',
        'domain' => 'tell-me.com',
    ])->assertSessionHasNoErrors();
    $this->actingAs($fx['user'])->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertSessionHasNoErrors();

    $order = Order::where('client_id', $fx['client']->id)->firstOrFail();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.invoices.mark-paid', Invoice::findOrFail($order->invoice_id)), ['gateway' => 'banktransfer'])
        ->assertRedirect();

    // A certificate cannot be issued until the customer supplies a CSR, so
    // they have to be asked for one.
    Mail::assertQueued(SslConfigurationRequiredMail::class);
});

test('the certificate shows up in the customer ssl list', function () {
    Mail::fake();
    $fx = sslShop();
    $admin = Admin::factory()->create();

    $this->actingAs($fx['user'])->post(route('client.cart.add'), [
        'product_id' => $fx['product']->id,
        'billing_cycle' => 'annually',
        'domain' => 'listed.com',
    ])->assertSessionHasNoErrors();
    $this->actingAs($fx['user'])->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertSessionHasNoErrors();

    $order = Order::where('client_id', $fx['client']->id)->firstOrFail();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.invoices.mark-paid', Invoice::findOrFail($order->invoice_id)), ['gateway' => 'banktransfer'])
        ->assertRedirect();

    $this->actingAs($fx['user'])->get(route('client.ssl.index'))
        ->assertOk()
        ->assertSee('listed.com');
});

test('an ordinary hosting product does not create a certificate order', function () {
    Mail::fake();
    $fx = sslShop();
    $admin = Admin::factory()->create();

    $hosting = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'type' => 'hostingaccount',
        'ssl_module' => null,
        'server_type' => null,
        'auto_setup' => 'payment',
        'tax' => false,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $hosting->id, 'currency_id' => Currency::where('is_default', true)->first()->id],
        ['monthly' => 15]
    );

    $this->actingAs($fx['user'])->post(route('client.cart.add'), [
        'product_id' => $hosting->id,
        'billing_cycle' => 'monthly',
        'domain' => 'plain-hosting.com',
    ])->assertSessionHasNoErrors();
    $this->actingAs($fx['user'])->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertSessionHasNoErrors();

    $order = Order::where('client_id', $fx['client']->id)->firstOrFail();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.invoices.mark-paid', Invoice::findOrFail($order->invoice_id)), ['gateway' => 'banktransfer'])
        ->assertRedirect();

    expect(SslOrder::count())->toBe(0);
});
