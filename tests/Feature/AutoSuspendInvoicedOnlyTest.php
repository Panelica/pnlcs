<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;

/*
 * An overdue invoice of any kind suspends every active service the client
 * has: a late domain renewal takes down a reseller's ten servers. That is the
 * default and stays so. AutoSuspendInvoicedOnly lets an operator suspend only
 * the services on the overdue invoice - the service itself, its overage or one
 * of its addons - and unsuspend-on-payment then asks about the same debt, so
 * the two do not switch a service off and on over someone else's invoice.
 */

beforeEach(fn () => Mail::fake());

function aioService(Client $client, string $status = 'active'): Service
{
    return Service::factory()->create([
        'client_id' => $client->id, 'server_id' => null, 'status' => $status, 'override_auto_suspend_date' => null,
        'suspension_reason' => $status === 'suspended' ? 'Overdue Invoice - Automatic Suspension' : null,
        'suspension_date' => $status === 'suspended' ? now()->subDay() : null,
    ]);
}

function aioOverdue(Client $client, string $type, int $relId, string $status = 'overdue'): Invoice
{
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => $status, 'due_date' => now()->subDays(10), 'total' => 15]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => $type, 'rel_id' => $relId, 'description' => $type, 'amount' => 15, 'taxed' => false]);

    return $invoice;
}

test('off, as by default, an overdue domain renewal still suspends the client\'s services', function () {
    $client = Client::factory()->create();
    $service = aioService($client);
    aioOverdue($client, 'Domain', 0);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
});

test('on, only the services on the overdue invoice are suspended', function () {
    Setting::set('AutoSuspendInvoicedOnly', '1');
    $client = Client::factory()->create();
    $owing = aioService($client);
    $paidUp = aioService($client);
    $addonOwing = aioService($client);
    $addon = ServiceAddon::create(['service_id' => $addonOwing->id, 'client_id' => $client->id, 'status' => 'active', 'amount' => 5, 'billing_cycle' => 'Monthly']);
    aioOverdue($client, 'Hosting', $owing->id);
    aioOverdue($client, 'Addon', $addon->id);
    aioOverdue($client, 'Domain', 0);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect($owing->fresh()->status)->toBe('suspended')
        ->and($addonOwing->fresh()->status)->toBe('suspended')
        ->and($paidUp->fresh()->status)->toBe('active');
});

test('on, a paid service comes back on although another invoice of the client is still overdue', function () {
    Setting::set('AutoSuspendInvoicedOnly', '1');
    $client = Client::factory()->create();
    $service = aioService($client, 'suspended');
    aioOverdue($client, 'Hosting', $service->id, 'Paid');
    aioOverdue($client, 'Domain', 0);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();
    expect($service->fresh()->status)->toBe('active');

    // And the next morning's run leaves it on.
    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();
    expect($service->fresh()->status)->toBe('active');
});

test('on, a service still owing stays off', function () {
    Setting::set('AutoSuspendInvoicedOnly', '1');
    $client = Client::factory()->create();
    $service = aioService($client, 'suspended');
    aioOverdue($client, 'Hosting', $service->id, 'Paid');
    aioOverdue($client, 'Hosting', $service->id);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
});

test('the switch is saved from General Settings and shown there', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['AutoSuspendInvoicedOnly' => '1'])->assertSessionHasNoErrors();
    expect(Setting::get('AutoSuspendInvoicedOnly'))->toBe('1');

    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))->assertOk()
        ->assertSee('name="AutoSuspendInvoicedOnly"', false)->assertSee(__('admin.settings.auto_suspend_invoiced_only'));
});
