<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\ClientGroup;

/*
 * Three things billing reads about a client could not be set after the client
 * was created, or at all: Tax Exempt (shown on the client page, used by every
 * invoice, with no field anywhere), "never suspend automatically"
 * (override_auto_suspend, read by auto-suspend, not even fillable), and the
 * client group (its discount and its suspend/terminate exemptions), offered
 * only on the New Client form.
 */

function cebAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

function cebForm(Client $client, array $changes): array
{
    return array_merge([
        'first_name' => $client->first_name, 'last_name' => $client->last_name, 'email' => $client->email,
        'country' => $client->country ?: 'TR', 'status' => 'active', 'default_payment_method' => 'banktransfer',
    ], $changes);
}

test('the edit form sets tax exemption, the auto-suspend exemption and the group, and clears them', function () {
    $client = Client::factory()->create(['tax_exempt' => false, 'group_id' => null]);
    $group = ClientGroup::create(['name' => 'Resellers']);

    $this->actingAs(cebAdmin(), 'admin')->put(route('admin.clients.update', $client), cebForm($client, [
        'tax_exempt' => '1', 'override_auto_suspend' => '1', 'group_id' => $group->id,
    ]))->assertSessionHasNoErrors();

    $client->refresh();
    expect($client->tax_exempt)->toBeTrue()
        ->and($client->override_auto_suspend)->toBeTrue()
        ->and($client->group_id)->toBe($group->id);

    // The hidden fields post 0 for a cleared box.
    $this->actingAs(cebAdmin(), 'admin')->put(route('admin.clients.update', $client), cebForm($client, [
        'tax_exempt' => '0', 'override_auto_suspend' => '0', 'group_id' => '',
    ]))->assertSessionHasNoErrors();

    $client->refresh();
    expect($client->tax_exempt)->toBeFalse()
        ->and($client->override_auto_suspend)->toBeFalse()
        ->and($client->group_id)->toBeNull();
});

test('the edit form shows the three fields, filled in', function () {
    $group = ClientGroup::create(['name' => 'Resellers']);
    $client = Client::factory()->create(['tax_exempt' => true, 'group_id' => $group->id]);
    $client->forceFill(['override_auto_suspend' => true])->save();

    $html = $this->actingAs(cebAdmin(), 'admin')->get(route('admin.clients.edit', $client))->assertOk()->getContent();

    expect($html)->toContain('name="tax_exempt" value="1" checked')
        ->toContain('name="override_auto_suspend" value="1" checked')
        ->toMatch('/<option value="'.$group->id.'"\s+selected>Resellers<\/option>/')
        ->toContain(__('admin.clients.never_auto_suspend'));
});
