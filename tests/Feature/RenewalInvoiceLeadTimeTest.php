<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Setting;
use App\Services\InvoiceGenerationService;

/**
 * Renewals were invoiced config('billing.invoice_days_before_due', 14) days
 * ahead. That config key does not exist, so it was always 14 days and no
 * setting could change it.
 */
function leadTimeService(int $dueInDays): Service
{
    Currency::where('is_default', true)->exists()
        || Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => false]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create(['tax_exempt' => true])->id,
        'product_id' => $product->id, 'status' => 'active', 'amount' => 20, 'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays($dueInDays), 'domain' => uniqid().'.example.com',
    ]);
}

function invoicedFor(Service $service): bool
{
    return Invoice::where('client_id', $service->client_id)->exists();
}

it('keeps the 14 day lead time when nothing is set', function () {
    $soon = leadTimeService(10);
    $later = leadTimeService(20);

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect(invoicedFor($soon))->toBeTrue()
        ->and(invoicedFor($later))->toBeFalse();
});

it('invoices renewals as many days ahead as the setting says', function () {
    Setting::set('InvoiceDaysBeforeDue', '3');
    $inside = leadTimeService(2);
    $outside = leadTimeService(5);

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect(invoicedFor($inside))->toBeTrue()
        ->and(invoicedFor($outside))->toBeFalse();
});

it('saves the lead time from General Settings', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))
        ->assertOk()->assertSee('name="InvoiceDaysBeforeDue"', false);

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['InvoiceDaysBeforeDue' => '7']);

    expect(Setting::get('InvoiceDaysBeforeDue'))->toBe('7');
});
