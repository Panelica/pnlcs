<?php

use App\Models\Client;
use App\Models\User;

test('client login page loads', function () {
    $response = $this->get(route('client.login'));
    $response->assertStatus(200)->assertSee((string) config('app.name'));
});

test('client register page loads', function () {
    $response = $this->get(route('client.register'));
    $response->assertStatus(200)->assertSee((string) config('app.name'));
});

test('client can register', function () {
    $response = $this->post(route('client.register.submit'), [
        'first_name' => 'New',
        'last_name' => 'Client',
        'email' => 'newclient@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000', 'country' => 'TR',
        'tos' => '1',
    ]);
    // Signed in, but sent to confirm the address first: verification ships on.
    $response->assertRedirect(route('client.verification.notice'));
    $this->assertAuthenticated();
    expect(User::where('email', 'newclient@example.com')->exists())->toBeTrue();
    expect(Client::where('email', 'newclient@example.com')->exists())->toBeTrue();
});

test('client can login', function () {
    $user = User::factory()->create(['email' => 'login@test.com', 'password' => 'secret123']);
    $response = $this->post(route('client.login.submit'), [
        'email' => 'login@test.com',
        'password' => 'secret123',
    ]);
    $response->assertRedirect(route('client.home'));
    $this->assertAuthenticatedAs($user);
});

test('client cannot login with wrong password', function () {
    User::factory()->create(['email' => 'fail@test.com', 'password' => 'secret123']);
    $response = $this->post(route('client.login.submit'), [
        'email' => 'fail@test.com',
        'password' => 'wrong',
    ]);
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('unauthenticated client redirected to login', function () {
    $response = $this->get(route('client.home'));
    $response->assertRedirect();
});

test('authenticated client can access dashboard', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('client.home'));
    $response->assertStatus(200)->assertSee('Welcome');
});

test('client can logout', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('client.logout'));
    $this->assertGuest();
});
