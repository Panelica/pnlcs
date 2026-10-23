<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;

/*
 * #246: a product group could be created and never touched again - no rename,
 * no way to take it off the store, no delete.
 * #247: a product edit refused by validation (a Proxmox field) reloaded the
 * page with no word of why, which read as "the group cannot be changed"; most
 * admin pages never print the reasons a form was refused.
 * #248: "Add product/service" makes a ready-made invoice line, not a product,
 * and "New Hosting Product" makes every kind of product.
 */

function pgAdmin(array $permissions = []): Admin
{
    $role = $permissions === []
        ? AdminRole::factory()->fullAdmin()->create()
        : AdminRole::factory()->create(['name' => 'PG '.uniqid(), 'permissions' => $permissions]);

    return Admin::factory()->create(['role_id' => $role->id]);
}

function pgGroup(array $over = []): ProductGroup
{
    return ProductGroup::factory()->create(array_merge(['name' => 'Group '.uniqid(), 'hidden' => false], $over));
}

// --- #246 product groups -----------------------------------------------------------

test('a group is edited: renamed, given a headline and hidden, its address kept', function () {
    $group = pgGroup(['slug' => 'shared-hosting']);
    $admin = pgAdmin();

    $this->actingAs($admin, 'admin')->get(route('admin.products.groups.edit', $group))->assertOk()
        ->assertSee('value="'.e($group->name).'"', false)
        ->assertSee('name="hidden"', false);

    $this->actingAs($admin, 'admin')->put(route('admin.products.groups.update', $group), ['name' => 'Cloud Hosting', 'headline' => 'Fast', 'tagline' => '', 'hidden' => '1'])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success', __('admin.messages.product_group_updated'));

    $group->refresh();
    expect($group->name)->toBe('Cloud Hosting')
        ->and($group->headline)->toBe('Fast')
        ->and($group->hidden)->toBeTrue()
        ->and($group->slug)->toBe('shared-hosting');

    // Hidden is off the store; shown again when unticked.
    expect($this->get(route('client.store'))->getContent())->not->toContain('Cloud Hosting');
    $this->actingAs($admin, 'admin')->put(route('admin.products.groups.update', $group), ['name' => 'Cloud Hosting']);
    expect($group->fresh()->hidden)->toBeFalse();
});

test('an empty group is deleted', function () {
    $group = pgGroup();

    $this->actingAs(pgAdmin(), 'admin')->delete(route('admin.products.groups.destroy', $group))
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success', __('admin.messages.product_group_deleted'));

    expect(ProductGroup::find($group->id))->toBeNull();
});

test('a group with a product is not deleted, and neither is the product', function () {
    $group = pgGroup();
    $product = Product::factory()->create(['group_id' => $group->id]);

    $this->actingAs(pgAdmin(), 'admin')->delete(route('admin.products.groups.destroy', $group))
        ->assertSessionHas('error', __('admin.messages.product_group_not_empty', ['count' => 1]));

    expect(ProductGroup::find($group->id))->not->toBeNull()
        ->and(Product::find($product->id))->not->toBeNull();
});

test('a group whose deleted products no service names is deleted with them', function () {
    $group = pgGroup();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $product->delete();

    $this->actingAs(pgAdmin(), 'admin')->delete(route('admin.products.groups.destroy', $group))
        ->assertSessionHas('success', __('admin.messages.product_group_deleted'));

    expect(ProductGroup::find($group->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->toBeNull();
});

test('a group whose deleted product a past service names is kept, and the product with it', function () {
    $group = pgGroup();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $service = Service::factory()->create(['product_id' => $product->id, 'status' => 'terminated']);
    $product->delete();

    $this->actingAs(pgAdmin(), 'admin')->delete(route('admin.products.groups.destroy', $group))
        ->assertSessionHas('error', __('admin.messages.product_group_has_history'));

    expect(ProductGroup::find($group->id))->not->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull()
        ->and($service->fresh()->product()->withTrashed()->first())->not->toBeNull();
});

test('the products page offers Edit on every group and Delete only on an empty one', function () {
    $empty = pgGroup(['name' => 'Empty Group', 'hidden' => true]);
    $full = pgGroup(['name' => 'Full Group']);
    Product::factory()->create(['group_id' => $full->id]);

    $page = $this->actingAs(pgAdmin(), 'admin')->get(route('admin.products.index'))->assertOk()->getContent();

    expect($page)->toContain(route('admin.products.groups.edit', $empty))
        ->and($page)->toContain(route('admin.products.groups.edit', $full))
        ->and($page)->toContain(route('admin.products.groups.destroy', $empty))
        ->and($page)->not->toContain('action="'.route('admin.products.groups.destroy', $full).'"')
        ->and($page)->toContain(e(trans_choice('admin.products.group_products', 1, ['count' => 1])))
        ->and($page)->toContain(e(trans_choice('admin.products.group_products', 0, ['count' => 0])));
});

test('editing groups needs the permission to manage products', function () {
    $group = pgGroup();
    $staff = pgAdmin(['list_products']);

    $this->actingAs($staff, 'admin')->get(route('admin.products.groups.edit', $group))->assertForbidden();
    $this->actingAs($staff, 'admin')->put(route('admin.products.groups.update', $group), ['name' => 'X'])->assertForbidden();
    $this->actingAs($staff, 'admin')->delete(route('admin.products.groups.destroy', $group))->assertForbidden();

    expect(ProductGroup::find($group->id)->name)->not->toBe('X');
});

// --- #247 the reasons a form was refused ---------------------------------------------

function pgProxmoxProduct(ProductGroup $group): Product
{
    Server::create(['name' => 'PVE', 'hostname' => 'pve.example.test', 'ip_address' => '192.0.2.40', 'type' => 'proxmox', 'port' => 8006, 'username' => 'u@pve!t', 'password' => 'x', 'access_hash' => 'y', 'active' => true]);

    return Product::factory()->create(['group_id' => $group->id, 'type' => 'vps', 'server_type' => 'proxmox', 'config_options' => [
        'pve_type' => 'qemu', 'pve_template' => '9000', 'pve_storage' => 'local-lvm', 'pve_bridge' => 'vmbr0',
        'pve_cores' => 1, 'pve_memory' => 1024, 'pve_disk' => 10, 'pve_ipv4' => 'dhcp',
    ]]);
}

function pgProxmoxForm(Product $product, ProductGroup $group, array $over = []): array
{
    return array_merge([
        'name' => $product->name, 'group_id' => $group->id, 'type' => 'vps', 'pay_type' => 'recurring', 'auto_setup' => 'payment',
        'server_type' => 'proxmox', 'pve_section' => '1', 'pve_type' => 'qemu', 'pve_template' => '9000', 'pve_storage' => 'local-lvm',
        'pve_bridge' => 'vmbr0', 'pve_cores' => 1, 'pve_memory' => 1024, 'pve_disk' => 10, 'pve_ipv4' => 'dhcp',
    ], $over);
}

test('a Proxmox product moves to another group', function () {
    $a = pgGroup();
    $b = pgGroup();
    $product = pgProxmoxProduct($a);

    $this->actingAs(pgAdmin(), 'admin')->put(route('admin.products.update', $product), pgProxmoxForm($product, $b))
        ->assertSessionHas('success');

    expect($product->fresh()->group_id)->toBe($b->id);
});

test('a product edit that is refused says why on the page', function () {
    $a = pgGroup();
    $b = pgGroup();
    $product = pgProxmoxProduct($a);
    $admin = pgAdmin();

    $this->actingAs($admin, 'admin')->from(route('admin.products.edit', $product))
        ->put(route('admin.products.update', $product), pgProxmoxForm($product, $b, ['pve_template' => '']))
        ->assertRedirect(route('admin.products.edit', $product));

    expect($product->fresh()->group_id)->toBe($a->id);

    $page = $this->actingAs($admin, 'admin')->followingRedirects()->from(route('admin.products.edit', $product))
        ->put(route('admin.products.update', $product), pgProxmoxForm($product, $b, ['pve_template' => '']))
        ->assertOk()->getContent();
    expect($page)->toContain(e(__('proxmox.error.no_template')))
        ->and($page)->toContain('class="alert alert-danger" role="alert"');
});

test('a page that prints its own errors does not get them twice', function () {
    $page = $this->actingAs(pgAdmin(), 'admin')->followingRedirects()->from(route('admin.products.groups.create'))
        ->post(route('admin.products.groups.store'), ['name' => ''])
        ->assertOk()->getContent();

    $message = e(__('validation.required', ['attribute' => 'name']));
    expect(substr_count($page, $message))->toBe(1)
        ->and($page)->not->toContain('class="alert alert-danger" role="alert"');
});

// --- #248 what each button makes ----------------------------------------------------------

test('the buttons say what they make, and the invoice items say what they are for', function () {
    $page = $this->actingAs(pgAdmin(), 'admin')->get(route('admin.products.index'))->assertOk()->getContent();

    expect($page)->toContain(e(__('admin.products.new_product')))
        ->and(__('admin.products.new_product'))->toBe('New Product')
        ->and($page)->toContain(e(__('admin.products.add_product_service')))
        ->and(__('admin.products.add_product_service'))->toBe('Add Invoice Item')
        ->and($page)->toContain(e(__('admin.products.invoice_items_hint')));
});

test('a service that is not hosting is a product of type Other, offered when staff add a service to a client', function () {
    Currency::firstOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $admin = pgAdmin();

    $this->actingAs($admin, 'admin')->post(route('admin.products.store'), [
        'name' => 'Monitoring 24/7', 'group_id' => pgGroup()->id, 'type' => 'other', 'pay_type' => 'recurring', 'auto_setup' => 'payment',
    ])->assertSessionHasNoErrors();
    $product = Product::where('name', 'Monitoring 24/7')->firstOrFail();
    expect($product->server_type)->toBeNull();

    $client = Client::factory()->create();
    // The Services tab's Add Service form lists it.
    $this->actingAs($admin, 'admin')->get(route('admin.clients.show', [$client, 'tab' => 'services']))->assertOk()
        ->assertSee('>Monitoring 24/7</option>', false);
});
