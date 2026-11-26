<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;

/*
 * Both lists used to be newest-id-first. A customer with several services or
 * domains could not bring the next renewal to the top. Services now default
 * to the nearest next_due_date and domains to the nearest expiry_date; the
 * name and date headers toggle direction.
 */

function sortingAccount(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

function sortingService(Client $client, string $domain, ?string $due, string $name = 'Hosting'): Service
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'type' => 'hosting',
        'name' => $name,
    ]);

    return Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'domain' => $domain,
        'next_due_date' => $due,
        'amount' => 10,
    ]);
}

function sortingDomain(Client $client, string $name, ?string $expiry): Domain
{
    return Domain::factory()->create([
        'client_id' => $client->id,
        'domain' => $name,
        'expiry_date' => $expiry,
        'next_due_date' => $expiry,
        'status' => 'active',
    ]);
}

it('sorts services by the nearest next due date by default', function () {
    [$user, $client] = sortingAccount();
    sortingService($client, 'late.example', '2026-12-20');
    sortingService($client, 'soon.example', '2026-12-01');

    $this->actingAs($user)->get(route('client.services.index'))
        ->assertOk()
        ->assertSeeInOrder(['soon.example', 'late.example']);
});

it('sorts services by domain and toggles direction', function () {
    [$user, $client] = sortingAccount();
    sortingService($client, 'beta.example', '2026-12-01');
    sortingService($client, 'alpha.example', '2026-12-02');

    $this->actingAs($user)->get(route('client.services.index', ['sort' => 'domain', 'dir' => 'asc']))
        ->assertOk()
        ->assertSeeInOrder(['alpha.example', 'beta.example']);

    $this->actingAs($user)->get(route('client.services.index', ['sort' => 'domain', 'dir' => 'desc']))
        ->assertOk()
        ->assertSeeInOrder(['beta.example', 'alpha.example']);
});

it('sorts services by next due date descending', function () {
    [$user, $client] = sortingAccount();
    sortingService($client, 'soon.example', '2026-12-01');
    sortingService($client, 'late.example', '2026-12-20');

    $this->actingAs($user)->get(route('client.services.index', ['sort' => 'expiry', 'dir' => 'desc']))
        ->assertOk()
        ->assertSeeInOrder(['late.example', 'soon.example']);
});

it('keeps services without a next due date at the end', function () {
    [$user, $client] = sortingAccount();
    sortingService($client, 'no-date.example', null);
    sortingService($client, 'dated.example', '2026-12-01');

    $this->actingAs($user)->get(route('client.services.index', ['sort' => 'expiry', 'dir' => 'asc']))
        ->assertOk()
        ->assertSeeInOrder(['dated.example', 'no-date.example']);
});

it('sorts domains by the nearest expiry date by default', function () {
    [$user, $client] = sortingAccount();
    sortingDomain($client, 'late.example', '2027-01-20');
    sortingDomain($client, 'soon.example', '2026-12-01');

    $this->actingAs($user)->get(route('client.domains.index'))
        ->assertOk()
        ->assertSeeInOrder(['soon.example', 'late.example']);
});

it('sorts domains by name and toggles direction', function () {
    [$user, $client] = sortingAccount();
    sortingDomain($client, 'beta.example', '2027-01-20');
    sortingDomain($client, 'alpha.example', '2027-01-21');

    $this->actingAs($user)->get(route('client.domains.index', ['sort' => 'name', 'dir' => 'asc']))
        ->assertOk()
        ->assertSeeInOrder(['alpha.example', 'beta.example']);

    $this->actingAs($user)->get(route('client.domains.index', ['sort' => 'name', 'dir' => 'desc']))
        ->assertOk()
        ->assertSeeInOrder(['beta.example', 'alpha.example']);
});

it('sorts domains by expiry date descending', function () {
    [$user, $client] = sortingAccount();
    sortingDomain($client, 'soon.example', '2026-12-01');
    sortingDomain($client, 'late.example', '2027-01-20');

    $this->actingAs($user)->get(route('client.domains.index', ['sort' => 'expiry', 'dir' => 'desc']))
        ->assertOk()
        ->assertSeeInOrder(['late.example', 'soon.example']);
});

it('keeps domains without an expiry date at the end', function () {
    [$user, $client] = sortingAccount();
    sortingDomain($client, 'no-date.example', null);
    sortingDomain($client, 'dated.example', '2026-12-01');

    $this->actingAs($user)->get(route('client.domains.index', ['sort' => 'expiry', 'dir' => 'asc']))
        ->assertOk()
        ->assertSeeInOrder(['dated.example', 'no-date.example']);
});

it('keeps service filters when a sort header is followed', function () {
    [$user, $client] = sortingAccount();
    sortingService($client, 'kept.example', '2026-12-01');

    $this->actingAs($user)->get(route('client.services.index', [
        'type' => 'hosting',
        'status' => 'active',
    ]))->assertOk()->assertSee('type=hosting&amp;status=active&amp;sort=expiry', false);
});
