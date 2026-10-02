<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;

/*
 * WHOIS privacy priced per extension: not offered, free, or sold.
 *
 * There was no price for it anywhere and the order never asked: every domain
 * was registered without it, and an operator who sells it had no way to.
 */

function wppSetup(?float $privacyPrice): Client
{
    Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    DomainPricing::updateOrCreate(['extension' => '.com'], [
        'register_price' => 12, 'transfer_price' => 10, 'renew_price' => 14, 'privacy_price' => $privacyPrice,
        'min_years' => 1, 'max_years' => 10, 'enabled' => true,
    ]);

    return Client::factory()->create(['tax_exempt' => false]);
}

function wppItem($cart, $c): array
{
    return json_decode($c->fresh()->data, true)['items'][0];
}

it('offers nothing when the extension has no privacy price', function () {
    $client = wppSetup(null);
    $carts = app(CartService::class);
    $c = $carts->getOrCreateCart($client->id);
    $carts->addDomain($c, 'plain-shop.com', 'register', 1);

    expect(wppItem($carts, $c)['privacy'])->toBeFalse()
        ->and($carts->setDomainPrivacy($c->fresh(), 0, true))->toBeFalse();
});

it('turns free privacy on by default and records it on the domain', function () {
    Mail::fake();
    $client = wppSetup(0);
    $carts = app(CartService::class);
    $c = $carts->getOrCreateCart($client->id);
    $carts->addDomain($c, 'quiet-shop.com', 'register', 1);

    expect(wppItem($carts, $c)['privacy'])->toBeTrue()->and((float) wppItem($carts, $c)['price'])->toBe(12.0);

    $order = $carts->checkout($c->fresh(), $client->id, 'banktransfer');
    expect(Domain::where('order_id', $order->id)->sole()->id_protection)->toBeTrue();
});

it('adds paid privacy to the first payment and the renewals for every year', function () {
    Mail::fake();
    $client = wppSetup(3);
    $carts = app(CartService::class);
    $c = $carts->getOrCreateCart($client->id);
    $carts->addDomain($c, 'paid-shop.com', 'register', 2);

    expect(wppItem($carts, $c)['privacy'])->toBeFalse()->and((float) wppItem($carts, $c)['price'])->toBe(24.0);

    expect($carts->setDomainPrivacy($c->fresh(), 0, true))->toBeTrue();
    expect((float) wppItem($carts, $c)['price'])->toBe(30.0)->and((float) wppItem($carts, $c)['renewal_amount'])->toBe(34.0);

    $order = $carts->checkout($c->fresh(), $client->id, 'banktransfer');
    $domain = Domain::where('order_id', $order->id)->sole();
    $line = InvoiceItem::where('invoice_id', $order->invoice_id)->where('type', 'Domain')->sole();

    expect($domain->id_protection)->toBeTrue()
        ->and((float) $domain->recurring_amount)->toBe(34.0)
        ->and((float) $line->amount)->toBe(30.0)
        ->and($line->description)->toContain('WHOIS privacy');
});

it('drops paid privacy again', function () {
    $client = wppSetup(3);
    $carts = app(CartService::class);
    $c = $carts->getOrCreateCart($client->id);
    $carts->addDomain($c, 'paid-shop.com', 'register', 1);
    $carts->setDomainPrivacy($c->fresh(), 0, true);
    $carts->setDomainPrivacy($c->fresh(), 0, false);

    expect(wppItem($carts, $c)['privacy'])->toBeFalse()
        ->and((float) wppItem($carts, $c)['price'])->toBe(12.0)
        ->and((float) wppItem($carts, $c)['renewal_amount'])->toBe(14.0);
});

it('shows the option in the cart and switches it there', function () {
    wppSetup(3);
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);
    test()->actingAs($user)->post(route('client.cart.add-domain'), ['domain' => 'paid-shop.com', 'type' => 'register', 'years' => 1]);

    test()->actingAs($user)->get(route('client.cart.index'))->assertOk()->assertSee(route('client.cart.privacy', 0), false);
    test()->actingAs($user)->post(route('client.cart.privacy', 0), ['privacy' => '1'])->assertRedirect(route('client.cart.index'));
    test()->actingAs($user)->post(route('client.cart.privacy', 5), ['privacy' => '1'])->assertSessionHas('error');
});

it('is set per extension on the domain pricing screen', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['manage_servers']])->id]);
    $tld = DomainPricing::updateOrCreate(['extension' => '.net'], ['register_price' => 12, 'transfer_price' => 10, 'renew_price' => 14, 'enabled' => true]);

    test()->actingAs($admin, 'admin')->put(route('admin.config.domain-pricing.update', $tld), ['extension' => '.net', 'privacy_price' => '2.50'])->assertSessionHas('success');
    expect($tld->fresh()->privacy_price)->toBe(2.5);

    test()->actingAs($admin, 'admin')->put(route('admin.config.domain-pricing.update', $tld), ['extension' => '.net', 'privacy_price' => ''])->assertSessionHas('success');
    expect($tld->fresh()->privacy_price)->toBeNull();
});

it('asks the registrar for privacy when the order chose it', function () {
    $params = new ArrayObject;
    $fake = Mockery::mock(\App\Contracts\RegistrarModuleInterface::class);
    $fake->shouldReceive('register')->andReturnUsing(function ($domain, $years, $p = []) use ($params) {
        $params[$domain->domain] = $p;
        $domain->update(['status' => 'active']);

        return ['success' => true, 'message' => 'registered'];
    });
    $fake->shouldReceive('getModuleName')->andReturn('privacyreg');
    app()->instance(\App\Contracts\RegistrarModuleInterface::class, $fake);
    app(\App\Services\Module\ModuleRegistry::class)->registerRegistrar('privacyreg', \App\Contracts\RegistrarModuleInterface::class);

    $client = Client::factory()->create(['tax_exempt' => true]);
    $orders = app(\App\Services\OrderService::class);
    foreach (['hidden-shop.com' => true, 'open-shop.com' => false] as $name => $privacy) {
        $order = $orders->processOrder($client, [[
            'type' => 'domain', 'domain' => $name, 'amount' => 12.0, 'domain_type' => 'register',
            'registrar' => 'privacyreg', 'registration_period' => 1, 'id_protection' => $privacy,
        ]], 'banktransfer');
        $orders->acceptOrder($order);
    }

    expect($params['hidden-shop.com']['privacy'] ?? null)->toBeTrue()
        ->and($params['open-shop.com'])->not->toHaveKey('privacy');
});
