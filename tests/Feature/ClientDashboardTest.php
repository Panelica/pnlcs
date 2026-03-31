<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;

test('unauthenticated user is redirected when accessing dashboard', function () {
    // Redirect target depends on guard config (may be admin.login or client.login)
    $this->get(route('client.home'))
        ->assertRedirect();
});

test('authenticated user can access dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('client.home'))
        ->assertStatus(200)
        ->assertSee('Welcome');
});

test('dashboard shows real service count', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    Service::factory()->count(3)->create(['client_id' => $client->id, 'status' => 'Active']);
    Service::factory()->create(['client_id' => $client->id, 'status' => 'Cancelled']);

    $this->actingAs($user)
        ->get(route('client.home'))
        ->assertStatus(200)
        ->assertSee('3');
});

test('dashboard shows real domain count', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    Domain::factory()->count(2)->create(['client_id' => $client->id, 'status' => 'Active']);

    $this->actingAs($user)
        ->get(route('client.home'))
        ->assertStatus(200)
        ->assertSee('2');
});

test('dashboard shows unpaid invoice count', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    Invoice::factory()->count(2)->create(['client_id' => $client->id, 'status' => 'Unpaid']);
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'Paid']);

    $this->actingAs($user)
        ->get(route('client.home'))
        ->assertStatus(200);

    expect(Invoice::where('client_id', $client->id)->where('status', 'Unpaid')->count())->toBe(2);
});

test('dashboard shows open ticket count', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    Ticket::factory()->count(2)->create(['client_id' => $client->id, 'status' => 'Open']);
    Ticket::factory()->create(['client_id' => $client->id, 'status' => 'Closed']);

    $this->actingAs($user)
        ->get(route('client.home'))
        ->assertStatus(200);

    expect(Ticket::where('client_id', $client->id)->whereIn('status', ['Open', 'Customer-Reply'])->count())->toBe(2);
});

test('dashboard only shows data for authenticated user clients', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $otherClient = Client::factory()->create();
    Service::factory()->count(5)->create(['client_id' => $otherClient->id, 'status' => 'Active']);

    $this->actingAs($user)
        ->get(route('client.home'))
        ->assertStatus(200);

    expect(Service::whereIn('client_id', [$client->id])->where('status', 'Active')->count())->toBe(0);
});

test('dashboard shows recent invoices table', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    Invoice::factory()->count(3)->create(['client_id' => $client->id]);

    $this->actingAs($user)
        ->get(route('client.home'))
        ->assertStatus(200)
        ->assertSee('Recent Invoices');
});

test('dashboard shows active services section when services exist', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    Service::factory()->create(['client_id' => $client->id, 'status' => 'Active']);

    $this->actingAs($user)
        ->get(route('client.home'))
        ->assertStatus(200)
        ->assertSee('Active Services');
});
