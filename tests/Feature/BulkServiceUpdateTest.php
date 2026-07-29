<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceAddon;

/**
 * Changing a batch of services from the admin screen.
 *
 * The update ran straight through the query builder, which does not fire model
 * events, so everything that hangs off a service changing state was skipped.
 * Ending twenty accounts in one go left twenty addons active and billing.
 */
function bulkServices(int $count, string $status = 'active'): array
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);
    $addonProduct = ProductAddon::create([
        'name' => 'Dedicated IP', 'packages' => null,
        'hidden' => false, 'retired' => false, 'sort_order' => 1, 'tax' => false,
    ]);

    $services = [];

    for ($i = 0; $i < $count; $i++) {
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'status' => $status,
            'amount' => 20,
            'billing_cycle' => 'Monthly',
            'next_due_date' => now()->addMonth(),
            'domain' => "bulk{$i}.com",
        ]);

        ServiceAddon::create([
            'service_id' => $service->id,
            'addon_id' => $addonProduct->id,
            'client_id' => $client->id,
            'qty' => 1,
            'amount' => 5,
            'billing_cycle' => 'Monthly',
            'next_due_date' => now()->addMonth(),
            'status' => 'active',
        ]);

        $services[] = $service;
    }

    return $services;
}

test('ending services in bulk stops their addons too', function () {
    $admin = Admin::factory()->create();
    $services = bulkServices(3);

    $this->actingAs($admin, 'admin')->post(route('admin.bulk.service-update'), [
        'service_ids' => collect($services)->pluck('id')->all(),
        'status' => 'terminated',
    ])->assertRedirect();

    foreach ($services as $service) {
        expect($service->fresh()->status)->toBe('terminated');
    }

    $stillBilling = ServiceAddon::whereIn('service_id', collect($services)->pluck('id'))
        ->where('status', 'active')
        ->count();

    expect($stillBilling)->toBe(0);
});

test('a bulk termination records when it happened', function () {
    $admin = Admin::factory()->create();
    $services = bulkServices(2);

    $this->actingAs($admin, 'admin')->post(route('admin.bulk.service-update'), [
        'service_ids' => collect($services)->pluck('id')->all(),
        'status' => 'terminated',
    ])->assertRedirect();

    foreach ($services as $service) {
        expect($service->fresh()->termination_date)->not->toBeNull();
    }
});

test('a bulk suspension records when it happened and leaves addons alone', function () {
    $admin = Admin::factory()->create();
    $services = bulkServices(2);

    $this->actingAs($admin, 'admin')->post(route('admin.bulk.service-update'), [
        'service_ids' => collect($services)->pluck('id')->all(),
        'status' => 'suspended',
    ])->assertRedirect();

    foreach ($services as $service) {
        expect($service->fresh()->status)->toBe('suspended')
            ->and($service->fresh()->suspension_date)->not->toBeNull();
    }

    // Suspension is usually non-payment; the extras are still owed for.
    expect(ServiceAddon::where('status', 'active')->count())->toBe(2);
});
