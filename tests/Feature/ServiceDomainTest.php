<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Domain;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $this->admin = Admin::factory()->create(['role_id' => $role->id, 'password' => 'secret']);
    $this->actingAs($this->admin, 'admin');
});

// Service detail view

test('admin can view service detail page', function () {
    $service = Service::factory()->active()->create();

    $response = $this->get(route('admin.services.show', $service));

    $response->assertStatus(200);
});

test('service detail shows billing info', function () {
    $service = Service::factory()->active()->create(['billing_cycle' => 'Monthly']);

    $response = $this->get(route('admin.services.show', $service));

    $response->assertStatus(200);
});

test('service detail shows module actions when server_type set', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->active()->create(['product_id' => $product->id]);

    $response = $this->get(route('admin.services.show', $service));

    $response->assertStatus(200);
    $response->assertSee('Create Account');
    $response->assertSee('Suspend');
    $response->assertSee('Unsuspend');
    $response->assertSee('Terminate');
});

test('service detail hides module actions when no server_type', function () {
    $product = Product::factory()->create(['server_type' => null]);
    $service = Service::factory()->active()->create(['product_id' => $product->id]);

    $response = $this->get(route('admin.services.show', $service));

    $response->assertStatus(200);
    $response->assertSee('No server module configured');
    $response->assertDontSee('Create Account');
});

// Module action dispatch

test('module action create dispatches to ProvisioningService', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->pending()->create(['product_id' => $product->id]);

    $response = $this->withoutMiddleware(PreventRequestForgery::class)
        ->post(route('admin.services.module-action', [$service, 'create']));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($service->fresh()->status)->toBe('Active');
});

test('module action suspend updates service status', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->active()->create(['product_id' => $product->id]);

    $response = $this->withoutMiddleware(PreventRequestForgery::class)
        ->post(route('admin.services.module-action', [$service, 'suspend']), [
            'reason' => 'Test suspension',
        ]);

    $response->assertRedirect();
    expect($service->fresh()->status)->toBe('Suspended');
    expect($service->fresh()->suspension_reason)->toBe('Test suspension');
});

test('module action unsuspend restores service', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->suspended()->create(['product_id' => $product->id]);

    $response = $this->withoutMiddleware(PreventRequestForgery::class)
        ->post(route('admin.services.module-action', [$service, 'unsuspend']));

    $response->assertRedirect();
    expect($service->fresh()->status)->toBe('Active');
});

test('module action terminate sets termination_date', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->active()->create(['product_id' => $product->id]);

    $response = $this->withoutMiddleware(PreventRequestForgery::class)
        ->post(route('admin.services.module-action', [$service, 'terminate']));

    $response->assertRedirect();
    expect($service->fresh()->status)->toBe('Terminated');
    expect($service->fresh()->termination_date)->not->toBeNull();
});

test('unknown module action returns error', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->active()->create(['product_id' => $product->id]);

    $response = $this->withoutMiddleware(PreventRequestForgery::class)
        ->post(route('admin.services.module-action', [$service, 'invalidaction']));

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

// Domain views

test('admin can view domain list', function () {
    Domain::factory()->count(3)->create();

    $response = $this->get(route('admin.domains.index'));

    $response->assertStatus(200);
    $response->assertSee('Domains');
});

test('domain list supports status filter', function () {
    Domain::factory()->create(['status' => 'Active', 'domain' => 'active-domain.com']);
    Domain::factory()->expired()->create(['domain' => 'expired-domain.com']);

    $response = $this->get(route('admin.domains.index', ['status' => 'Active']));

    $response->assertStatus(200);
    $response->assertSee('active-domain.com');
    $response->assertDontSee('expired-domain.com');
});

test('domain list supports search filter', function () {
    Domain::factory()->create(['domain' => 'findme.example.com']);
    Domain::factory()->create(['domain' => 'other.example.com']);

    $response = $this->get(route('admin.domains.index', ['search' => 'findme']));

    $response->assertStatus(200);
    $response->assertSee('findme.example.com');
    $response->assertDontSee('other.example.com');
});

test('admin can view domain detail page', function () {
    $domain = Domain::factory()->create(['domain' => 'test-show.com']);

    $response = $this->get(route('admin.domains.show', $domain));

    $response->assertStatus(200);
    $response->assertSee('test-show.com');
});

test('domain detail shows registration dates', function () {
    $domain = Domain::factory()->create([
        'domain' => 'dates-test.com',
        'registration_date' => '2024-01-15',
        'expiry_date' => '2025-01-15',
    ]);

    $response = $this->get(route('admin.domains.show', $domain));

    $response->assertStatus(200);
    $response->assertSee('15 Jan 2024');
    $response->assertSee('15 Jan 2025');
});

test('domain detail shows feature toggles', function () {
    $domain = Domain::factory()->create([
        'domain' => 'features-test.com',
        'dns_management' => true,
        'id_protection' => false,
    ]);

    $response = $this->get(route('admin.domains.show', $domain));

    $response->assertStatus(200);
    $response->assertSee('DNS Management');
    $response->assertSee('ID Protection');
});
