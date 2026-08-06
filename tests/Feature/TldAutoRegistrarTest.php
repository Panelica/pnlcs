<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Mail;

/**
 * The registrar the operator chose for a TLD.
 *
 * The TLD pricing screen has an "auto registrar" field. It is validated, it is
 * saved, and the list shows it - falling back to the word "Manual" when it is
 * empty, which tells the operator it means something.
 *
 * Nothing reads it. Every domain ordered through the shop is created as
 * Manual, so the TLD set up to register through eNom is marked active without
 * any registry hearing about it - now that ordering actually calls the
 * registrar, that is the difference between a registered domain and one that
 * only looks registered.
 */
function tldWithRegistrar(string $extension, ?string $registrar): DomainPricing
{
    return DomainPricing::updateOrCreate(
        ['extension' => $extension],
        [
            'register_price' => 10,
            'transfer_price' => 10,
            'renew_price' => 12,
            'min_years' => 1,
            'max_years' => 5,
            'auto_registrar' => $registrar,
            'enabled' => true,
        ]
    );
}

function domainOrderVia(string $extension, ?string $registrar): Domain
{
    tldWithRegistrar($extension, $registrar);

    $client = Client::factory()->create(['tax_exempt' => true]);

    $cartService = app(CartService::class);
    $cart = $cartService->getOrCreateCart($client->id);
    $cartService->addDomain($cart, 'ordered-through-shop'.$extension, 'register', 1);

    $order = $cartService->checkout($cart->fresh(), $client->id, 'banktransfer');

    return Domain::where('order_id', $order->id)->firstOrFail();
}

it('registers a domain through the registrar the tld is set up with', function () {
    Mail::fake();

    $domain = domainOrderVia('.shoptest', 'enom');

    expect(strtolower((string) $domain->registrar))->toBe('enom');
});

it('leaves a tld with no registrar to be done by hand', function () {
    Mail::fake();

    $domain = domainOrderVia('.manualtest', null);

    expect(strtolower((string) $domain->registrar))->toBe('manual');
});

it('lets an order that names its own registrar keep it', function () {
    Mail::fake();

    tldWithRegistrar('.overridden', 'enom');

    $client = Client::factory()->create(['tax_exempt' => true]);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'domain',
        'domain' => 'named-explicitly.overridden',
        'domain_type' => 'register',
        'registrar' => 'namecheap',
        'registration_period' => 1,
        'amount' => 10.00,
    ]], 'banktransfer');

    $domain = Domain::where('order_id', $order->id)->firstOrFail();

    expect(strtolower((string) $domain->registrar))->toBe('namecheap');
});
