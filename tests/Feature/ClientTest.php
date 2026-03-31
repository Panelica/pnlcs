<?php

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;


test("client migration creates table with correct columns", function () {
    $columns = Schema::getColumnListing("clients");

    expect($columns)->toContain("uuid")
        ->toContain("first_name")
        ->toContain("last_name")
        ->toContain("company_name")
        ->toContain("email")
        ->toContain("country")
        ->toContain("status")
        ->toContain("credit")
        ->toContain("deleted_at");
});

test("user_client pivot table exists", function () {
    expect(Schema::hasTable("user_client"))->toBeTrue();
});

test("client can be created with factory", function () {
    $client = Client::factory()->create();

    expect($client)->toBeInstanceOf(Client::class)
        ->and($client->uuid)->not->toBeEmpty()
        ->and($client->status)->toBe(ClientStatus::Active);
});

test("client uuid is auto-generated", function () {
    $client = Client::factory()->create(["uuid" => null]);

    expect($client->uuid)->not->toBeNull()
        ->and(strlen($client->uuid))->toBe(36);
});

test("client full name accessor works", function () {
    $client = Client::factory()->create([
        "first_name" => "Jane",
        "last_name" => "Smith",
    ]);

    expect($client->full_name)->toBe("Jane Smith");
});

test("client display name prefers company", function () {
    $client = Client::factory()->create([
        "first_name" => "Jane",
        "last_name" => "Smith",
        "company_name" => "Acme Corp",
    ]);

    expect($client->display_name)->toBe("Acme Corp");
});

test("client display name falls back to full name", function () {
    $client = Client::factory()->create([
        "first_name" => "Jane",
        "last_name" => "Smith",
        "company_name" => null,
    ]);

    expect($client->display_name)->toBe("Jane Smith");
});

test("client can be linked to user", function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    $client->users()->attach($user->id, ["owner" => true]);

    expect($client->users)->toHaveCount(1)
        ->and($client->users->first()->id)->toBe($user->id)
        ->and((bool) $client->users->first()->pivot->owner)->toBeTrue();
});

test("user can have multiple clients", function () {
    $user = User::factory()->create();
    $clients = Client::factory()->count(3)->create();

    foreach ($clients as $i => $client) {
        $user->clients()->attach($client->id, ["owner" => $i === 0]);
    }

    expect($user->clients)->toHaveCount(3);
});

test("client status enum works", function () {
    $active = Client::factory()->create(["status" => ClientStatus::Active]);
    $inactive = Client::factory()->inactive()->create();
    $closed = Client::factory()->closed()->create();

    expect($active->status)->toBe(ClientStatus::Active)
        ->and($inactive->status)->toBe(ClientStatus::Inactive)
        ->and($closed->status)->toBe(ClientStatus::Closed);
});

test("client search scope works", function () {
    Client::factory()->create(["first_name" => "TestSearch", "email" => "random@test.com"]);
    Client::factory()->create(["first_name" => "Other", "email" => "other@test.com"]);

    $results = Client::search("TestSearch")->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->first_name)->toBe("TestSearch");
});

test("client active scope works", function () {
    Client::factory()->count(3)->create(["status" => ClientStatus::Active]);
    Client::factory()->count(2)->create(["status" => ClientStatus::Inactive]);

    expect(Client::active()->count())->toBe(3);
});

test("client soft deletes work", function () {
    $client = Client::factory()->create();
    $client->delete();

    expect(Client::count())->toBe(0)
        ->and(Client::withTrashed()->count())->toBe(1);
});

test("deleting client cascades to user_client pivot", function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $client->users()->attach($user->id, ["owner" => true]);

    // Force delete to trigger cascade
    $client->forceDelete();

    expect(DB::table("user_client")->where("client_id", $client->id)->count())->toBe(0);
});
