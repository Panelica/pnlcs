<?php

use App\Mail\BulkMassMail;
use App\Models\Client;
use App\Models\User;
use App\Models\UserInvite;
use App\Support\ClientPermissions;
use Illuminate\Support\Facades\Mail;

/*
 * The account owner manages who else can sign in to the account.
 *
 * Invitations, their acceptance and per-login permissions existed, but only
 * the API could send an invitation, and no screen showed or changed which
 * logins were on an account or what they could do.
 */

function cauAccount(string $ownerEmail = 'owner@example.test'): array
{
    $client = Client::factory()->create();
    $owner = User::factory()->create(['email' => $ownerEmail]);
    $client->users()->attach($owner->id, ['owner' => true]);

    return [$client, $owner];
}

function cauMember(Client $client, array $permissions, string $email = 'accountant@example.test'): User
{
    $user = User::factory()->create(['email' => $email]);
    $client->users()->attach($user->id, ['owner' => false, 'permissions' => json_encode($permissions)]);

    return $user;
}

it('lets the owner invite someone with chosen permissions', function () {
    Mail::fake();
    [$client, $owner] = cauAccount();

    test()->actingAs($owner)->get(route('client.account.users'))->assertOk()->assertSee('owner@example.test');
    test()->actingAs($owner)->post(route('client.account.users.invite'), [
        'email' => 'Accountant@Example.test', 'permissions' => ['invoices', 'quotes'],
    ])->assertSessionHasNoErrors();

    $invite = UserInvite::where('client_id', $client->id)->sole();
    expect($invite->email)->toBe('accountant@example.test')
        ->and($invite->permissions)->toBe(['invoices', 'quotes']);
    Mail::assertQueued(BulkMassMail::class, fn ($mail) => $mail->hasTo('accountant@example.test'));
});

it('replaces an open invitation to the same address', function () {
    Mail::fake();
    [$client, $owner] = cauAccount();

    foreach ([['invoices'], ['tickets']] as $permissions) {
        test()->actingAs($owner)->post(route('client.account.users.invite'), ['email' => 'a@example.test', 'permissions' => $permissions]);
    }

    expect(UserInvite::where('client_id', $client->id)->get()->pluck('permissions')->all())->toBe([['tickets']]);
});

it('refuses an address already on the account and an unknown permission', function () {
    Mail::fake();
    [$client, $owner] = cauAccount();
    cauMember($client, ['invoices']);

    test()->actingAs($owner)->post(route('client.account.users.invite'), ['email' => 'accountant@example.test', 'permissions' => ['invoices']])
        ->assertSessionHasErrors('email');
    test()->actingAs($owner)->post(route('client.account.users.invite'), ['email' => 'b@example.test', 'permissions' => ['root']])
        ->assertSessionHasErrors('permissions.0');

    expect(UserInvite::count())->toBe(0);
});

it('changes a login\'s permissions, which the client area then enforces', function () {
    [$client, $owner] = cauAccount();
    $member = cauMember($client, ['invoices']);

    test()->actingAs($owner)->put(route('client.account.users.update', $member), ['permissions' => ['tickets']])
        ->assertSessionHasNoErrors();

    expect(ClientPermissions::granted($member->fresh(), $client))->toBe(['tickets']);
    test()->actingAs($member)->get(route('client.invoices.index'))->assertForbidden();
    test()->actingAs($member)->get(route('client.tickets.index'))->assertOk();
});

it('removes a login from the account and cancels an invitation', function () {
    Mail::fake();
    [$client, $owner] = cauAccount();
    $member = cauMember($client, ['invoices']);
    $invite = UserInvite::create(['token' => hash('sha256', 'x'), 'email' => 'c@example.test', 'client_id' => $client->id, 'invited_by' => 0, 'permissions' => ['tickets']]);

    test()->actingAs($owner)->delete(route('client.account.users.destroy', $member))->assertRedirect();
    test()->actingAs($owner)->delete(route('client.account.users.invites.destroy', $invite))->assertRedirect();

    expect($client->users()->whereKey($member->id)->exists())->toBeFalse()
        ->and(UserInvite::find($invite->id))->toBeNull()
        ->and(User::find($member->id))->not->toBeNull();
});

it('keeps the page and its actions to the owner', function () {
    [$client, $owner] = cauAccount();
    $member = cauMember($client, ClientPermissions::ALL);
    $other = cauMember($client, ['invoices'], 'other@example.test');

    test()->actingAs($member)->get(route('client.account.users'))->assertForbidden();
    test()->actingAs($member)->post(route('client.account.users.invite'), ['email' => 'x@example.test', 'permissions' => ['invoices']])->assertForbidden();
    test()->actingAs($member)->delete(route('client.account.users.destroy', $other))->assertForbidden();

    // Nor can the owner remove themself or change their own access.
    test()->actingAs($owner)->delete(route('client.account.users.destroy', $owner))->assertNotFound();
    expect($client->users()->count())->toBe(3);
});

it('does not reach into another account', function () {
    [$client, $owner] = cauAccount();
    [$otherClient] = cauAccount('other-owner@example.test');
    $stranger = cauMember($otherClient, ['invoices'], 'stranger@example.test');
    $foreignInvite = UserInvite::create(['token' => hash('sha256', 'y'), 'email' => 'd@example.test', 'client_id' => $otherClient->id, 'invited_by' => 0]);

    test()->actingAs($owner)->delete(route('client.account.users.destroy', $stranger))->assertNotFound();
    test()->actingAs($owner)->delete(route('client.account.users.invites.destroy', $foreignInvite))->assertNotFound();

    expect($otherClient->users()->whereKey($stranger->id)->exists())->toBeTrue()
        ->and(UserInvite::find($foreignInvite->id))->not->toBeNull();
});

it('shows the menu link to the owner only', function () {
    [$client, $owner] = cauAccount();
    $member = cauMember($client, ClientPermissions::ALL);

    test()->actingAs($owner)->get(route('client.account.security'))->assertSee(route('client.account.users'), false);
    test()->actingAs($member)->get(route('client.account.security'))->assertDontSee(route('client.account.users'), false);
});
