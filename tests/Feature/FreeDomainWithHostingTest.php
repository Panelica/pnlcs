<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Cart;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\InvoiceItem;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\CartService;
use App\Services\DomainAvailability;

/*
 * Hosting with a free domain - a standard offer - could not be sold: a domain
 * ordered with any product cost its full price. The product now says which
 * extensions are free and on which billing cycles; the first year of such a
 * domain is free and it renews at the normal price.
 */

function fdwFixture(array $product = []): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $client = Client::factory()->create(['tax_exempt' => true]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->clients()->attach($client->id);
    $p = Product::factory()->create(array_merge(['group_id' => ProductGroup::factory()->create()->id, 'type' => 'hosting',
        'show_domain_options' => true, 'tax' => false, 'free_domain' => true, 'free_domain_tlds' => '.com,.com.tr', 'free_domain_cycles' => 'annually'], $product));
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $p->id, 'currency_id' => $currency->id], ['monthly' => 20, 'annually' => 200]);
    DomainPricing::updateOrCreate(['extension' => '.com'], ['register_price' => 12, 'transfer_price' => 10, 'renew_price' => 14, 'min_years' => 1, 'max_years' => 10, 'enabled' => true]);
    DomainPricing::updateOrCreate(['extension' => '.net'], ['register_price' => 13, 'transfer_price' => 11, 'renew_price' => 15, 'min_years' => 1, 'max_years' => 10, 'enabled' => true]);
    app()->instance(DomainAvailability::class, new class extends DomainAvailability {
        public function __construct() {}

        public function check(string $domain): array
        {
            return ['domain' => $domain, 'available' => true, 'checked' => true];
        }
    });

    return compact('client', 'user', 'p');
}

function fdwDomainLine(Client $client): array
{
    $items = json_decode((string) Cart::where('user_id', $client->id)->first()?->data, true)['items'] ?? [];

    return collect($items)->firstWhere('type', 'domain') ?? [];
}

it('gives the first year free for a listed extension on a listed cycle, and renews at the normal price', function () {
    $fx = fdwFixture();

    test()->actingAs($fx['user'])->post(route('client.cart.add'), ['product_id' => $fx['p']->id, 'billing_cycle' => 'annually', 'domain' => 'free-shop.com', 'domain_option' => 'register'])
        ->assertSessionHasNoErrors();

    $line = fdwDomainLine($fx['client']);
    expect((float) $line['price'])->toBe(0.0)->and((float) $line['renewal_amount'])->toBe(14.0)->and($line['free_with'])->toBe($fx['p']->id);

    $cart = app(CartService::class);
    $order = $cart->checkout($cart->getOrCreateCart($fx['client']->id), $fx['client']->id, 'banktransfer');
    $domain = Domain::where('order_id', $order->id)->firstOrFail();
    expect((float) InvoiceItem::where('invoice_id', $order->invoice_id)->where('type', 'Domain')->value('amount'))->toBe(0.0)
        ->and((float) $domain->recurring_amount)->toBe(14.0);
});

it('charges the domain on a cycle or an extension that is not listed', function () {
    $fx = fdwFixture();
    test()->actingAs($fx['user'])->post(route('client.cart.add'), ['product_id' => $fx['p']->id, 'billing_cycle' => 'monthly', 'domain' => 'monthly-shop.com', 'domain_option' => 'register']);
    expect((float) fdwDomainLine($fx['client'])['price'])->toBe(12.0);

    $fx2 = fdwFixture();
    test()->actingAs($fx2['user'])->post(route('client.cart.add'), ['product_id' => $fx2['p']->id, 'billing_cycle' => 'annually', 'domain' => 'other-shop.net', 'domain_option' => 'register']);
    expect((float) fdwDomainLine($fx2['client'])['price'])->toBe(13.0);
});

it('lets staff set it, and turn the domain question off, on the product page', function () {
    $fx = fdwFixture(['free_domain' => false, 'free_domain_tlds' => null, 'free_domain_cycles' => null]);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['manage_products']])->id]);
    $p = $fx['p'];

    test()->actingAs($admin, 'admin')->put(route('admin.products.update', $p), [
        'name' => $p->name, 'group_id' => $p->group_id, 'type' => 'hosting', 'pay_type' => 'recurring',
        'domain_section' => 1, 'show_domain_options' => 1, 'free_domain' => 1, 'free_domain_tlds' => 'COM, .com.tr', 'free_domain_cycles' => ['annually', 'biennially'],
    ])->assertSessionHasNoErrors();
    $p->refresh();
    expect($p->free_domain)->toBeTrue()->and($p->freeDomainTlds())->toBe(['.com', '.com.tr'])->and($p->freeDomainCycles())->toBe(['annually', 'biennially']);

    test()->actingAs($admin, 'admin')->put(route('admin.products.update', $p), [
        'name' => $p->name, 'group_id' => $p->group_id, 'type' => 'hosting', 'pay_type' => 'recurring', 'domain_section' => 1,
    ])->assertSessionHasNoErrors();
    expect($p->fresh()->show_domain_options)->toBeFalsy()->and($p->fresh()->free_domain)->toBeFalse();
});
