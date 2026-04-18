<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Server;
use App\Models\ServerGroup;


function makeServerAdmin(): Admin
{
    $role = AdminRole::factory()->fullAdmin()->create();
    return Admin::factory()->create(['role_id' => $role->id]);
}

test('admin can view servers page', function () {
    $admin = makeServerAdmin();
    $this->actingAs($admin, 'admin')
        ->get(route('admin.config.servers'))
        ->assertStatus(200)
        ->assertSee('Servers');
});

test('admin can create server', function () {
    $admin = makeServerAdmin();
    $response = $this->actingAs($admin, 'admin')
        ->post(route('admin.config.servers.store'), [
            'name'         => 'Test Server',
            'hostname'     => 'server1.example.com',
            'ip_address'   => '1.2.3.4',
            'port'         => 2222,
            'type'         => 'custom',
            'username'     => 'admin',
            'password'     => 'secret',
            'max_accounts' => 100,
            'active'       => 1,
        ]);
    $response->assertRedirect();
    $this->assertDatabaseHas('servers', ['name' => 'Test Server', 'hostname' => 'server1.example.com']);
});

test('admin can update server', function () {
    $admin  = makeServerAdmin();
    $server = Server::factory()->create();
    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.servers.update', $server), [
            'name'         => 'Updated Server Name',
            'hostname'     => $server->hostname,
            'max_accounts' => 200,
            'active'       => 1,
        ])
        ->assertRedirect();
    $this->assertDatabaseHas('servers', ['id' => $server->id, 'name' => 'Updated Server Name']);
});

test('admin can delete server', function () {
    $admin  = makeServerAdmin();
    $server = Server::factory()->create();
    $this->actingAs($admin, 'admin')
        ->delete(route('admin.config.servers.destroy', $server))
        ->assertRedirect();
    $this->assertDatabaseMissing('servers', ['id' => $server->id]);
});

test('admin can view server groups page', function () {
    $admin = makeServerAdmin();
    $this->actingAs($admin, 'admin')
        ->get(route('admin.config.server-groups'))
        ->assertStatus(200)
        ->assertSee('Server Groups');
});

test('admin can create server group', function () {
    $admin = makeServerAdmin();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.server-groups.store'), [
            'name'      => 'Primary Group',
            'fill_type' => 'round_robin',
        ])
        ->assertRedirect();
    $this->assertDatabaseHas('server_groups', ['name' => 'Primary Group', 'fill_type' => 'round_robin']);
});

test('admin can update server group', function () {
    $admin = makeServerAdmin();
    $group = ServerGroup::factory()->create();
    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.server-groups.update', $group), [
            'name'      => 'Renamed Group',
            'fill_type' => 'fill',
        ])
        ->assertRedirect();
    $this->assertDatabaseHas('server_groups', ['id' => $group->id, 'name' => 'Renamed Group']);
});

test('admin can delete server group', function () {
    $admin = makeServerAdmin();
    $group = ServerGroup::factory()->create();
    $this->actingAs($admin, 'admin')
        ->delete(route('admin.config.server-groups.destroy', $group))
        ->assertRedirect();
    $this->assertDatabaseMissing('server_groups', ['id' => $group->id]);
});

test('guest cannot access servers page', function () {
    $this->get(route('admin.config.servers'))
        ->assertRedirect(route('admin.login'));
});
