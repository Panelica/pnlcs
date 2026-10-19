<?php

use App\Events\InvoicePaid;
use App\Listeners\RenewOnPaymentListener;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Modules\Servers\Custom\CustomModule;

/*
 * A paid renewal moved the due date and told the service's module nothing.
 * For a hosting account that is the whole renewal; a license bought per
 * period from a vendor lapses at the vendor unless that period is bought too.
 * A server module offering renew() is now called after the date has moved.
 */

class RenewingTestModule extends CustomModule
{
    public static array $calls = [];

    public static bool $refuse = false;

    public function renew(Service $service): array
    {
        self::$calls[] = ['id' => $service->id, 'due' => $service->fresh()->next_due_date?->toDateString()];

        return self::$refuse ? ['success' => false, 'message' => 'vendor balance too low'] : ['success' => true, 'message' => 'renewed'];
    }

    public function getModuleName(): string
    {
        return 'renewingtest';
    }
}

function renewalPaid(string $serverType, string $status = 'active', bool $fromOrder = false): Service
{
    app(ModuleRegistry::class)->registerServer('renewingtest', RenewingTestModule::class);
    $client = Client::factory()->create();
    $product = Product::factory()->create(['server_type' => $serverType]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'status' => $status,
        'billing_cycle' => 'Monthly', 'next_due_date' => '2026-11-01',
    ]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'paid', 'total' => 10]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Hosting', 'rel_id' => $service->id, 'description' => 'Renewal', 'amount' => 10]);
    if ($fromOrder) {
        Order::factory()->create(['client_id' => $client->id, 'invoice_id' => $invoice->id]);
    }

    app(RenewOnPaymentListener::class)->handleInvoicePaid(new InvoicePaid($invoice->fresh()));

    return $service;
}

beforeEach(function () {
    RenewingTestModule::$calls = [];
    RenewingTestModule::$refuse = false;
});

test('a paid renewal is passed to a module that renews, after the date has moved', function () {
    $service = renewalPaid('renewingtest');

    expect(RenewingTestModule::$calls)->toBe([['id' => $service->id, 'due' => '2026-12-01']]);
});

test('a module without renew is left alone, and the date still moves', function () {
    $service = renewalPaid('custom');

    expect(RenewingTestModule::$calls)->toBe([])
        ->and($service->fresh()->next_due_date->toDateString())->toBe('2026-12-01');
});

test('a new order\'s invoice and a terminated service are not renewed at the vendor', function () {
    renewalPaid('renewingtest', fromOrder: true);
    renewalPaid('renewingtest', status: 'terminated');

    expect(RenewingTestModule::$calls)->toBe([]);
});

test('a refusal is raised for staff, and the customer keeps the paid period', function () {
    RenewingTestModule::$refuse = true;
    $raised = [];
    add_hook('ModuleActionFailed', 1, function ($vars) use (&$raised) { $raised[] = $vars; });

    $service = renewalPaid('renewingtest');

    expect($raised[0]['action'] ?? null)->toBe('renew')
        ->and($raised[0]['error'] ?? null)->toBe('vendor balance too low')
        ->and($service->fresh()->next_due_date->toDateString())->toBe('2026-12-01');
});
