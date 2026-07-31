<?php

use App\Mail\LoginEmailChangedMail;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Changing the address you sign in with.
 *
 * Changing the password asks for the current one. Changing the email address
 * asked for nothing at all - and the email address is where "forgot password"
 * sends the link, so anyone who reached an open session could point the
 * account at an address of their own and take it over at leisure. The old
 * address was told nothing about it either.
 */
function profileOwner(): array
{
    $user = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('the-real-password'),
    ]);

    $client = Client::factory()->create(['email' => 'owner@example.test']);
    $user->clients()->attach($client->id);

    return [$user, $client];
}

function profileFields(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Account',
        'last_name' => 'Owner',
        'email' => 'owner@example.test',
        'country' => 'TR',
    ], $overrides);
}

test('the login address cannot be changed without the password', function () {
    [$user] = profileOwner();

    $this->actingAs($user)
        ->put(route('client.account.update'), profileFields(['email' => 'attacker@example.test']))
        ->assertSessionHasErrors('current_password');

    expect($user->fresh()->email)->toBe('owner@example.test');
});

test('a wrong password does not change the login address', function () {
    [$user] = profileOwner();

    $this->actingAs($user)
        ->put(route('client.account.update'), profileFields([
            'email' => 'attacker@example.test',
            'current_password' => 'not-the-password',
        ]))->assertSessionHasErrors('current_password');

    expect($user->fresh()->email)->toBe('owner@example.test');
});

test('the owner can change it with their password', function () {
    Mail::fake();
    [$user, $client] = profileOwner();

    $this->actingAs($user)
        ->put(route('client.account.update'), profileFields([
            'email' => 'new@example.test',
            'current_password' => 'the-real-password',
        ]))->assertRedirect();

    expect($user->fresh()->email)->toBe('new@example.test')
        ->and($client->fresh()->email)->toBe('new@example.test');
});

test('the address that is losing the account is told', function () {
    Mail::fake();
    [$user] = profileOwner();

    $this->actingAs($user)
        ->put(route('client.account.update'), profileFields([
            'email' => 'new@example.test',
            'current_password' => 'the-real-password',
        ]));

    Mail::assertQueued(LoginEmailChangedMail::class, function ($mail) {
        return $mail->hasTo('owner@example.test');
    });
});

test('the rest of the profile still saves without a password', function () {
    [$user, $client] = profileOwner();

    $this->actingAs($user)
        ->put(route('client.account.update'), profileFields([
            'first_name' => 'Renamed',
            'city' => 'Ankara',
        ]))->assertRedirect();

    expect($user->fresh()->first_name)->toBe('Renamed')
        ->and($client->fresh()->city)->toBe('Ankara');
});

test('clearing the country is refused rather than crashing', function () {
    [$user] = profileOwner();

    // The select offers a blank option; the column does not accept one.
    $this->actingAs($user)
        ->put(route('client.account.update'), profileFields(['country' => '']))
        ->assertSessionHasErrors('country');
});
