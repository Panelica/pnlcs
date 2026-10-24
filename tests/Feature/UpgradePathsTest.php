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

function upAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'Products '.uniqid(), 'permissions' => ['manage_products', 'list_products']])->id]);
}

function upForm(Product $product, array $over = []): array
{
    return array_merge([
        'name' => $product->name, 'group_id' => $product->group_id, 'type' => 'hosting', 'pay_type' => 'recurring',
        'server_type' => 'panelica', 'upgrade_paths_section' => 1,
    ], $over);
}

// --- #274: the list was a narrow multi-select that cut names off -------------------------

it('lists every package as a box under its group, across the card, the saved ones ticked', function () {
    $fx = upFixture();
    $fx['current']->upgradeProducts()->sync([$fx['bigger']->id]);
    $fx['reseller']->update(['name' => str_repeat('Reseller Start with a long name ', 4)]);

    $html = $this->actingAs(upAdmin(), 'admin')->get(route('admin.products.edit', $fx['current']))->assertOk()->getContent();

    preg_match('#<div id="upgrade-paths"[^>]*>(.*?)</div>\s*<div style="font-size:12px;color:\#777;margin-top:6px;">#s', $html, $list);
    expect($html)->not->toContain('<select name="upgrade_paths[]"')
        ->and($list[0] ?? '')->toContain('overflow-y:auto')
        ->and($list[0])->not->toContain('max-width')
        ->and($list[1])->toMatch('#<input type="checkbox" name="upgrade_paths\[\]" value="'.$fx['bigger']->id.'"[^>]*checked#')
        ->and($list[1])->toMatch('#<input type="checkbox" name="upgrade_paths\[\]" value="'.$fx['reseller']->id.'"(?![^>]*checked)[^>]*>#')
        ->and($list[1])->toContain(e($fx['reseller']->name))
        ->and($list[1])->toContain(e($fx['bigger']->group->name))
        ->and($list[1])->not->toContain('VPS Medium')
        ->and($html)->toContain(e(__('admin.products.upgrade_paths_hint')))
        ->and(__('admin.products.upgrade_paths_hint'))->not->toContain('Ctrl');
});

// --- #275: no way to keep customers on a package ------------------------------------------

it('lets a new product be moved from, and shows that ticked', function () {
    $fx = upFixture();

    expect($fx['current']->fresh()->allow_upgrades)->toBeTrue();
    $this->actingAs(upAdmin(), 'admin')->get(route('admin.products.edit', $fx['current']))->assertOk()
        ->assertSee('<input type="checkbox" name="allow_upgrades" value="1" id="allow-upgrades" checked', false);
});

it('keeps customers on a product that does not allow moving, everywhere they could ask', function () {
    $fx = upFixture();
    $admin = upAdmin();

    $this->actingAs($admin, 'admin')->put(route('admin.products.update', $fx['current']), upForm($fx['current'], [
        'allow_upgrades' => '0', 'upgrade_paths' => [$fx['bigger']->id],
    ]))->assertSessionHasNoErrors();

    $product = $fx['current']->fresh();
    expect($product->allow_upgrades)->toBeFalse()
        // The list is kept for when moving is allowed again.
        ->and($product->upgradeProducts()->pluck('products.id')->all())->toBe([$fx['bigger']->id]);

    // No link on the service, and no package on the page if opened anyway.
    $this->actingAs($fx['user'])->get(route('client.services.show', $fx['service']))->assertOk()
        ->assertDontSee(route('client.services.upgrade', $fx['service']), false);
    $this->actingAs($fx['user'])->get(route('client.services.upgrade', $fx['service']))->assertOk()
        ->assertDontSee('Hosting Large')->assertDontSee('Reseller Start');

    // Asked for regardless: from the client area and through the API.
    $this->actingAs($fx['user'])->post(route('client.services.upgrade.process', $fx['service']), ['new_product_id' => $fx['bigger']->id])
        ->assertSessionHas('error');
    $credential = \App\Models\ApiCredential::factory()->create();
    $this->withHeaders(['X-API-Key' => $credential->identifier, 'X-API-Secret' => \Database\Factories\ApiCredentialFactory::PLAINTEXT_SECRET, 'Accept' => 'application/json'])
        ->post('/api/v1/upgradeproduct', ['serviceid' => $fx['service']->id, 'packageid' => $fx['bigger']->id])
        ->assertStatus(422);
    expect(Upgrade::count())->toBe(0)
        ->and($fx['service']->fresh()->product_id)->toBe($fx['current']->id);

    // Allowed again: the kept list applies.
    $this->actingAs($admin, 'admin')->put(route('admin.products.update', $fx['current']), upForm($fx['current'], [
        'allow_upgrades' => '1', 'upgrade_paths' => [$fx['bigger']->id],
    ]))->assertSessionHasNoErrors();
    $this->actingAs($fx['user'])->get(route('client.services.show', $fx['service']))
        ->assertSee(route('client.services.upgrade', $fx['service']), false);
    $this->actingAs($fx['user'])->get(route('client.services.upgrade', $fx['service']))
        ->assertSee('Hosting Large')->assertDontSee('Reseller Start');
});

it('still offers the page when packages are closed but the product has options to raise', function () {
    $fx = upFixture();
    $fx['current']->update(['allow_upgrades' => false]);
    $group = \App\Models\ConfigOptionGroup::create(['name' => 'Resources']);
    \App\Models\ConfigOptionLink::create(['group_id' => $group->id, 'product_id' => $fx['current']->id]);
    \App\Models\ConfigOption::create(['group_id' => $group->id, 'option_name' => 'Extra RAM', 'option_type' => 'dropdown', 'sort_order' => 1]);

    $this->actingAs($fx['user'])->get(route('client.services.show', $fx['service']))->assertOk()
        ->assertSee(route('client.services.upgrade', $fx['service']), false);
});

it('leaves moving as it was when a form does not carry the switch', function () {
    $fx = upFixture();
    $fx['current']->update(['allow_upgrades' => false]);

    $this->actingAs(upAdmin(), 'admin')->put(route('admin.products.update', $fx['current']), upForm($fx['current']))
        ->assertSessionHasNoErrors();

    expect($fx['current']->fresh()->allow_upgrades)->toBeFalse();
});
