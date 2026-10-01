<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;

/*
 * The service page shows the server's IP address.
 *
 * It read $service->server->ip, but the servers table calls the column
 * ip_address and the model has no "ip" accessor, so the row was never shown:
 * a customer could not find the address to point their domain at.
 */

it('shows the IP address of the server the service is on', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $server = Server::factory()->create(['ip_address' => '203.0.113.47']);
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id])->id,
        'server_id' => $server->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->get(route('client.services.show', $service))
        ->assertOk()
        ->assertSee('203.0.113.47');
});
