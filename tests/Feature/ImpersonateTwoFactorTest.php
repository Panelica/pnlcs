<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\User;

/**
 * Impersonation lands the admin on the client area, which is behind the 2fa
 * middleware. An admin has already authenticated as an admin, with the
 * edit_clients permission, and cannot possibly hold the customer's TOTP - so
 * a client with 2FA enabled could not be impersonated at all: the admin was
 * bounced to the customer's 2FA screen with no way through. Taking over the
 * account is exactly when that second factor must not stand in the way.
 */
function imp2faAdmin(): Admin
{
    $role = AdminRole::factory()->create([
        'is_full_admin' => false,
        'permissions' => ['edit_clients', 'view_clients', 'list_clients'],
    ]);

    return Admin::factory()->create(['role_id' => $role->id, 'username' => 'ops']);
}

function imp2faClient(): Client
{
    $client = Client::factory()->create();
    $user = User::factory()->create([
        'second_factor_type' => 'totp',
        'second_factor_secret' => 'JBSWY3DPEHPK3PXP',
    ]);
    $user->clients()->attach($client->id);

    return $client;
}

it('reaches the client area when impersonating a 2FA-enabled client', function () {
    $client = imp2faClient();

    test()->actingAs(imp2faAdmin(), 'admin')
        ->post(route('admin.clients.impersonate', $client))
        ->assertRedirect(route('client.home'));

    // The very next request must land in the client area, not be bounced to
    // the customer's 2FA screen.
    test()->get(route('client.home'))->assertOk();
});

it('still sends an ordinary client login through 2FA', function () {
    // Guard against fixing impersonation by disabling 2FA for everyone: a
    // real client logging in with 2FA on is still challenged.
    $client = imp2faClient();
    $user = $client->users()->first();

    test()->actingAs($user)
        ->get(route('client.home'))
        ->assertRedirect(route('client.2fa.verify'));
});

it('a stale 2FA pass in the session cannot wave a real 2FA login through', function () {
    // The exact leak: a leftover 2fa_verified (from a prior impersonation)
    // sitting in the browser session when a real 2FA client signs in.
    $client = imp2faClient();
    $user = $client->users()->first();

    test()->withSession(['2fa_verified' => true])
        ->post(route('client.login.submit'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('client.2fa.verify'))
        ->assertSessionMissing('2fa_verified');
});
