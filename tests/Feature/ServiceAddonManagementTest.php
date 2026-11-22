<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AddonService;

function pnlcs_test_service_addon(array $overrides = []): array
{
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
        'auto_renew' => true,
        'next_due_date' => now()->addMonth(),
    ]);

    $productAddon = ProductAddon::create([
        'name' => 'Extra backup',
        'packages' => null,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 0,
        'tax' => true,
    ]);

    $addon = ServiceAddon::create(array_merge([
        'service_id' => $service->id,
        'client_id' => $client->id,
        'addon_id' => $productAddon->id,
        'qty' => 1,
        'amount' => 50,
        'billing_cycle' => 'Annually',
        'next_due_date' => now()->addMonth(),
        'status' => 'active',
        'notes' => null,
    ], $overrides));

    return compact('admin', 'client', 'service', 'productAddon', 'addon');
}

it('lets an admin edit the price and the next invoice line uses it', function () {
    $fx = pnlcs_test_service_addon();

    $this->actingAs($fx['admin'], 'admin')
        ->put(route('admin.services.addons.update', [$fx['service'], $fx['addon']]), [
            'amount' => 75.50,
            'billing_cycle' => 'Annually',
            'next_due_date' => now()->addYear()->toDateString(),
            'notes' => 'new price',
        ])
        ->assertRedirect(route('admin.services.show', $fx['service']));

    $addon = $fx['addon']->fresh();

    expect((float) $addon->amount)->toBe(75.5)
        ->and($addon->notes)->toBe('new price');

    $line = app(AddonService::class)->lineItem($addon);
    expect((float) $line['amount'])->toBe(75.5);
});

it('refuses to change the price of an unpaid addon', function () {
    $fx = pnlcs_test_service_addon(['status' => 'pending']);

    $this->actingAs($fx['admin'], 'admin')
        ->put(route('admin.services.addons.update', [$fx['service'], $fx['addon']]), [
            'amount' => 99,
            'billing_cycle' => 'Annually',
            'next_due_date' => now()->addYear()->toDateString(),
        ])
        ->assertSessionHasErrors('addon');

    expect((float) $fx['addon']->fresh()->amount)->toBe(50.0);
});

it('suspends and resumes an addon without touching its service', function () {
    $fx = pnlcs_test_service_addon();
    $due = fn () => app(AddonService::class)->dueQuery(now()->addYear())->pluck('id')->all();

    expect($due())->toContain($fx['addon']->id);

    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.services.addons.suspend', [$fx['service'], $fx['addon']]))
        ->assertRedirect();

    expect(strtolower((string) $fx['addon']->fresh()->status))->toBe('suspended')
        ->and($due())->not->toContain($fx['addon']->id);

    $this->actingAs($fx['admin'], 'admin')
        ->post(route('admin.services.addons.resume', [$fx['service'], $fx['addon']]))
        ->assertRedirect();

    expect(strtolower((string) $fx['addon']->fresh()->status))->toBe('active')
        ->and($due())->toContain($fx['addon']->id);
});

it('refuses to delete an addon that already has invoice lines', function () {
    $fx = pnlcs_test_service_addon();

    $invoice = Invoice::factory()->create(['client_id' => $fx['client']->id]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $fx['client']->id,
        'type' => 'Addon',
        'rel_id' => $fx['addon']->id,
        'description' => 'Extra backup',
        'amount' => 50,
        'taxed' => true,
    ]);

    $this->actingAs($fx['admin'], 'admin')
        ->delete(route('admin.services.addons.destroy', [$fx['service'], $fx['addon']]))
        ->assertSessionHas('error');

    expect(ServiceAddon::find($fx['addon']->id))->not->toBeNull();
});

it('deletes an addon that never reached an invoice', function () {
    $fx = pnlcs_test_service_addon();

    $this->actingAs($fx['admin'], 'admin')
        ->delete(route('admin.services.addons.destroy', [$fx['service'], $fx['addon']]))
        ->assertRedirect();

    expect(ServiceAddon::find($fx['addon']->id))->toBeNull();
});

it('keeps an addon tied to its own service', function () {
    $fx = pnlcs_test_service_addon();
    $other = Service::factory()->create();

    $this->actingAs($fx['admin'], 'admin')
        ->get(route('admin.services.addons.edit', [$other, $fx['addon']]))
        ->assertNotFound();
});

it('refuses to delete a product addon that is in use', function () {
    $fx = pnlcs_test_service_addon();

    $this->actingAs($fx['admin'], 'admin')
        ->delete(route('admin.config.addons.destroy', $fx['productAddon']->id))
        ->assertSessionHas('error');

    expect(ProductAddon::find($fx['productAddon']->id))->not->toBeNull();
});
