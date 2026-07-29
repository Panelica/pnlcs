<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\TicketDepartment;
use App\Models\User;

/**
 * What the customer is told they owe, and what they can raise a ticket about.
 *
 * The dashboard tile counted invoices at status "unpaid" only. An invoice that
 * has gone past its due date is "overdue" — so a customer with three overdue
 * bills was shown a green nought and told nothing was outstanding.
 *
 * The new-ticket form has a service picker wrapped in isset(), and the
 * controller never passed the services, so it silently never appeared.
 */
function outstandingUser(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

test('an overdue bill is counted as outstanding', function () {
    [$user, $client] = outstandingUser();

    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'overdue', 'total' => 30]);
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 10]);
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'partially_paid', 'total' => 20]);
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'paid', 'total' => 50]);

    $this->actingAs($user)->get(route('client.home'))
        ->assertOk()
        ->assertViewHas('unpaidInvoices', 3);
});

test('a customer who owes nothing is shown nothing', function () {
    [$user, $client] = outstandingUser();

    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'paid', 'total' => 50]);
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'cancelled', 'total' => 50]);

    $this->actingAs($user)->get(route('client.home'))
        ->assertOk()
        ->assertViewHas('unpaidInvoices', 0);
});

test('the new ticket form offers the services the ticket might be about', function () {
    [$user, $client] = outstandingUser();

    TicketDepartment::factory()->create(['hidden' => false]);

    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'name' => 'Business Hosting']);

    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'domain' => 'live-site.com',
        'status' => 'active',
    ]);

    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'domain' => 'gone-site.com',
        'status' => 'terminated',
    ]);

    $response = $this->actingAs($user)->get(route('client.tickets.create'))->assertOk();

    $response->assertViewHas('services', fn ($services) => $services->count() === 1);
    $response->assertSee('live-site.com')->assertDontSee('gone-site.com');
});
