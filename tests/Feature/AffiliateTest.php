<?php

use App\Models\User;
use App\Models\Client;
use App\Models\Affiliate;

test('authenticated user can view affiliate page', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    $this->actingAs($user)
        ->get(route('client.affiliates.index'))
        ->assertStatus(200);
});

test('user can activate affiliate account', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    $this->actingAs($user)
        ->post(route('client.affiliates.activate'))
        ->assertRedirect();
});

test('activating affiliate twice does not create duplicate', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    Affiliate::create(['client_id' => $client->id, 'date' => now(), 'visitors' => 0, 'balance' => 0, 'withdrawn' => 0]);

    $this->actingAs($user)
        ->post(route('client.affiliates.activate'))
        ->assertRedirect();

    expect(Affiliate::where('client_id', $client->id)->count())->toBe(1);
});
