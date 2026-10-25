<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\NetworkIssue;
use App\Models\Server;
use App\Models\User;

/*
 * Network Status tells customers which server an issue is "Affecting", from
 * the issue's affected_server - but the admin form had no field for it, and
 * the controller validated a key called "affected" that no column holds, so
 * nothing was ever saved and customers were never told which server.
 */

function niaAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('a reported issue keeps the server it affects, and editing changes it', function () {
    $this->actingAs(niaAdmin(), 'admin')->post(route('admin.config.network-issues.store'), [
        'title' => 'Packet loss', 'description' => 'Upstream is dropping packets.', 'type' => 'server', 'status' => 'investigating', 'affected_server' => 'node-ist-1',
    ])->assertSessionHasNoErrors();

    $issue = NetworkIssue::where('title', 'Packet loss')->sole();
    expect($issue->affected_server)->toBe('node-ist-1');

    $this->actingAs(niaAdmin(), 'admin')->put(route('admin.config.network-issues.update', $issue), [
        'title' => 'Packet loss', 'description' => 'Upstream is dropping packets.', 'type' => 'server', 'status' => 'investigating', 'affected_server' => 'node-ist-2',
    ])->assertSessionHasNoErrors();
    expect($issue->fresh()->affected_server)->toBe('node-ist-2');
});

test('the form offers the field, with the servers as suggestions, and customers see it', function () {
    Server::factory()->create(['name' => 'node-ist-1']);
    NetworkIssue::create(['title' => 'Packet loss', 'description' => 'x', 'status' => 'open', 'affected_server' => 'node-ist-1']);

    $this->actingAs(niaAdmin(), 'admin')->get(route('admin.config.network-issues'))->assertOk()
        ->assertSee('name="affected_server"', false)->assertSee('<option value="node-ist-1">', false);

    $this->actingAs(User::factory()->create())->get(route('client.network-status'))->assertOk()->assertSee('node-ist-1');
});
