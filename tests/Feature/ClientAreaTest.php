<?php

use App\Models\User;
use App\Models\Client;


test("client login page loads", function () {
    $response = $this->get(route("client.login"));
    $response->assertStatus(200)->assertSee("Sign in to your account");
});

test("client register page loads", function () {
    $response = $this->get(route("client.register"));
    $response->assertStatus(200)->assertSee("Create your account");
});

test("client can register", function () {
    $response = $this->post(route("client.register.submit"), [
        "first_name" => "New",
        "last_name" => "Client",
        "email" => "newclient@example.com",
        "password" => "password123",
        "password_confirmation" => "password123",
    ]);
    $response->assertRedirect(route("client.home"));
    $this->assertAuthenticated();
    expect(User::where("email", "newclient@example.com")->exists())->toBeTrue();
    expect(Client::where("email", "newclient@example.com")->exists())->toBeTrue();
});

test("client can login", function () {
    $user = User::factory()->create(["email" => "login@test.com", "password" => "secret123"]);
    $response = $this->post(route("client.login.submit"), [
        "email" => "login@test.com",
        "password" => "secret123",
    ]);
    $response->assertRedirect(route("client.home"));
    $this->assertAuthenticatedAs($user);
});

test("client cannot login with wrong password", function () {
    User::factory()->create(["email" => "fail@test.com", "password" => "secret123"]);
    $response = $this->post(route("client.login.submit"), [
        "email" => "fail@test.com",
        "password" => "wrong",
    ]);
    $response->assertSessionHasErrors("email");
    $this->assertGuest();
});

test("unauthenticated client redirected to login", function () {
    $response = $this->get(route("client.home"));
    $response->assertRedirect();
});

test("authenticated client can access dashboard", function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route("client.home"));
    $response->assertStatus(200)->assertSee("Welcome");
});

test("client can logout", function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route("client.logout"));
    $this->assertGuest();
});
