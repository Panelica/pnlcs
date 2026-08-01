<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The address on the profile page.
 *
 * Every address field on that form is rendered from $user - company name,
 * telephone, country, street, city, postcode - and the users table has none of
 * those columns. They came out blank for everybody, however much the client
 * record held: sixteen customers here have a street address and thirty-two a
 * telephone number.
 *
 * Saving writes those same fields to the client. So a customer who opened
 * their profile to correct a surname, and saved what the page showed them,
 * handed back a set of empty boxes and lost the lot.
 */
function profileCustomer(): array
{
    $user = User::factory()->create([
        'first_name' => 'Kerem',
        'last_name' => 'Yilmaz',
        'email' => 'kerem@example.test',
        'password' => Hash::make('the-password'),
    ]);

    $client = Client::factory()->create([
        'first_name' => 'Kerem',
        'last_name' => 'Yilmaz',
        'email' => 'kerem@example.test',
        'company_name' => 'Yilmaz Bilisim',
        'phone_number' => '+90 555 111 22 33',
        'country' => 'TR',
        'address1' => 'Ataturk Bulvari 42',
        'city' => 'Ankara',
        'state' => 'Ankara',
        'postcode' => '06420',
    ]);

    $user->clients()->attach($client->id);

    return [$user, $client];
}

test('the page shows the address the account actually has', function () {
    [$user] = profileCustomer();

    $this->actingAs($user)->get(route('client.account.profile'))
        ->assertOk()
        ->assertSee('Yilmaz Bilisim', false)
        ->assertSee('+90 555 111 22 33', false)
        ->assertSee('Ataturk Bulvari 42', false)
        ->assertSee('Ankara', false)
        ->assertSee('06420', false);
});

test('correcting a name does not empty the address', function () {
    [$user, $client] = profileCustomer();

    // What the form renders, with one surname changed.
    $this->actingAs($user)->put(route('client.account.update'), [
        'first_name' => 'Kerem',
        'last_name' => 'Yilmazoglu',
        'email' => 'kerem@example.test',
        'company_name' => $client->company_name,
        'phone_number' => $client->phone_number,
        'country' => $client->country,
        'address1' => $client->address1,
        'city' => $client->city,
        'state' => $client->state,
        'postcode' => $client->postcode,
    ])->assertRedirect();

    $fresh = $client->fresh();

    expect($fresh->last_name)->toBe('Yilmazoglu')
        ->and($fresh->address1)->toBe('Ataturk Bulvari 42')
        ->and($fresh->city)->toBe('Ankara')
        ->and($fresh->phone_number)->toBe('+90 555 111 22 33');
});

test('the name and sign-in address still come from the login', function () {
    [$user] = profileCustomer();

    $this->actingAs($user)->get(route('client.account.profile'))
        ->assertOk()
        ->assertSee('kerem@example.test', false)
        ->assertSee('Kerem', false);
});
