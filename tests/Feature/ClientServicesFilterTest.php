<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;

/*
 * The services list can be narrowed by kind of product and by status.
 *
 * It was one paginated list of everything, 25 to a page: a customer with web
 * hosting, a few VPS and some cancelled services had no way to see just the
 * servers, and a filter in the page itself would only ever see one page.
 */

function csfAccount(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    $group = ProductGroup::factory()->create();

    $make = function (string $type, string $name, string $status, string $domain) use ($client, $group) {
        $product = Product::factory()->create(['group_id' => $group->id, 'type' => $type, 'name' => $name]);

        return Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'status' => $status, 'domain' => $domain]);
    };

    $make('hosting', 'Hosting Small', 'active', 'site-one.example');
    $make('vps', 'VPS Medium', 'active', 'vps-one.example');
    $make('vps', 'VPS Large', 'cancelled', 'vps-old.example');

    return [$user, $client];
}

it('lists everything when no filter is chosen, and offers the filters', function () {
    [$user] = csfAccount();

    $this->actingAs($user)->get(route('client.services.index'))
        ->assertOk()
        ->assertSee('site-one.example')->assertSee('vps-one.example')->assertSee('vps-old.example')
        ->assertSee('name="type"', false)->assertSee('name="status"', false);
});

it('shows only the chosen kind of product', function () {
    [$user] = csfAccount();

    $this->actingAs($user)->get(route('client.services.index', ['type' => 'vps']))
        ->assertOk()
        ->assertSee('vps-one.example')->assertSee('vps-old.example')
        ->assertDontSee('site-one.example');
});

it('combines kind and status', function () {
    [$user] = csfAccount();

    $this->actingAs($user)->get(route('client.services.index', ['type' => 'vps', 'status' => 'active']))
        ->assertOk()
        ->assertSee('vps-one.example')
        ->assertDontSee('vps-old.example')->assertDontSee('site-one.example');
});

it('ignores a value the account does not have', function () {
    [$user] = csfAccount();

    $this->actingAs($user)->get(route('client.services.index', ['type' => 'ssl', 'status' => 'nonsense']))
        ->assertOk()
        ->assertSee('site-one.example')->assertSee('vps-one.example');
});

it('keeps the filter on the next page', function () {
    [$user, $client] = csfAccount();
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'type' => 'vps']);
    Service::factory()->count(30)->create(['client_id' => $client->id, 'product_id' => $product->id, 'status' => 'active']);

    $this->actingAs($user)->get(route('client.services.index', ['type' => 'vps']))
        ->assertOk()
        ->assertSee('type=vps&amp;page=2', false);
});

it('does not show filters to an account with nothing to choose between', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);
    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'type' => 'hosting'])->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->get(route('client.services.index'))
        ->assertOk()
        ->assertDontSee('name="type"', false);
});
