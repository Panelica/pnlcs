<?php

use App\Models\Affiliate;
use App\Models\Client;
use App\Models\User;

function makeAffiliateUser(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    return [$user, $client];
}

test('authenticated user can view affiliate page', function () {
    [$user] = makeAffiliateUser();

    $this->actingAs($user)
        ->get(route('client.affiliates.index'))
        ->assertStatus(200)
        ->assertSee('Affiliate Program');
});

test('affiliate page shows join prompt when not yet an affiliate', function () {
    [$user] = makeAffiliateUser();

    $this->actingAs($user)
        ->get(route('client.affiliates.index'))
        ->assertStatus(200)
        ->assertSee('Join Affiliate Program');
});

test('user can activate affiliate account', function () {
    [$user, $client] = makeAffiliateUser();

    $response = $this->actingAs($user)
        ->post(route('client.affiliates.activate'));

    $response->assertRedirect();
    $this->assertDatabaseHas('affiliates', [
        'client_id' => $client->id,
        'balance'   => 0,
    ]);
});

test('activating affiliate account twice does not create duplicate', function () {
    [$user, $client] = makeAffiliateUser();

    Affiliate::create([
        'client_id'  => $client->id,
        'visitors'   => 0,
        'pay_type'   => 'percentage',
        'pay_amount' => 10,
        'onetime'    => false,
        'balance'    => 0,
        'withdrawn'  => 0,
    ]);

    $this->actingAs($user)
        ->post(route('client.affiliates.activate'));

    expect(Affiliate::where('client_id', $client->id)->count())->toBe(1);
});

test('affiliate page shows stats when affiliate account exists', function () {
    [$user, $client] = makeAffiliateUser();

    Affiliate::create([
        'client_id'  => $client->id,
        'visitors'   => 42,
        'pay_type'   => 'percentage',
        'pay_amount' => 10,
        'onetime'    => false,
        'balance'    => 25.00,
        'withdrawn'  => 100.00,
    ]);

    $this->actingAs($user)
        ->get(route('client.affiliates.index'))
        ->assertStatus(200)
        ->assertSee('42')
        ->assertSee('25.00');
});

test('unauthenticated user cannot access affiliate page', function () {
    // Should redirect (to login, but redirect target may vary by guard config)
    $this->get(route('client.affiliates.index'))
        ->assertRedirect();
});

test('user without client cannot activate affiliate', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('client.affiliates.activate'));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});
