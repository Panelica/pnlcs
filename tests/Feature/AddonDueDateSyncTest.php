<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceAddon;

/*
 * An addon follows its service. The purchase copies the service's renewal
 * date, and after the first invoice the addon edit screen locks it there.
 * A later manual change on the service used to leave the addon on the old
 * date, so the service renewed on 6 December while its addons billed on 1
 * December.
 */

function addonSyncAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'permissions' => ['view_services', 'manage_services'],
        ])->id,
    ]);
}

function addonSyncService(string $due = '2026-12-01'): Service
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'pay_type' => 'recurring',
        'server_type' => '',
    ]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'status' => 'active',
        'domain' => 'addon-sync.test',
        'amount' => 10,
        'billing_cycle' => 'Monthly',
        'next_due_date' => $due,
    ]);
}

function addonSyncProductAddon(): ProductAddon
{
    return ProductAddon::create([
        'name' => 'Dodatkowy adres IP',
        'packages' => null,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 0,
        'tax' => true,
    ]);
}

function addonSyncRow(Service $service, string $status, string $due, ?string $firstInvoice = null): ServiceAddon
{
    return ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => addonSyncProductAddon()->id,
        'client_id' => $service->client_id,
        'qty' => 1,
        'amount' => 8.60,
        'billing_cycle' => 'Monthly',
        'next_due_date' => $due,
        'first_invoice_date' => $firstInvoice,
        'status' => $status,
    ]);
}

it('moves every unfinished addon when staff change the service date', function () {
    $service = addonSyncService('2026-12-01');
    $active = addonSyncRow($service, 'active', '2026-12-01', '2026-11-25');
    $pending = addonSyncRow($service, 'pending', '2026-12-01');
    $draft = addonSyncRow($service, 'draft', '2026-12-01');
    $cancelled = addonSyncRow($service, 'cancelled', '2026-12-01');
    $terminated = addonSyncRow($service, 'terminated', '2026-12-01');

    test()->actingAs(addonSyncAdmin(), 'admin')
        ->put(route('admin.services.next-due', $service), ['next_due_date' => '2026-12-06'])
        ->assertSessionHas('success');

    expect($active->fresh()->next_due_date->toDateString())->toBe('2026-12-06')
        ->and($pending->fresh()->next_due_date->toDateString())->toBe('2026-12-06')
        ->and($draft->fresh()->next_due_date->toDateString())->toBe('2026-12-06')
        ->and($cancelled->fresh()->next_due_date->toDateString())->toBe('2026-12-01')
        ->and($terminated->fresh()->next_due_date->toDateString())->toBe('2026-12-01')
        ->and($active->fresh()->first_invoice_date->toDateString())->toBe('2026-11-25');
});

it('repairs existing mismatches in a dry run without saving', function () {
    $service = addonSyncService('2026-12-06');
    $addon = addonSyncRow($service, 'active', '2026-12-01');

    $this->artisan('pnlcs:sync-addon-due-dates', ['--dry-run' => true])->assertSuccessful();

    expect($addon->fresh()->next_due_date->toDateString())->toBe('2026-12-01');
});

it('repairs existing mismatches and leaves cancelled addons alone', function () {
    $service = addonSyncService('2026-12-06');
    $active = addonSyncRow($service, 'active', '2026-12-01');
    $draft = addonSyncRow($service, 'draft', '2026-12-01');
    $cancelled = addonSyncRow($service, 'cancelled', '2026-12-01');

    $this->artisan('pnlcs:sync-addon-due-dates')->assertSuccessful();

    expect($active->fresh()->next_due_date->toDateString())->toBe('2026-12-06')
        ->and($draft->fresh()->next_due_date->toDateString())->toBe('2026-12-06')
        ->and($cancelled->fresh()->next_due_date->toDateString())->toBe('2026-12-01');
});
