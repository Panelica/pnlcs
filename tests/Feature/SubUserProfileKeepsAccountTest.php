<?php

use App\Models\Client;
use App\Models\User;

/**
 * A colleague's profile save renaming the account.
 *
 * The name and address at the top of the profile form are the signed-in
 * login's own, and saving copied them onto the account as well. For the
 * account holder those are the same thing. For a colleague invited onto the
 * account, saving their own profile renamed the account after them and moved
 * its contact address, and the invoices mailed there, to their own.
 */
function accountWithColleague(): array
{
    $client = Client::factory()->create([
        'first_name' => 'Ayse', 'last_name' => 'Demir', 'email' => 'ayse@example.test', 'country' => 'TR',
    ]);

    $holder = User::factory()->create(['first_name' => 'Ayse', 'last_name' => 'Demir', 'email' => 'ayse@example.test']);
    $holder->clients()->attach($client->id, ['owner' => true]);

    $colleague = User::factory()->create(['first_name' => 'Can', 'last_name' => 'Ozturk', 'email' => 'can@example.test']);
    $colleague->clients()->attach($client->id, ['owner' => false, 'permissions' => json_encode(['profile'])]);

    return [$client, $holder, $colleague];
}

function profileSave(User $user, Client $client, array $overrides = []): void
{
    test()->actingAs($user)->put(route('client.account.update'), array_merge([
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'country' => $client->country,
        'city' => $client->city,
    ], $overrides))->assertSessionHasNoErrors()->assertRedirect();
}

it('leaves the account name and address alone when a colleague saves their profile', function () {
    [$client, , $colleague] = accountWithColleague();

    profileSave($colleague, $client, ['last_name' => 'Ozturk-Yeni']);

    expect($client->fresh())
        ->first_name->toBe('Ayse')
        ->last_name->toBe('Demir')
        ->email->toBe('ayse@example.test')
        ->and($colleague->fresh()->last_name)->toBe('Ozturk-Yeni');
});

it('still saves the account details a colleague with the profile permission changes', function () {
    [$client, , $colleague] = accountWithColleague();

    profileSave($colleague, $client, ['city' => 'Izmir']);

    expect($client->fresh()->city)->toBe('Izmir')
        ->and($client->fresh()->email)->toBe('ayse@example.test');
});

it('renames the account when the account holder saves their profile', function () {
    [$client, $holder] = accountWithColleague();

    profileSave($holder, $client, ['last_name' => 'Demir-Kaya']);

    expect($client->fresh()->last_name)->toBe('Demir-Kaya');
});

it('renames the account for a login made before the owner flag, carrying its address', function () {
    $client = Client::factory()->create(['first_name' => 'Old', 'last_name' => 'Login', 'email' => 'old@example.test', 'country' => 'TR']);
    $user = User::factory()->create(['first_name' => 'Old', 'last_name' => 'Login', 'email' => 'old@example.test']);
    $user->clients()->attach($client->id);

    profileSave($user, $client, ['last_name' => 'Renamed']);

    expect($client->fresh()->last_name)->toBe('Renamed');
});
