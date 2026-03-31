<?php

use App\Models\Admin;
use App\Models\AdminRole;
use Livewire\Livewire;
use App\Livewire\Admin\Auth\Login;

test("admin login page loads", function () {
    $response = $this->get(route("admin.login"));
    $response->assertStatus(200);
});

test("admin can login with valid credentials via livewire", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create([
        "role_id" => $role->id,
        "username" => "testadmin",
        "password" => "secret123",
    ]);

    Livewire::test(Login::class)
        ->set("username", "testadmin")
        ->set("password", "secret123")
        ->call("login")
        ->assertHasNoErrors()
        ->assertRedirect(route("admin.dashboard"));

    $this->assertAuthenticatedAs($admin, "admin");
});

test("admin cannot login with invalid credentials", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    Admin::factory()->create([
        "role_id" => $role->id,
        "username" => "testadmin",
        "password" => "secret123",
    ]);

    Livewire::test(Login::class)
        ->set("username", "testadmin")
        ->set("password", "wrongpassword")
        ->call("login")
        ->assertHasErrors("username");

    $this->assertGuest("admin");
});

test("disabled admin cannot login", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    Admin::factory()->create([
        "role_id" => $role->id,
        "username" => "disabled",
        "password" => "secret123",
        "is_disabled" => true,
    ]);

    Livewire::test(Login::class)
        ->set("username", "disabled")
        ->set("password", "secret123")
        ->call("login")
        ->assertHasErrors("username");

    $this->assertGuest("admin");
});

test("unauthenticated admin is redirected to login", function () {
    $response = $this->get(route("admin.dashboard"));
    $response->assertRedirect(route("admin.login"));
});

test("authenticated admin can access dashboard", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);

    $response = $this->actingAs($admin, "admin")
        ->get(route("admin.dashboard"));

    $response->assertStatus(200)
        ->assertSee("Welcome to PNLCS");
});

test("admin can logout", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);

    $this->actingAs($admin, "admin")
        ->post(route("admin.logout"));

    $this->assertGuest("admin");
});

test("admin last login is updated on login", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create([
        "role_id" => $role->id,
        "username" => "logintrack",
        "password" => "secret123",
        "last_login" => null,
    ]);

    Livewire::test(Login::class)
        ->set("username", "logintrack")
        ->set("password", "secret123")
        ->call("login");

    $admin->refresh();
    expect($admin->last_login)->not->toBeNull();
});

test("admin permission middleware blocks unauthorized access", function () {
    $role = AdminRole::factory()->create([
        "permissions" => ["list_clients"],
    ]);
    $admin = Admin::factory()->create(["role_id" => $role->id]);

    Route::middleware(["web", "admin.auth", "admin.permission:delete_clients"])
        ->get("/admin/test-permission", fn () => "OK");

    $response = $this->actingAs($admin, "admin")
        ->get("/admin/test-permission");

    $response->assertStatus(403);
});

test("full admin bypasses all permission checks", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);

    Route::middleware(["web", "admin.auth", "admin.permission:nonexistent"])
        ->get("/admin/test-bypass", fn () => "OK");

    $response = $this->actingAs($admin, "admin")
        ->get("/admin/test-bypass");

    $response->assertStatus(200);
});
