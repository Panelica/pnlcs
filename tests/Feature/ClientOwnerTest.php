<?php

use App\Models\Client;
use App\Models\User;

/*
 * Client::owner() answers the same login whether or not the logins are in hand.
 *
 * The client page used to decide for itself which login owns the account; it
 * now asks owner(), which reads a loaded `users` relation instead of querying
 * again. The two paths have to agree, or a page would show one owner and the
 * action it posts to would act on another.
 */

test('the owner is the same login whether the relation is loaded or not', function () {
    $client = Client::factory()->create();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $client->users()->attach($owner->id, ['owner' => true]);
    $client->users()->attach($other->id, ['owner' => false]);

    // Not loaded: the query path.
    expect($client->fresh()->owner()?->id)->toBe($owner->id);

    // Loaded: read from the logins already in hand, and without a query.
    $loaded = Client::with('users')->find($client->id);
    $queries = 0;
    DB::listen(function () use (&$queries) { $queries++; });
    expect($loaded->owner()?->id)->toBe($owner->id)
        ->and($queries)->toBe(0);
});

test('an account with no owner flag has no owner, either way', function () {
    $client = Client::factory()->create();
    $client->users()->attach(User::factory()->create()->id, ['owner' => false]);

    expect($client->fresh()->owner())->toBeNull()
        ->and(Client::with('users')->find($client->id)->owner())->toBeNull();
});
