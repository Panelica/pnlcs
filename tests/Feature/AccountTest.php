<?php

use App\Models\Client;
use App\Models\User;


function makeClientUser(): array
{
    $user   = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane_' . uniqid() . '@example.com']);
    $client = Client::factory()->create(['email' => $user->email, 'first_name' => 'Jane', 'last_name' => 'Doe']);
    $user->clients()->attach($client->id, ['owner' => true, 'permissions' => null]);
    return [$user, $client];
}

test('unauthenticated user cannot access profile', function () {
    $this->get(route('client.account.profile'))->assertRedirect();
});

test('authenticated user can view profile page', function () {
    [$user] = makeClientUser();
    $response = $this->actingAs($user)->get(route('client.account.profile'));
    $response->assertStatus(200);
    $response;
    $response;
});

test('user can update profile', function () {
    [$user, $client] = makeClientUser();

    $response = $this->actingAs($user)->put(route('client.account.update'), [
        'first_name'   => 'Updated',
        'last_name'    => 'Name',
        'email'        => $user->email,
        'company_name' => 'Acme Inc',
        'address1'     => '123 Main St',
        'address2'     => '',
        'city'         => 'Springfield',
        'state'        => 'IL',
        'postcode'     => '62701',
        'country'      => 'US',
        'phone_number' => '+1 555 0100',
    ]);

    $response->assertRedirect(route('client.account.profile'));
    $response->assertSessionHas('success');

    $user->refresh();
    expect($user->first_name)->toBe('Updated');
    expect($user->last_name)->toBe('Name');

    $client->refresh();
    expect($client->company_name)->toBe('Acme Inc');
    expect($client->city)->toBe('Springfield');
});

test('profile update validates required fields', function () {
    [$user] = makeClientUser();

    $response = $this->actingAs($user)->put(route('client.account.update'), [
        'first_name' => '',
        'last_name'  => '',
        'email'      => 'not-an-email',
    ]);

    $response->assertSessionHasErrors(['first_name', 'last_name', 'email']);
});

test('user can view change password page', function () {
    [$user] = makeClientUser();
    $response = $this->actingAs($user)->get(route('client.account.password'));
    $response->assertStatus(200);
    $response->assertSee('Change Password');
});

test('user can change password with correct current password', function () {
    [$user] = makeClientUser();

    $response = $this->actingAs($user)->put(route('client.account.password.update'), [
        'current_password'      => 'password',
        'password'              => 'NewPass1234',
        'password_confirmation' => 'NewPass1234',
    ]);

    $response->assertRedirect(route('client.account.password'));
    $response->assertSessionHas('success');
});

test('password change fails with wrong current password', function () {
    [$user] = makeClientUser();

    $response = $this->actingAs($user)->put(route('client.account.password.update'), [
        'current_password'      => 'wrongpassword',
        'password'              => 'NewPass1234',
        'password_confirmation' => 'NewPass1234',
    ]);

    $response->assertSessionHasErrors('current_password');
});

test('password validation requires minimum complexity', function () {
    [$user] = makeClientUser();

    $response = $this->actingAs($user)->put(route('client.account.password.update'), [
        'current_password'      => 'password',
        'password'              => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors('password');
});

test('user can view contacts page', function () {
    [$user] = makeClientUser();
    $response = $this->actingAs($user)->get(route('client.account.contacts'));
    $response->assertStatus(200);
    $response->assertSee('Contacts');
    $response->assertSee('Add New Contact');
});

test('user can add a contact', function () {
    [$user, $client] = makeClientUser();

    $response = $this->actingAs($user)->post(route('client.account.contacts.store'), [
        'first_name'   => 'Alice',
        'last_name'    => 'Smith',
        'email'        => 'alice_' . uniqid() . '@example.com',
        'company_name' => 'Tech Co',
        'phone_number' => '+1 555 9999',
    ]);

    $response->assertRedirect(route('client.account.contacts'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('contacts', [
        'client_id'  => $client->id,
        'first_name' => 'Alice',
        'last_name'  => 'Smith',
    ]);
});

test('contact store validates email', function () {
    [$user] = makeClientUser();

    $response = $this->actingAs($user)->post(route('client.account.contacts.store'), [
        'first_name' => 'Bad',
        'last_name'  => 'Email',
        'email'      => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('email');
});

test('user can view security page', function () {
    [$user] = makeClientUser();
    $response = $this->actingAs($user)->get(route('client.account.security'));
    $response->assertStatus(200);
    $response->assertSee('Two-Factor Authentication');
});
