<?php

use App\Models\Client;
use App\Models\SslOrder;
use App\Models\User;

/**
 * Regression guard for the SSL client-area authorization bug.
 *
 * SslController previously scoped by auth()->id() (the User id) instead of the
 * Client id. Users and clients are a many-to-many relation with independent id
 * spaces, so that let a user reach the SSL order — including the downloadable
 * private key — of whichever client's id happened to equal their user id, while
 * hiding their own orders. These tests pin the correct client-scoped behaviour.
 *
 * Each test is built so it FAILS against the old (auth()->id()) code and passes
 * against the fixed (client-scoped) code.
 */

it('lists only the signed-in client own ssl orders', function () {
    // Diverge ids: attacker->id must differ from own client id, so the old
    // where('client_id', auth()->id()) would return none of the own orders.
    User::factory()->count(3)->create();
    $attacker = User::factory()->create();
    $ownClient = Client::factory()->create();
    $attacker->clients()->attach($ownClient->id);
    expect($attacker->id)->not->toBe($ownClient->id);

    $otherClient = Client::factory()->create();
    $own = SslOrder::create(['client_id' => $ownClient->id, 'module' => 'test', 'status' => 'Pending']);
    SslOrder::create(['client_id' => $otherClient->id, 'module' => 'test', 'status' => 'Pending']);

    $this->actingAs($attacker)
        ->get(route('client.ssl.index'))
        ->assertOk()
        ->assertViewHas('orders', fn ($orders) =>
            $orders->pluck('id')->contains($own->id) && $orders->count() === 1);
});

/**
 * Builds the exact exploit alignment: a FOREIGN client whose id equals the
 * attacker's USER id, plus the attacker's own (different) client. The old check
 * `$order->client_id !== auth()->id()` then evaluates `attackerUserId !==
 * attackerUserId` => false => access GRANTED; the fixed check compares the
 * attacker's real client id => 403.
 */
function sslExploitSetup(): array
{
    $attacker = User::factory()->create();
    $targetId = $attacker->id;

    // Ensure a foreign client exists whose id == attacker user id.
    $foreignClient = Client::find($targetId);
    if (! $foreignClient) {
        $foreignClient = Client::factory()->make();
        $foreignClient->id = $targetId;
        $foreignClient->save();
    }

    $ownClient = Client::factory()->create();
    $attacker->clients()->attach($ownClient->id);

    expect($foreignClient->id)->toBe($attacker->id);       // exploit alignment holds
    expect($ownClient->id)->not->toBe($foreignClient->id); // foreign really is foreign

    return [$attacker, $foreignClient, $ownClient];
}

it('forbids viewing a foreign order aligned to the attacker user id (exploit condition)', function () {
    [$attacker, $foreignClient] = sslExploitSetup();

    $foreign = SslOrder::create(['client_id' => $foreignClient->id, 'module' => 'test', 'status' => 'Pending']);

    $this->actingAs($attacker)
        ->get(route('client.ssl.show', $foreign))
        ->assertForbidden();
});

it('forbids downloading a foreign private key under the exploit condition', function () {
    [$attacker, $foreignClient] = sslExploitSetup();

    $foreign = SslOrder::create(['client_id' => $foreignClient->id, 'module' => 'test', 'status' => 'Completed']);

    $this->actingAs($attacker)
        ->get(route('client.ssl.download', $foreign))
        ->assertForbidden();
});
