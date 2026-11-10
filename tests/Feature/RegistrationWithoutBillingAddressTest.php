<?php

use App\Models\User;

/*
 * The signup form asked for the billing address (and said invoices are issued
 * to it). The address is asked at checkout and on the profile page instead,
 * so opening an account no longer requires one.
 */

test('an account can be opened without a billing address', function () {
    $email = 'no-address-'.uniqid().'@example.com';

    $this->post(route('client.register.submit'), [
        'first_name' => 'Jan',
        'last_name' => 'Kowalski',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'country' => 'PL',
        'tos' => '1',
    ])->assertRedirect();

    $user = User::where('email', $email)->firstOrFail();
    $client = $user->clients()->firstOrFail();

    expect($client->address1)->toBeNull()
        ->and($client->city)->toBeNull()
        ->and($client->postcode)->toBeNull()
        ->and($client->country)->toBe('PL');
});

test('the signup form no longer offers the billing address', function () {
    $this->get(route('client.register'))->assertOk()
        ->assertDontSee(__('common.form.billing_address'))
        ->assertDontSee(__('common.form.billing_address_hint'))
        ->assertDontSee('name="address1"', false)
        ->assertDontSee('name="postcode"', false);
});
