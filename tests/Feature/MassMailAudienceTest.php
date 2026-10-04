<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Domain;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Services\ClientAudience;

/*
 * Mass mail could only go to clients ticked one by one from a list of every
 * client: "everyone on server 2, about tonight's maintenance" meant finding
 * them by hand.
 */

function mmaWorld(): array
{
    $server1 = Server::factory()->create(['name' => 'web-1']);
    $server2 = Server::factory()->create(['name' => 'web-2']);
    $hosting = Product::factory()->create(['name' => 'Hosting S']);
    $vps = Product::factory()->create(['name' => 'VPS M']);
    $group = ClientGroup::create(['name' => 'Resellers']);

    $a = Client::factory()->create(['first_name' => 'Ada', 'status' => 'active']);
    Service::factory()->create(['client_id' => $a->id, 'product_id' => $hosting->id, 'server_id' => $server1->id, 'status' => 'active']);
    $b = Client::factory()->create(['first_name' => 'Bora', 'status' => 'active', 'group_id' => $group->id]);
    Service::factory()->create(['client_id' => $b->id, 'product_id' => $hosting->id, 'server_id' => $server2->id, 'status' => 'active']);
    Service::factory()->create(['client_id' => $b->id, 'product_id' => $vps->id, 'server_id' => $server1->id, 'status' => 'suspended']);
    $c = Client::factory()->create(['first_name' => 'Cem', 'status' => 'inactive']);
    Domain::factory()->create(['client_id' => $c->id, 'domain' => 'cem.com.tr', 'status' => 'active']);

    return compact('server1', 'server2', 'hosting', 'vps', 'group', 'a', 'b', 'c');
}

test('clients are picked by product, server, service status, group, status and domain extension', function () {
    $w = mmaWorld();
    $aud = app(ClientAudience::class);

    expect($aud->matching(['product_ids' => [$w['hosting']->id]]))->toBe([$w['a']->id, $w['b']->id])
        ->and($aud->matching(['server_ids' => [$w['server1']->id]]))->toBe([$w['a']->id, $w['b']->id])
        ->and($aud->matching(['server_ids' => [$w['server1']->id], 'service_status' => 'active']))->toBe([$w['a']->id])
        ->and($aud->matching(['group_id' => $w['group']->id]))->toBe([$w['b']->id])
        ->and($aud->matching(['client_status' => 'inactive']))->toBe([$w['c']->id])
        ->and($aud->matching(['tld' => '.com.tr']))->toBe([$w['c']->id])
        ->and(ClientAudience::hasFilters(['tld' => '']))->toBeFalse();
});

test('the mass mail screen ticks the matching clients and says how many', function () {
    $w = mmaWorld();
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['manage_email_templates']])->id]);

    $html = $this->actingAs($admin, 'admin')->get(route('admin.bulk.mass-email', ['server_ids' => [$w['server2']->id]]))
        ->assertOk()->assertSee(__('admin.bulk.filter_matched', ['count' => 1]))->getContent();

    expect(substr_count($html, 'name="client_ids[]" value="'.$w['b']->id.'" checked'))->toBe(1)
        ->and(substr_count($html, 'name="client_ids[]" value="'.$w['a']->id.'" checked'))->toBe(0);
});
