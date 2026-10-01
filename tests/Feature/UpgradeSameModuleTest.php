<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Upgrade;
use App\Models\User;

/*
 * A service can only be moved onto a package its own module provisions.
 *
 * The upgrade screen offered every active product: a shared-hosting customer
 * could choose a VPS. The hosting module was then asked to apply a plan it
 * does not know, the change failed at the server, and billing moved the
 * service onto the VPS price regardless.
 */

function usmFixture(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $hosting = usmProduct('panelica', 'Hosting Small', 10, $currency);
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $hosting->id,
        'status' => 'active',
        'amount' => 10,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(20),
    ]);

    return [
        'user' => $user,
        'service' => $service,
        'bigger' => usmProduct('panelica', 'Hosting Large', 30, $currency),
        'vps' => usmProduct('proxmox', 'VPS Medium', 40, $currency),
    ];
}

function usmProduct(?string $module, string $name, float $monthly, Currency $currency): Product
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'name' => $name,
        'server_type' => $module,
        'tax' => false,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => $monthly]
    );

    return $product;
}

it('offers only packages provisioned by the same module', function () {
    $fx = usmFixture();

    $this->actingAs($fx['user'])->get(route('client.services.upgrade', $fx['service']))
        ->assertOk()
        ->assertSee('Hosting Large')
        ->assertDontSee('VPS Medium');
});

it('refuses a move onto a package another module provisions', function () {
    $fx = usmFixture();

    $this->actingAs($fx['user'])->post(route('client.services.upgrade.process', $fx['service']), [
        'new_product_id' => $fx['vps']->id,
    ])->assertRedirect();

    expect(Upgrade::count())->toBe(0)
        ->and($fx['service']->fresh()->product_id)->toBe($fx['service']->product_id);
});

it('still moves a service onto a package of the same module', function () {
    $fx = usmFixture();

    $this->actingAs($fx['user'])->post(route('client.services.upgrade.process', $fx['service']), [
        'new_product_id' => $fx['bigger']->id,
    ])->assertRedirect();

    expect(Upgrade::count())->toBe(1);
});
