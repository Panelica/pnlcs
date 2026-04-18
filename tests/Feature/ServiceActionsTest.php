<?php

use App\Models\Client;
use App\Models\Service;
use App\Models\User;


function makeAuthUserWithClient(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    return [$user, $client];
}


test('authenticated user can view service detail', function () {
    [$user, $client] = makeAuthUserWithClient();
    $service = Service::factory()->create(['client_id' => $client->id, 'status' => 'Active']);

    $this->actingAs($user)
        ->get(route('client.services.show', $service))
        ->assertStatus(200)
        ->assertSee($service->domain);
});

test('user cannot view service belonging to another client', function () {
    [$user] = makeAuthUserWithClient();
    $otherService = Service::factory()->create();

    $this->actingAs($user)
        ->get(route('client.services.show', $otherService))
        ->assertStatus(403);
});

test('authenticated user can view cancellation form', function () {
    [$user, $client] = makeAuthUserWithClient();
    $service = Service::factory()->create(['client_id' => $client->id, 'status' => 'Active']);

    $this->actingAs($user)
        ->get(route('client.services.cancel', $service))
        ->assertStatus(200)
        ->assertSee('Request Cancellation');
});

test('user cannot view cancellation form for another client service', function () {
    [$user] = makeAuthUserWithClient();
    $otherService = Service::factory()->create();

    $this->actingAs($user)
        ->get(route('client.services.cancel', $otherService))
        ->assertStatus(403);
});

test('authenticated user can submit cancellation request', function () {
    [$user, $client] = makeAuthUserWithClient();
    $service = Service::factory()->create(['client_id' => $client->id, 'status' => 'Active']);

    $response = $this->actingAs($user)->post(route('client.services.cancel.submit', $service), [
        'type'   => 'Immediate',
        'reason' => 'No longer needed.',
    ]);

    $response->assertRedirect(route('client.services.show', $service));
    $this->assertDatabaseHas('cancellation_requests', [
        'service_id' => $service->id,
        'type'       => 'Immediate',
    ]);
});

test('cancellation request validates required fields', function () {
    [$user, $client] = makeAuthUserWithClient();
    $service = Service::factory()->create(['client_id' => $client->id]);

    $this->actingAs($user)
        ->post(route('client.services.cancel.submit', $service), [])
        ->assertSessionHasErrors(['type', 'reason']);
});

test('cancellation request rejects invalid type', function () {
    [$user, $client] = makeAuthUserWithClient();
    $service = Service::factory()->create(['client_id' => $client->id]);

    $this->actingAs($user)
        ->post(route('client.services.cancel.submit', $service), [
            'type'   => 'Invalid',
            'reason' => 'Test reason',
        ])
        ->assertSessionHasErrors(['type']);
});

test('user cannot submit cancellation for another client service', function () {
    [$user] = makeAuthUserWithClient();
    $otherService = Service::factory()->create();

    $this->actingAs($user)
        ->post(route('client.services.cancel.submit', $otherService), [
            'type'   => 'Immediate',
            'reason' => 'Test',
        ])
        ->assertStatus(403);
});

test('authenticated user can view upgrade page', function () {
    [$user, $client] = makeAuthUserWithClient();
    $service = Service::factory()->create(['client_id' => $client->id]);

    $this->actingAs($user)
        ->get(route('client.services.upgrade', $service))
        ->assertStatus(200)
        ->assertSee('Upgrade');
});

test('unauthenticated user cannot access service actions', function () {
    $service = Service::factory()->create();

    $this->get(route('client.services.show', $service))->assertRedirect();
    $this->get(route('client.services.cancel', $service))->assertRedirect();
    $this->get(route('client.services.upgrade', $service))->assertRedirect();
});
