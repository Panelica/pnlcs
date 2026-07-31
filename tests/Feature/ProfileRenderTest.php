<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The profile page has to carry the field the controller now insists on.
 *
 * The first attempt put the input behind a @push('scripts') block, and this
 * layout has no @stack - the block is discarded, the field would have stayed
 * hidden, and the address could never have been changed at all.
 */
test('the profile page asks for the password it requires', function () {
    $user = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('the-real-password'),
    ]);
    $client = Client::factory()->create(['email' => 'owner@example.test']);
    $user->clients()->attach($client->id);

    $this->actingAs($user)->get(route('client.account.profile'))
        ->assertOk()
        ->assertSee('name="current_password"', false)
        ->assertDontSee('@push', false);
});
