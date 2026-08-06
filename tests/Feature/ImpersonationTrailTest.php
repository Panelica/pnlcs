<?php

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\User;

/**
 * Signing in as a customer, with nothing to show for it.
 *
 * Nine ordinary events are written to the activity log with the customer's id
 * on them - an invoice raised, a ticket opened, a service suspended - and the
 * customer's own Log tab reads them back.
 *
 * Taking over the account writes nothing at all. Staff can sign in as the
 * customer, see every invoice, place an order, open a ticket, change the
 * account, and the record of who was in there does not exist. When a customer
 * later says "I never ordered that", there is nothing to check.
 */
function impersonatingAdmin(): Admin
{
    $role = AdminRole::factory()->create([
        'is_full_admin' => false,
        'permissions' => ['edit_clients', 'view_clients', 'list_clients'],
    ]);

    return Admin::factory()->create(['role_id' => $role->id, 'username' => 'sonia']);
}

function clientWithUser(): Client
{
    $client = Client::factory()->create(['first_name' => 'Kerem', 'last_name' => 'Aydin']);
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return $client;
}

it('records who signed in as the customer', function () {
    $client = clientWithUser();

    test()->actingAs(impersonatingAdmin(), 'admin')
        ->post(route('admin.clients.impersonate', $client))
        ->assertRedirect();

    $entries = ActivityLog::forClient($client)->get();

    expect($entries)->toHaveCount(1);
    expect($entries->first()->description)->toContain('signed in as');
    expect($entries->first()->user)->toBe('sonia');
});

it('records when they stop', function () {
    // The session does not survive between requests in this suite, so the
    // state impersonation leaves behind is handed to the stop request
    // directly - that is what the stop path reads.
    $client = clientWithUser();
    $admin = impersonatingAdmin();

    test()->actingAs($admin, 'admin')->withSession([
        'impersonating_admin_id' => $admin->id,
        'impersonating_admin_name' => 'sonia',
        'active_client_id' => $client->id,
    ])->get(route('admin.clients.stop-impersonation'));

    $entries = ActivityLog::forClient($client)->get();

    expect($entries)->toHaveCount(1);
    expect($entries->first()->description)->toContain('stopped signing in');
    expect($entries->first()->user)->toBe('sonia');
});

it('writes nothing when there is nobody to sign in as', function () {
    $client = Client::factory()->create();

    test()->actingAs(impersonatingAdmin(), 'admin')
        ->post(route('admin.clients.impersonate', $client))
        ->assertRedirect();

    expect(ActivityLog::forClient($client)->count())->toBe(0);
});
