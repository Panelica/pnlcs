<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Contact;
use App\Models\User;

// --- Contact Tests ---
test("contact belongs to client", function () {
    $client = Client::factory()->create();
    $contact = Contact::factory()->create(["client_id" => $client->id]);

    expect($contact->client->id)->toBe($client->id);
});

test("client has many contacts", function () {
    $client = Client::factory()->create();
    Contact::factory()->count(3)->create(["client_id" => $client->id]);

    expect($client->contacts)->toHaveCount(3);
});

test("deleting client cascades to contacts", function () {
    $client = Client::factory()->create();
    Contact::factory()->count(2)->create(["client_id" => $client->id]);

    $client->forceDelete();

    expect(Contact::count())->toBe(0);
});

// --- ClientGroup Tests ---
test("client group can be created", function () {
    $group = ClientGroup::factory()->create(["name" => "VIP", "discount_percent" => 10.50]);

    expect($group->name)->toBe("VIP")
        ->and((float) $group->discount_percent)->toBe(10.50);
});

test("client can belong to a group", function () {
    $group = ClientGroup::factory()->create();
    $client = Client::factory()->create(["group_id" => $group->id]);

    expect($group->clients)->toHaveCount(1)
        ->and($group->clients->first()->id)->toBe($client->id);
});

// --- AdminRole Tests ---
test("admin role can check permissions", function () {
    $role = AdminRole::factory()->create([
        "permissions" => ["list_clients", "add_clients", "view_invoices"],
    ]);

    expect($role->hasPermission("list_clients"))->toBeTrue()
        ->and($role->hasPermission("delete_clients"))->toBeFalse();
});

test("full admin role has all permissions", function () {
    $role = AdminRole::factory()->fullAdmin()->create();

    expect($role->hasPermission("anything"))->toBeTrue()
        ->and($role->hasPermission("delete_universe"))->toBeTrue();
});

// --- Admin Tests ---
test("admin can be created with role", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);

    expect($admin->role->id)->toBe($role->id)
        ->and($admin->uuid)->not->toBeEmpty();
});

test("admin password is hashed", function () {
    $admin = Admin::factory()->create(["password" => "secret123"]);

    expect($admin->password)->not->toBe("secret123")
        ->and(Hash::check("secret123", $admin->password))->toBeTrue();
});

test("admin permission check delegates to role", function () {
    $role = AdminRole::factory()->create([
        "permissions" => ["list_clients"],
    ]);
    $admin = Admin::factory()->create(["role_id" => $role->id]);

    expect($admin->hasPermission("list_clients"))->toBeTrue()
        ->and($admin->hasPermission("add_clients"))->toBeFalse();
});

test("admin full name accessor works", function () {
    $admin = Admin::factory()->create([
        "first_name" => "Admin",
        "last_name" => "User",
    ]);

    expect($admin->full_name)->toBe("Admin User");
});

// --- All Migrations Up/Down ---
test("all migrations can rollback and re-run", function () {
    $this->artisan("migrate:fresh", ["--force" => true])->assertExitCode(0);

    expect(Schema::hasTable("users"))->toBeTrue()
        ->and(Schema::hasTable("clients"))->toBeTrue()
        ->and(Schema::hasTable("user_client"))->toBeTrue()
        ->and(Schema::hasTable("contacts"))->toBeTrue()
        ->and(Schema::hasTable("client_groups"))->toBeTrue()
        ->and(Schema::hasTable("admin_roles"))->toBeTrue()
        ->and(Schema::hasTable("admins"))->toBeTrue()
        ->and(Schema::hasTable("admin_logs"))->toBeTrue();
});
