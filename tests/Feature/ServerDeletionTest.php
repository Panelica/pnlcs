<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Service;

/**
 * Deleting a server that still has accounts on it.
 *
 * The record went without a question asked, and services.server_id carries no
 * foreign key, so the services kept pointing at a row that no longer existed.
 * The module then resolves the server afresh and picks the first active one of
 * that type - a different machine - and stamps it onto the service. From then
 * on suspending or terminating that customer reaches for the wrong host, while
 * their hosting carries on running on the old one with nothing in the panel
 * pointing at it.
 *
 * Deleting a client already refuses while services are live. A server is the
 * same promise from the other end.
 */
function serverDeletingAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function serverWithService(string $status = 'active'): array
{
    $server = Server::factory()->create(['type' => 'cpanel', 'active' => true]);

    $service = Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id])->id,
        'server_id' => $server->id,
        'status' => $status,
        'domain' => 'still-hosted.example',
    ]);

    return [$server, $service];
}

test('a server with live accounts on it is not deleted', function () {
    [$server] = serverWithService('active');

    $this->actingAs(serverDeletingAdmin(), 'admin')
        ->delete(route('admin.config.servers.destroy', $server))
        ->assertRedirect();

    expect(Server::whereKey($server->id)->exists())->toBeTrue();
});

test('a suspended account still counts as live', function () {
    [$server] = serverWithService('suspended');

    $this->actingAs(serverDeletingAdmin(), 'admin')
        ->delete(route('admin.config.servers.destroy', $server));

    expect(Server::whereKey($server->id)->exists())->toBeTrue();
});

test('a server whose accounts are all gone can be deleted', function () {
    [$server] = serverWithService('terminated');

    $this->actingAs(serverDeletingAdmin(), 'admin')
        ->delete(route('admin.config.servers.destroy', $server));

    expect(Server::whereKey($server->id)->exists())->toBeFalse();
});

test('a group products are selling from is not deleted', function () {
    $group = ServerGroup::create(['name' => 'Frankfurt', 'fill_type' => 'fill']);

    Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_group_id' => $group->id,
    ]);

    $this->actingAs(serverDeletingAdmin(), 'admin')
        ->delete(route('admin.config.server-groups.destroy', $group));

    expect(ServerGroup::whereKey($group->id)->exists())->toBeTrue();
});

test('a group nothing points at can be deleted', function () {
    $group = ServerGroup::create(['name' => 'Unused', 'fill_type' => 'fill']);

    $this->actingAs(serverDeletingAdmin(), 'admin')
        ->delete(route('admin.config.server-groups.destroy', $group));

    expect(ServerGroup::whereKey($group->id)->exists())->toBeFalse();
});
