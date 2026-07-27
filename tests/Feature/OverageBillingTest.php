<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Services\InvoiceGenerationService;

/**
 * Overage charges are assembled by InvoiceGenerationService, but the cron that
 * actually runs in production (pnlcs:generate-invoices) rolled its own simpler
 * loop and never called it — so a product with overage enabled and disk and
 * bandwidth rates configured billed nothing at all. Proved at runtime: two
 * services over their limits invoiced 40 instead of 540.
 */
function overageService(string $domain, array $usage = []): Service
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => null,
        'overage_enabled' => true,
        'overage_disk_rate' => 0.05,
        'overage_bw_rate' => 0.01,
        'tax' => false,
    ]);

    return Service::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => null,
        'status' => 'active',
        'next_due_date' => now()->addDays(3),
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        'domain' => $domain,
        'disk_usage' => 5000, 'disk_limit' => 1000,
        'bw_usage' => 20000, 'bw_limit' => 10000,
    ], $usage));
}

test('the renewal cron bills disk and bandwidth overage', function () {
    $service = overageService('over-limit.com');

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $invoice = Invoice::where('client_id', $service->client_id)->firstOrFail();
    $overage = InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Overage')->get();

    // 4000 MB disk over at 0.05 = 200, 10000 MB bandwidth over at 0.01 = 100.
    expect($overage)->toHaveCount(2)
        ->and((float) $overage->sum('amount'))->toBe(300.0)
        ->and((float) $invoice->fresh()->total)->toBe(320.0);
});

test('a service inside its limits is billed the plain amount', function () {
    $service = overageService('within-limit.com', [
        'disk_usage' => 500, 'disk_limit' => 1000,
        'bw_usage' => 5000, 'bw_limit' => 10000,
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $invoice = Invoice::where('client_id', $service->client_id)->firstOrFail();
    expect(InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Overage')->count())->toBe(0)
        ->and((float) $invoice->total)->toBe(20.0);
});

test('overage is not billed when the product has it disabled', function () {
    $service = overageService('no-overage.com');
    $service->product->update(['overage_enabled' => false]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $invoice = Invoice::where('client_id', $service->client_id)->firstOrFail();
    expect(InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Overage')->count())->toBe(0);
});

test('running the cron twice does not bill the customer twice', function () {
    $service = overageService('twice.com');

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();
    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $service->client_id)->count())->toBe(1);
});

test('the generation service no longer double-bills when called repeatedly', function () {
    $service = overageService('service-path.com');
    $generator = app(InvoiceGenerationService::class);

    $generator->generateDueInvoices();
    $generator->generateDueInvoices();
    $generator->generateDueInvoices();

    expect(Invoice::where('client_id', $service->client_id)->count())->toBe(1);
});
