<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\FreeSubdomains;
use Illuminate\Support\Facades\Mail;

/*
 * Free subdomains: a hosting order can take yourname.<one of the shop's domains>.
 * The names the shop keeps (www, mail, its own hosts) and the names already in
 * use are refused, whether the customer picks the free subdomain option or
 * types the name as their own domain.
 */

function subdomainProduct(): Product
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create(['hidden' => false])->id,
        'server_type' => null, 'auto_setup' => 'payment', 'tax' => false, 'hidden' => false, 'retired' => false,
        'show_domain_options' => true,
    ]);
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id], ['monthly' => 5]);

    return $product;
}

function subdomainBuyer(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

function addWithSubdomain(User $user, Product $product, string $label, string $parent = 'example-sites.com', string $option = 'subdomain')
{
    return test()->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $product->id, 'billing_cycle' => 'monthly',
        'domain_option' => $option, 'domain' => $label, 'subdomain_parent' => $parent,
    ]);
}

beforeEach(function () {
    Setting::set('FreeSubdomains', "example-sites.com\n.second-sites.net");
});

test('the parents are read from the setting, one per line, without a leading dot', function () {
    expect(FreeSubdomains::parents())->toBe(['example-sites.com', 'second-sites.net'])
        ->and(FreeSubdomains::enabled())->toBeTrue();

    Setting::set('FreeSubdomains', '');
    expect(FreeSubdomains::enabled())->toBeFalse();
});

test('a free name goes into the cart as the full name', function () {
    $product = subdomainProduct();

    addWithSubdomain(subdomainBuyer(), $product, 'Kafem')->assertSessionHasNoErrors();

    $items = json_decode(\App\Models\Cart::latest('id')->first()->data, true)['items'];
    expect($items[0]['domain'])->toBe('kafem.example-sites.com')
        ->and($items[0]['domain_option'])->toBe('subdomain')
        ->and((string) ($items[0]['notes'] ?? ''))->not->toContain('requested');
});

test('a reserved name is refused', function (string $label) {
    addWithSubdomain(subdomainBuyer(), subdomainProduct(), $label)
        ->assertSessionHasErrors(['domain' => __('client.cart.subdomain_reserved', ['name' => "$label.example-sites.com"])]);
})->with(['www', 'mail', 'admin', 'ns1', 'autodiscover']);

test('the operator\'s own list replaces the default one', function () {
    Setting::set('DisallowedSubdomainPrefixes', "shop, office");

    addWithSubdomain(subdomainBuyer(), subdomainProduct(), 'office')->assertSessionHasErrors('domain');
    addWithSubdomain(subdomainBuyer(), subdomainProduct(), 'www')->assertSessionHasNoErrors();
});

test('a name another service already has is refused, one that ended is free again', function () {
    $product = subdomainProduct();
    $service = Service::factory()->create(['domain' => 'kafem.example-sites.com', 'status' => 'active']);

    addWithSubdomain(subdomainBuyer(), $product, 'kafem')
        ->assertSessionHasErrors(['domain' => __('client.cart.subdomain_taken', ['name' => 'kafem.example-sites.com'])]);

    $service->update(['status' => 'terminated']);
    addWithSubdomain(subdomainBuyer(), $product, 'kafem')->assertSessionHasNoErrors();
});

test('names that are not one label, or under a domain not offered, are refused', function (string $label, string $parent) {
    addWithSubdomain(subdomainBuyer(), subdomainProduct(), $label, $parent)->assertSessionHasErrors('domain');
})->with([
    'two labels' => ['a.b', 'example-sites.com'],
    'leading hyphen' => ['-kafem', 'example-sites.com'],
    'not offered' => ['kafem', 'someone-else.com'],
]);

test('typing a reserved or taken name as your own domain is refused the same way', function () {
    addWithSubdomain(subdomainBuyer(), subdomainProduct(), 'mail.example-sites.com', '', 'own')
        ->assertSessionHasErrors(['domain' => __('client.cart.subdomain_reserved', ['name' => 'mail.example-sites.com'])]);

    Service::factory()->create(['domain' => 'kafem.example-sites.com', 'status' => 'active']);
    addWithSubdomain(subdomainBuyer(), subdomainProduct(), 'kafem.example-sites.com', '', 'own')
        ->assertSessionHasErrors(['domain' => __('client.cart.subdomain_taken', ['name' => 'kafem.example-sites.com'])]);

    // A domain of the customer's own is not touched by any of this.
    addWithSubdomain(subdomainBuyer(), subdomainProduct(), 'mail.kafem.com', '', 'own')->assertSessionHasNoErrors();
});

test('a name taken while it waited in the cart stops the order', function () {
    Mail::fake();
    $product = subdomainProduct();
    $user = subdomainBuyer();
    addWithSubdomain($user, $product, 'kafem')->assertSessionHasNoErrors();

    Service::factory()->create(['domain' => 'kafem.example-sites.com', 'status' => 'pending']);
    $orders = Order::count();

    $this->actingAs($user)->post(route('client.cart.process'), ['payment_method' => 'banktransfer', 'terms' => '1'])
        ->assertSessionHasErrors('domain');
    expect(Order::count())->toBe($orders);
});

test('the order form offers the option only when domains are set', function () {
    $product = subdomainProduct();

    $this->actingAs(subdomainBuyer())->get(route('client.store.configure', $product))
        ->assertOk()->assertSee('value="subdomain"', false)->assertSee('.example-sites.com');

    Setting::set('FreeSubdomains', '');
    $this->actingAs(subdomainBuyer())->get(route('client.store.configure', $product))
        ->assertOk()->assertDontSee('value="subdomain"', false);
});

test('staff set the domains and the reserved names on the general settings', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))
        ->assertOk()->assertSee('name="FreeSubdomains"', false)->assertSee('name="DisallowedSubdomainPrefixes"', false);
});
