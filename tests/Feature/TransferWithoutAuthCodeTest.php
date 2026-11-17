<?php

use App\Contracts\RegistrarModuleInterface;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\User;
use App\Services\CartService;
use App\Services\Module\ModuleRegistry;
use App\Services\OrderService;

/*
 * Some registries move a name without an EPP/auth code: a .uk transfer is
 * approved with the registrar's IPS tag instead. Every transfer demanded a
 * code all the same, and the per-extension epp_code flag that says so sat
 * in the pricing table, read by nothing and missing from the form.
 */

function authCodeTlds(): Client
{
    Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'GBP', 'prefix' => '£', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    DomainPricing::updateOrCreate(['extension' => '.com'], [
        'register_price' => 12, 'transfer_price' => 10, 'renew_price' => 14, 'enabled' => true, 'epp_code' => true,
    ]);
    DomainPricing::updateOrCreate(['extension' => '.co.uk'], [
        'register_price' => 8, 'transfer_price' => 0, 'renew_price' => 8, 'enabled' => true, 'epp_code' => false,
    ]);

    return Client::factory()->create();
}

function authCodeUser(Client $client): User
{
    $user = User::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    return $user;
}

test('a transfer for an extension without codes goes into the cart without one', function () {
    $client = authCodeTlds();

    $this->actingAs(authCodeUser($client))
        ->post(route('client.cart.add-domain'), ['domain' => 'moving.co.uk', 'type' => 'transfer', 'years' => 1])
        ->assertSessionHasNoErrors();

    $cart = app(CartService::class);
    expect($cart->hasDomain($cart->getOrCreateCart($client->id), 'moving.co.uk'))->toBeTrue();
});

test('an extension that needs a code still asks for it', function () {
    $client = authCodeTlds();

    $this->actingAs(authCodeUser($client))
        ->post(route('client.cart.add-domain'), ['domain' => 'moving.com', 'type' => 'transfer', 'years' => 1])
        ->assertSessionHasErrors('epp_code');
});

test('the order step sends a code-less transfer to the registrar', function () {
    authCodeTlds();
    $sent = new ArrayObject;
    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('transfer')->andReturnUsing(function ($domain, $code) use ($sent) {
        $sent[] = $code;

        return ['success' => true, 'status' => 'pending_transfer'];
    });
    $fake->shouldReceive('getModuleName')->andReturn('ukfake');
    app()->instance('ukfake.module', $fake);
    app(ModuleRegistry::class)->registerRegistrar('ukfake', 'ukfake.module');

    $domain = Domain::create([
        'client_id' => Client::factory()->create()->id, 'domain' => 'moving.co.uk', 'type' => 'Transfer',
        'registrar' => 'ukfake', 'status' => 'pending', 'registration_period' => 1,
        'first_payment_amount' => 0, 'recurring_amount' => 8,
    ]);

    app(OrderService::class)->retryDomainRegistration($domain);

    expect($sent->getArrayCopy())->toBe([''])
        ->and($domain->fresh()->status)->toBe('pending_transfer');
});

test('the extension form sets and clears the flag', function () {
    authCodeTlds();
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $tld = DomainPricing::where('extension', '.com')->first();

    $this->actingAs($admin, 'admin')->put(route('admin.config.domain-pricing.update', $tld), [
        'extension' => '.com', 'register_price' => 12, 'transfer_price' => 10, 'renew_price' => 14,
        'enabled' => 1, 'epp_code' => 0,
    ])->assertSessionHasNoErrors();
    expect($tld->fresh()->epp_code)->toBeFalse();

    $html = $this->actingAs($admin, 'admin')->get(route('admin.config.domain-pricing'))->assertOk()->getContent();
    expect($html)->toContain('name="epp_code"');
});
