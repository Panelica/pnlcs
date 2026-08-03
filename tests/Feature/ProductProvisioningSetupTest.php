<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\ServerGroup;

/**
 * Telling a product which panel creates the account.
 *
 * The create form asked for five of the twenty-six things a product has, and
 * how it is set up was not among them. The edit form asked for the module as
 * free text, so an operator had to know to type "cpanel" into an empty box.
 *
 * A product with no module is sold, paid for, activated - and nothing is
 * created on any server. That is what happened: an order was placed and paid,
 * the service went active with no server and no username, the module queue
 * stayed empty and no log line was written.
 */
function productAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('the create form offers the modules this installation has', function () {
    $html = $this->actingAs(productAdmin(), 'admin')
        ->get(route('admin.products.create'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('name="server_type"')
        ->and($html)->toContain('value="cpanel"')
        ->and($html)->toContain('name="auto_setup"')
        // Not a box to type a module name into.
        ->and($html)->not->toContain('<input type="text" name="server_type"');
});

test('a product can be given its module as it is created', function () {
    $group = ProductGroup::factory()->create();

    $this->actingAs(productAdmin(), 'admin')
        ->post(route('admin.products.store'), [
            'name' => 'Hosting Plan',
            'group_id' => $group->id,
            'type' => 'hosting',
            'pay_type' => 'recurring',
            'auto_setup' => 'payment',
            'server_type' => 'cpanel',
        ])->assertSessionHasNoErrors();

    $product = Product::where('name', 'Hosting Plan')->firstOrFail();

    expect($product->server_type)->toBe('cpanel')
        ->and($product->auto_setup)->toBe('payment');
});

test('a module this installation does not have is refused', function () {
    $group = ProductGroup::factory()->create();

    $this->actingAs(productAdmin(), 'admin')
        ->post(route('admin.products.store'), [
            'name' => 'Typo Plan',
            'group_id' => $group->id,
            'type' => 'hosting',
            'pay_type' => 'recurring',
            'server_type' => 'cpnael',
        ])->assertSessionHasErrors('server_type');

    expect(Product::where('name', 'Typo Plan')->exists())->toBeFalse();
});

test('the edit form shows the module the product is on', function () {
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'cpanel',
    ]);

    $this->actingAs(productAdmin(), 'admin')
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertSee('<option value="cpanel" selected>', false);
});

test('a product can be pointed at a server group', function () {
    $group = ProductGroup::factory()->create();
    $serverGroup = ServerGroup::create(['name' => 'Frankfurt', 'fill_type' => 'fill']);
    Server::factory()->create(['type' => 'cpanel', 'active' => true]);

    $this->actingAs(productAdmin(), 'admin')
        ->post(route('admin.products.store'), [
            'name' => 'Grouped Plan',
            'group_id' => $group->id,
            'type' => 'hosting',
            'pay_type' => 'recurring',
            'server_type' => 'cpanel',
            'server_group_id' => $serverGroup->id,
        ])->assertSessionHasNoErrors();

    expect((int) Product::where('name', 'Grouped Plan')->firstOrFail()->server_group_id)
        ->toBe($serverGroup->id);
});
