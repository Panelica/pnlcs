<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\CartService;
use App\Services\Module\ModuleRegistry;

/**
 * Configuring a payment gateway, and being able to pay with it.
 *
 * The screen posted flat field names while the controller only read
 * settings[...], so nothing an operator typed was ever stored — the gateway
 * settings table was empty on a year-old installation. The field names it did
 * offer were not the ones the modules read either: PayPal wants a client id
 * and secret and was asked for an email address, Authorize.net reads
 * api_login_id and was given api_login.
 *
 * Then the invoice page looked for active gateways with a where() on a column
 * that is encrypted at rest, which cannot match, so it always fell back to
 * bank transfer; and the checkout offered a fixed list of three gateways
 * whatever was configured.
 */
function shopperWithCart(): User
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'hidden' => false,
        'retired' => false,
    ]);

    Pricing::create([
        'type' => 'product',
        'rel_id' => $product->id,
        'currency_id' => Currency::firstOrCreate(
            ['code' => 'USD'],
            ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]
        )->id,
        'monthly' => 10,
    ]);

    $cart = app(CartService::class)->getOrCreateCart($client->id);
    app(CartService::class)->addProduct($cart, $product, 'monthly', 'shop.example');

    return $user;
}

function gatewayAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Gateways',
            'permissions' => ['manage_gateways'],
        ])->id,
    ]);
}

test('the screen offers the fields each module actually reads', function () {
    $response = $this->actingAs(gatewayAdmin(), 'admin')
        ->get(route('admin.config.gateways'))
        ->assertOk();

    // PayPal authenticates with a client id and secret, not an email address.
    $response->assertSee('settings[client_id]', false)
        ->assertSee('settings[client_secret]', false)
        // Authorize.net reads api_login_id.
        ->assertSee('settings[api_login_id]', false)
        ->assertSee('settings[transaction_key]', false)
        // Razorpay reads key_id / key_secret.
        ->assertSee('settings[key_id]', false)
        ->assertSee('settings[key_secret]', false)
        // Stripe needs its webhook secret to verify callbacks.
        ->assertSee('settings[webhook_secret]', false)
        // Bank transfer takes international details.
        ->assertSee('settings[iban]', false)
        ->assertSee('settings[swift]', false);
});

test('what the operator types is saved', function () {
    $this->actingAs(gatewayAdmin(), 'admin')
        ->post(route('admin.config.gateways.settings.update', 'stripe'), [
            'active' => '1',
            'settings' => [
                'publishable_key' => 'pk_live_example',
                'secret_key' => 'sk_live_example',
                'webhook_secret' => 'whsec_example',
            ],
        ])->assertRedirect();

    $stored = GatewaySettings::where('gateway', 'stripe')->pluck('value', 'setting');

    expect($stored['secret_key'])->toBe('sk_live_example')
        ->and($stored['webhook_secret'])->toBe('whsec_example')
        ->and($stored['active'])->toBe('1');
});

test('the module reads back what was typed', function () {
    $this->actingAs(gatewayAdmin(), 'admin')
        ->post(route('admin.config.gateways.settings.update', 'authorize'), [
            'active' => '1',
            'settings' => ['api_login_id' => 'LOGIN123', 'transaction_key' => 'KEY456'],
        ])->assertRedirect();

    $module = app(ModuleRegistry::class)->getGatewayModule('authorize');

    $read = (new ReflectionClass($module))->getMethod('getSetting');
    $read->setAccessible(true);

    expect($read->invoke($module, 'api_login_id'))->toBe('LOGIN123');
});

test('unticking the box switches the gateway off', function () {
    $admin = gatewayAdmin();

    $this->actingAs($admin, 'admin')->post(route('admin.config.gateways.settings.update', 'stripe'), [
        'active' => '1',
        'settings' => ['secret_key' => 'sk_live_example'],
    ])->assertRedirect();

    $this->actingAs($admin, 'admin')->post(route('admin.config.gateways.settings.update', 'stripe'), [
        'settings' => ['secret_key' => 'sk_live_example'],
    ])->assertRedirect();

    expect(GatewaySettings::where('gateway', 'stripe')->where('setting', 'active')->first()->value)
        ->toBe('0');
});

test('an invoice can be paid with a gateway that is switched on', function () {
    $this->actingAs(gatewayAdmin(), 'admin')
        ->post(route('admin.config.gateways.settings.update', 'stripe'), [
            'active' => '1',
            'settings' => ['publishable_key' => 'pk_live_example', 'secret_key' => 'sk_live_example'],
        ])->assertRedirect();

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => 25,
    ]);

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->assertViewHas('gateways', fn ($gateways) => in_array('stripe', $gateways, true));
});

test('the checkout offers the gateways that are switched on', function () {
    $this->actingAs(gatewayAdmin(), 'admin')
        ->post(route('admin.config.gateways.settings.update', 'mollie'), [
            'active' => '1',
            'settings' => ['api_key' => 'test_example'],
        ])->assertRedirect();

    $user = shopperWithCart();

    $this->actingAs($user)->get(route('client.cart.checkout'))
        ->assertOk()
        ->assertViewHas('paymentMethods', fn ($methods) => array_key_exists('mollie', $methods)
            && ! array_key_exists('stripe', $methods));
});

test('with nothing configured the customer can still pay by bank transfer', function () {
    $user = shopperWithCart();

    $this->actingAs($user)->get(route('client.cart.checkout'))
        ->assertOk()
        ->assertViewHas('paymentMethods', fn ($methods) => array_keys($methods) === ['banktransfer']);
});
