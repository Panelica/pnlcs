<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Setting;

/*
 * A product may keep its own suspension and termination days.
 *
 * Both were general settings only (AutoSuspensionDays, AutoTermination*), so
 * a product that wants a longer grace - a website a small business depends
 * on, a service whose data is kept after it ends - changed it for every
 * hosting account too. Empty, the general settings apply as before.
 */
function daysProduct(array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'group_id' => ProductGroup::factory()->create()->id, 'type' => 'other', 'server_type' => null,
    ], $attrs));
}

/** A module-less service whose client owes an invoice $overdue days past due. */
function daysService(Product $product, int $overdue, array $attrs = []): Service
{
    $client = Client::factory()->create();
    $service = Service::factory()->create(array_merge([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => null,
        'status' => 'active', 'domain' => 'svc-'.uniqid().'.test',
    ], $attrs));

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'overdue', 'due_date' => now()->subDays($overdue), 'total' => 20]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Hosting', 'rel_id' => $service->id,
        'description' => 'x', 'amount' => 20, 'taxed' => false, 'due_date' => now()->subDays($overdue)]);

    return $service;
}

test('a product with a longer grace is not suspended on the general one', function () {
    Setting::set('AutoSuspensionDays', '3');
    $own = daysService(daysProduct(['suspend_after_days' => 14]), 5);
    $general = daysService(daysProduct(), 5);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($own->fresh()->status)->toBe('active')
        ->and($general->fresh()->status)->toBe('suspended');
});

test('it is suspended once its own grace has passed', function () {
    Setting::set('AutoSuspensionDays', '3');
    $own = daysService(daysProduct(['suspend_after_days' => 14]), 15);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($own->fresh()->status)->toBe('suspended');
});

test('a product with a shorter grace is suspended before the general one', function () {
    Setting::set('AutoSuspensionDays', '3');
    $own = daysService(daysProduct(['suspend_after_days' => 1]), 2);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($own->fresh()->status)->toBe('suspended');
});

test('a product with its own termination days is terminated even while the general switch is off', function () {
    Setting::set('AutoTerminationEnabled', '0');
    $product = daysProduct(['terminate_after_days' => 90]);
    $suspended = ['status' => 'suspended', 'suspension_reason' => 'Overdue Invoice - Automatic Suspension'];
    $old = daysService($product, 120, $suspended + ['suspension_date' => now()->subDays(100)]);
    $recent = daysService($product, 60, $suspended + ['suspension_date' => now()->subDays(45)]);
    $other = daysService(daysProduct(), 120, $suspended + ['suspension_date' => now()->subDays(100)]);

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($old->fresh()->status)->toBe('terminated')
        ->and($recent->fresh()->status)->toBe('suspended')
        // Without its own days, the general switch (off) decides.
        ->and($other->fresh()->status)->toBe('suspended');
});

test('a product with longer termination days outlasts the general ones', function () {
    Setting::set('AutoTerminationEnabled', '1');
    Setting::set('AutoTerminationDays', '30');
    $suspended = ['status' => 'suspended', 'suspension_reason' => 'Overdue Invoice - Automatic Suspension', 'suspension_date' => now()->subDays(45)];
    $own = daysService(daysProduct(['terminate_after_days' => 90]), 60, $suspended);
    $general = daysService(daysProduct(), 60, $suspended);

    $this->artisan('pnlcs:auto-terminate')->assertSuccessful();

    expect($own->fresh()->status)->toBe('suspended')
        ->and($general->fresh()->status)->toBe('terminated');
});

test('the product form saves the days, and empty goes back to the general settings', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $product = daysProduct(['pay_type' => 'recurring']);
    $form = fn (array $extra) => array_merge([
        'name' => $product->name, 'group_id' => $product->group_id, 'type' => 'other', 'pay_type' => 'recurring',
    ], $extra);

    $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product), $form(['suspend_after_days' => '14', 'terminate_after_days' => '90']))
        ->assertSessionHasNoErrors();
    expect($product->fresh()->suspend_after_days)->toBe(14)->and($product->fresh()->terminate_after_days)->toBe(90);

    $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product), $form(['suspend_after_days' => '', 'terminate_after_days' => '']))
        ->assertSessionHasNoErrors();
    expect($product->fresh()->suspend_after_days)->toBeNull()->and($product->fresh()->terminate_after_days)->toBeNull();

    $this->actingAs($admin, 'admin')->get(route('admin.products.edit', $product))->assertOk()
        ->assertSee(__('admin.products.suspend_after_days'));
});
