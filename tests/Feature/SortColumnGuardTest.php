<?php

use App\Models\Admin;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Domain;
use Database\Factories\ApiCredentialFactory;

/**
 * The column a list is sorted by.
 *
 * The domains list offers four sort options and passes whatever arrives in the
 * query string to the query builder. So does getclients at the API, with
 * orderby and order. A name that is not a column does not sort by nothing - it
 * reaches the database and comes back as an error, which the visitor sees as a
 * broken page and the operator sees as noise in the log.
 *
 * The offered options are the answer: anything else falls back to them.
 */
function sortingAdmin(): Admin
{
    return Admin::factory()->create();
}

function domainToList(): Domain
{
    return Domain::create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'sortable-example.com',
        'type' => 'Register',
        'registration_period' => 1,
        'registration_date' => now()->subMonth(),
        'expiry_date' => now()->addMonths(11),
        'next_due_date' => now()->addMonths(11),
        'status' => 'active',
        'recurring_amount' => 12.99,
        'registrar' => 'enom',
    ]);
}

it('does not break the domain list on a column that does not exist', function () {
    domainToList();

    $this->actingAs(sortingAdmin(), 'admin')
        ->get(route('admin.domains.index', ['sort' => 'no_such_column']))
        ->assertOk();
});

it('does not break the domain list on a direction that is not one', function () {
    domainToList();

    $this->actingAs(sortingAdmin(), 'admin')
        ->get(route('admin.domains.index', ['sort' => 'domain', 'dir' => 'sideways']))
        ->assertOk();
});

it('still sorts by an offered column', function () {
    domainToList();

    $this->actingAs(sortingAdmin(), 'admin')
        ->get(route('admin.domains.index', ['sort' => 'expiry_date', 'dir' => 'asc']))
        ->assertOk()
        ->assertSee('sortable-example.com');
});

it('does not break the client list at the api on a column that does not exist', function () {
    Client::factory()->create();

    $credential = ApiCredential::factory()->create();

    $this->withHeaders([
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ])->getJson('/api/v1/getclients?orderby=no_such_column&order=sideways')
        ->assertSuccessful();
});
