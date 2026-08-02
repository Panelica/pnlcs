<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;

/**
 * Editing a customer from the admin side.
 *
 * The email rule is unique:clients,email with nothing excluded, so a client's
 * own address counted against them: saving any change at all - a corrected
 * surname, a new telephone - was refused with "email has already been taken"
 * unless the admin also changed the address to one nobody was using. The
 * customer-facing forms have always excluded the row being edited.
 *
 * The country rule says nullable and the column will not take a null, so
 * clearing it was a 500 rather than a message. The same pair of faults as the
 * customer profile page.
 */
function clientEditingAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function editableClient(array $overrides = []): Client
{
    return Client::factory()->create(array_merge([
        'first_name' => 'Deniz',
        'last_name' => 'Kaya',
        'email' => 'deniz@example.test',
        'country' => 'TR',
        'status' => 'active',
    ], $overrides));
}

test('a customer can be saved without changing their email', function () {
    $client = editableClient();

    $this->actingAs(clientEditingAdmin(), 'admin')
        ->put(route('admin.clients.update', $client), [
            'first_name' => 'Deniz',
            'last_name' => 'Kayaoglu',
            'email' => 'deniz@example.test',
            'country' => 'TR',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

    expect($client->fresh()->last_name)->toBe('Kayaoglu');
});

test('somebody else email is still refused', function () {
    $client = editableClient();
    editableClient(['email' => 'taken@example.test']);

    $this->actingAs(clientEditingAdmin(), 'admin')
        ->put(route('admin.clients.update', $client), [
            'first_name' => 'Deniz',
            'last_name' => 'Kaya',
            'email' => 'taken@example.test',
            'country' => 'TR',
            'status' => 'active',
        ])->assertSessionHasErrors('email');

    expect($client->fresh()->email)->toBe('deniz@example.test');
});

test('clearing the country is refused rather than crashing', function () {
    $client = editableClient();

    $this->actingAs(clientEditingAdmin(), 'admin')
        ->put(route('admin.clients.update', $client), [
            'first_name' => 'Deniz',
            'last_name' => 'Kaya',
            'email' => 'deniz@example.test',
            'country' => '',
            'status' => 'active',
        ])->assertSessionHasErrors('country');

    expect($client->fresh()->country)->toBe('TR');
});
