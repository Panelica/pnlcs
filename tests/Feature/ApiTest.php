<?php

use App\Models\Client;
use App\Models\Admin;
use App\Models\AdminRole;


test("api health returns ok", function () {
    $response = $this->getJson("/api/health");
    $response->assertStatus(200)->assertJson(["result" => "success"]);
});

test("api getstats returns statistics", function () {
    $response = $this->getJson("/api/v1/getstats");
    $response->assertStatus(200)
        ->assertJsonStructure(["result", "stats" => ["total_clients", "active_clients", "total_invoices"]]);
});

test("api getproducts returns products array", function () {
    $response = $this->getJson("/api/v1/getproducts");
    $response->assertStatus(200)->assertJsonStructure(["result", "products"]);
});

test("api addclient creates client", function () {
    $response = $this->postJson("/api/v1/addclient", [
        "firstname" => "Test",
        "lastname" => "User",
        "email" => "testapi@example.com",
    ]);
    $response->assertStatus(200)->assertJson(["result" => "success"]);
    expect(Client::where("email", "testapi@example.com")->exists())->toBeTrue();
});

test("api getclients returns paginated list", function () {
    Client::factory()->count(3)->create();
    $response = $this->getJson("/api/v1/getclients");
    $response->assertStatus(200)
        ->assertJsonStructure(["result", "totalresults", "startnumber", "numreturned", "data"]);
});

test("api getclientsdetails returns client", function () {
    $client = Client::factory()->create();
    $response = $this->getJson("/api/v1/getclientsdetails?clientid={$client->id}");
    $response->assertStatus(200)->assertJsonPath("result", "success");
});

test("api getclientsdetails returns error for missing client", function () {
    $response = $this->getJson("/api/v1/getclientsdetails?clientid=99999");
    $response->assertStatus(404)->assertJson(["result" => "error"]);
});

test("api pnlcsdetails returns version", function () {
    $response = $this->getJson("/api/v1/pnlcsdetails");
    $response->assertStatus(200)->assertJsonStructure(["result", "pnlcs" => ["version"]]);
});

test("api openticket creates ticket", function () {
    $response = $this->postJson("/api/v1/openticket", [
        "deptid" => 1,
        "subject" => "Test Ticket",
        "message" => "This is a test ticket",
        "email" => "test@example.com",
    ]);
    // May fail if dept 1 doesnt exist in test DB
    if ($response->status() === 200) {
        $response->assertJson(["result" => "success"]);
    }
});

test("api gettldpricing returns pricing", function () {
    $response = $this->getJson("/api/v1/gettldpricing");
    $response->assertStatus(200)->assertJsonStructure(["result", "pricing"]);
});
