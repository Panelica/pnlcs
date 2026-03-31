<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Product;
use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $this->admin = Admin::factory()->create(['role_id' => $role->id, 'password' => 'secret']);
    $this->actingAs($this->admin, 'admin');
});

test('createAccount sets service status to Active', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->pending()->create(['product_id' => $product->id]);

    $provisioning = app(ProvisioningService::class);
    $result = $provisioning->createAccount($service->fresh(['product']));

    expect($result['success'])->toBeTrue();
    expect($service->fresh()->status)->toBe('Active');
});

test('suspendAccount sets suspension_date and reason', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->active()->create(['product_id' => $product->id]);

    $provisioning = app(ProvisioningService::class);
    $result = $provisioning->suspendAccount($service->fresh(['product']), 'Non-payment');

    expect($result['success'])->toBeTrue();
    $fresh = $service->fresh();
    expect($fresh->status)->toBe('Suspended');
    expect($fresh->suspension_date)->not->toBeNull();
    expect($fresh->suspension_reason)->toBe('Non-payment');
});

test('unsuspendAccount clears suspension fields', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->suspended()->create(['product_id' => $product->id]);

    $provisioning = app(ProvisioningService::class);
    $result = $provisioning->unsuspendAccount($service->fresh(['product']));

    expect($result['success'])->toBeTrue();
    $fresh = $service->fresh();
    expect($fresh->status)->toBe('Active');
    expect($fresh->suspension_date)->toBeNull();
    expect($fresh->suspension_reason)->toBeNull();
});

test('terminateAccount sets termination_date', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->active()->create(['product_id' => $product->id]);

    $provisioning = app(ProvisioningService::class);
    $result = $provisioning->terminateAccount($service->fresh(['product']));

    expect($result['success'])->toBeTrue();
    $fresh = $service->fresh();
    expect($fresh->status)->toBe('Terminated');
    expect($fresh->termination_date)->not->toBeNull();
});

test('getModuleForService returns null when product has no server_type', function () {
    $product = Product::factory()->create(['server_type' => null]);
    $service = Service::factory()->create(['product_id' => $product->id]);

    $provisioning = app(ProvisioningService::class);
    $module = $provisioning->getModuleForService($service->fresh(['product']));

    expect($module)->toBeNull();
});

test('getModuleForService resolves custom module', function () {
    $product = Product::factory()->create(['server_type' => 'custom']);
    $service = Service::factory()->create(['product_id' => $product->id]);

    $provisioning = app(ProvisioningService::class);
    $module = $provisioning->getModuleForService($service->fresh(['product']));

    expect($module)->toBeInstanceOf(\App\Contracts\ServerModuleInterface::class);
});

test('createAccount returns failure when no module configured', function () {
    $product = Product::factory()->create(['server_type' => null]);
    $service = Service::factory()->create(['product_id' => $product->id]);

    $provisioning = app(ProvisioningService::class);
    $result = $provisioning->createAccount($service->fresh(['product']));

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('No server module');
});
