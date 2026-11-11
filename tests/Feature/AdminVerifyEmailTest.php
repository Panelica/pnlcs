<?php

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\User;

/*
 * Email verification is on by default and stops an unverified customer at the
 * payment step. When the email never reaches them, the customer is stuck and
 * staff had nothing to press: the address could only be confirmed from the
 * customer's own mailbox. The client page now shows whether the owner's login
 * is verified and lets staff with edit_clients confirm it for them.
 */

function verifyEmailStaff(array $permissions = ['view_clients', 'list_clients', 'edit_clients']): Admin
{
    $role = AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => $permissions]);

    return Admin::factory()->create(['role_id' => $role->id]);
}

function verifyEmailCustomer(bool $verified = false): array
{
    $user = User::factory()->create(['email' => 'owner@example.test', 'email_verified_at' => $verified ? now() : null]);
    $client = Client::factory()->create(['email' => 'owner@example.test']);
    $user->clients()->attach($client->id, ['owner' => true]);

    return [$user, $client];
}

test('staff can mark the owner\'s email address as verified', function () {
    [$user, $client] = verifyEmailCustomer();

    $this->actingAs(verifyEmailStaff(), 'admin')
        ->from(route('admin.clients.show', $client))
        ->post(route('admin.clients.verify-email', $client))
        ->assertRedirect(route('admin.clients.show', $client))
        ->assertSessionHas('success');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and(ActivityLog::where('client_id', $client->id)->where('description', 'like', '%as verified%')->exists())->toBeTrue();
});

test('an address that is already verified keeps its date', function () {
    [$user, $client] = verifyEmailCustomer(verified: true);
    $user->forceFill(['email_verified_at' => now()->subMonth()])->save();
    $before = $user->fresh()->email_verified_at;

    $this->actingAs(verifyEmailStaff(), 'admin')
        ->post(route('admin.clients.verify-email', $client))
        ->assertSessionHas('success');

    expect($user->fresh()->email_verified_at->eq($before))->toBeTrue()
        ->and(ActivityLog::where('client_id', $client->id)->where('description', 'like', '%as verified%')->exists())->toBeFalse();
});

test('only the owner is verified, not another login on the account', function () {
    [$owner, $client] = verifyEmailCustomer();
    $other = User::factory()->create(['email_verified_at' => null]);
    $other->clients()->attach($client->id, ['owner' => false]);

    $this->actingAs(verifyEmailStaff(), 'admin')->post(route('admin.clients.verify-email', $client));

    expect($owner->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($other->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a client without a login gets an error instead of a silent success', function () {
    $client = Client::factory()->create();

    $this->actingAs(verifyEmailStaff(), 'admin')
        ->post(route('admin.clients.verify-email', $client))
        ->assertSessionHas('error');
});

test('staff who cannot edit clients cannot verify an address', function () {
    [$user, $client] = verifyEmailCustomer();

    $this->actingAs(verifyEmailStaff(['view_clients', 'list_clients']), 'admin')
        ->post(route('admin.clients.verify-email', $client))
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('the client page shows the state and offers the button only while unverified', function () {
    [$user, $client] = verifyEmailCustomer();
    $staff = verifyEmailStaff();

    $this->actingAs($staff, 'admin')->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertSee(__('admin.clients.email_unverified'))
        ->assertSee(route('admin.clients.verify-email', $client), false);

    $user->markEmailAsVerified();

    $this->actingAs($staff, 'admin')->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertDontSee(__('admin.clients.email_unverified'))
        ->assertDontSee(route('admin.clients.verify-email', $client), false);
});

test('staff without edit_clients see the state but no button', function () {
    [, $client] = verifyEmailCustomer();

    $this->actingAs(verifyEmailStaff(['view_clients', 'list_clients']), 'admin')
        ->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertSee(__('admin.clients.email_unverified'))
        ->assertDontSee(route('admin.clients.verify-email', $client), false);
});
