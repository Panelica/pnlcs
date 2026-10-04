<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Credit;

/*
 * The client's credit balance was shown on their page, and nothing in the
 * admin area could change it: a goodwill credit, a refund to balance or a
 * correction meant the API's AddCredit (which only adds) or the database.
 */

function accAdmin(array $permissions = ['view_clients', 'manage_invoices']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

it('adds and removes credit with a reason, keeping the ledger and the balance together', function () {
    $admin = accAdmin();
    $client = Client::factory()->create(['credit' => 0]);

    test()->actingAs($admin, 'admin')->get(route('admin.clients.show', $client))->assertSee(route('admin.clients.credit', $client), false);

    test()->actingAs($admin, 'admin')->post(route('admin.clients.credit.store', $client), ['type' => 'add', 'amount' => '25.50', 'description' => 'Goodwill for the outage'])
        ->assertRedirect(route('admin.clients.credit', $client))->assertSessionHas('success');
    test()->actingAs($admin, 'admin')->post(route('admin.clients.credit.store', $client), ['type' => 'remove', 'amount' => '10', 'description' => 'Correction'])
        ->assertSessionHas('success');

    expect((float) $client->fresh()->credit)->toBe(15.5)
        ->and(Credit::where('client_id', $client->id)->orderBy('id')->pluck('amount')->map(fn ($a) => (float) $a)->all())->toBe([25.5, -10.0])
        ->and(Credit::where('client_id', $client->id)->value('admin_id'))->toBe($admin->id);

    test()->actingAs($admin, 'admin')->get(route('admin.clients.credit', $client))->assertOk()->assertSee('Goodwill for the outage')->assertSee('Correction');
});

it('will not take more than the balance', function () {
    $client = Client::factory()->create(['credit' => 5]);

    test()->actingAs(accAdmin(), 'admin')->post(route('admin.clients.credit.store', $client), ['type' => 'remove', 'amount' => '6', 'description' => 'Too much'])
        ->assertSessionHasErrors('amount');

    expect((float) $client->fresh()->credit)->toBe(5.0)->and(Credit::where('client_id', $client->id)->count())->toBe(0);
});

it('is for staff who manage invoices', function () {
    $client = Client::factory()->create(['credit' => 0]);
    $viewer = accAdmin(['view_clients']);

    test()->actingAs($viewer, 'admin')->post(route('admin.clients.credit.store', $client), ['type' => 'add', 'amount' => '1', 'description' => 'x'])->assertForbidden();
    test()->actingAs($viewer, 'admin')->get(route('admin.clients.show', $client))->assertDontSee(route('admin.clients.credit', $client), false);
});
