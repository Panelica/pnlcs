<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Upgrade;
use App\Models\User;
use App\Services\UpgradeService;

/*
 * The operator names the packages a product can move to.
 *
 * product_upgrade_paths existed from the start and nothing read or wrote it.
 * With paths set, only those packages are offered and allowed - and still
 * only of the service's own module. Without any, every package of the same
 * module stays allowed, so installs that never set paths keep their upgrades.
 */

function upProduct(?string $module, string $name, float $monthly, Currency $currency): Product
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

function upFixture(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $current = upProduct('panelica', 'Hosting Small', 10, $currency);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $current->id, 'status' => 'active',
        'amount' => 10, 'billing_cycle' => 'Monthly', 'next_due_date' => now()->addDays(20),
    ]);

    return [
        'user' => $user,
        'service' => $service,
        'current' => $current,
        'bigger' => upProduct('panelica', 'Hosting Large', 30, $currency),
        'reseller' => upProduct('panelica', 'Reseller Start', 50, $currency),
        'vps' => upProduct('proxmox', 'VPS Medium', 40, $currency),
    ];
}

it('offers and allows only the configured packages', function () {
    $fx = upFixture();
    $fx['current']->upgradeProducts()->sync([$fx['bigger']->id]);

    $this->actingAs($fx['user'])->get(route('client.services.upgrade', $fx['service']))
        ->assertOk()->assertSee('Hosting Large')->assertDontSee('Reseller Start');

    $this->actingAs($fx['user'])->post(route('client.services.upgrade.process', $fx['service']), [
        'new_product_id' => $fx['bigger']->id,
    ])->assertRedirect();
    expect(Upgrade::count())->toBe(1);
});

it('refuses a package outside the paths in requestProductChange itself', function () {
    $fx = upFixture();
    $fx['current']->upgradeProducts()->sync([$fx['bigger']->id]);

    // The API's UpgradeProduct and the client area both go through here.
    $result = app(UpgradeService::class)->requestProductChange($fx['service'], $fx['reseller']);

    expect($result['success'])->toBeFalse()
        ->and(Upgrade::count())->toBe(0);
});

it('falls back to every package of the same module when no paths are set', function () {
    $fx = upFixture();

    $this->actingAs($fx['user'])->get(route('client.services.upgrade', $fx['service']))
        ->assertOk()->assertSee('Hosting Large')->assertSee('Reseller Start')->assertDontSee('VPS Medium');
});

it('still refuses a path to another module', function () {
    $fx = upFixture();
    // Written straight to the table, as an old row or a module change would leave it.
    $fx['current']->upgradeProducts()->sync([$fx['vps']->id]);

    $result = app(UpgradeService::class)->requestProductChange($fx['service'], $fx['vps']);

    expect($result['success'])->toBeFalse();
    $this->actingAs($fx['user'])->get(route('client.services.upgrade', $fx['service']))
        ->assertOk()->assertDontSee('VPS Medium');
});

it('saves the paths from the product form, keeping only the same module', function () {
    $fx = upFixture();
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'Products', 'permissions' => ['manage_products', 'list_products']])->id]);
    $product = $fx['current'];

    $this->actingAs($admin, 'admin')->get(route('admin.products.edit', $product))
        ->assertOk()->assertSee('name="upgrade_paths[]"', false)
        ->assertSee('Hosting Large')->assertDontSee('VPS Medium');

    $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product), [
        'name' => $product->name, 'group_id' => $product->group_id, 'type' => 'hosting', 'pay_type' => 'recurring',
        'server_type' => 'panelica', 'upgrade_paths_section' => 1,
        'upgrade_paths' => [$fx['bigger']->id, $fx['vps']->id, $product->id],
    ])->assertSessionHasNoErrors();

    expect($product->upgradeProducts()->pluck('products.id')->all())->toBe([$fx['bigger']->id]);

    // Unselecting everything clears the list (back to the same-module rule).
    $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product), [
        'name' => $product->name, 'group_id' => $product->group_id, 'type' => 'hosting', 'pay_type' => 'recurring',
        'server_type' => 'panelica', 'upgrade_paths_section' => 1,
    ])->assertSessionHasNoErrors();
    expect($product->upgradeProducts()->count())->toBe(0);
});
