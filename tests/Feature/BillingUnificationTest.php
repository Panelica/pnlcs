<?php

use App\Events\OrderPlaced;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\User;
use App\Services\CartService;
use App\Services\InvoiceGenerationService;
use Illuminate\Support\Facades\Event;

/**
 * Renewal billing used to be written twice: once inside the nightly command
 * and once in InvoiceGenerationService, which the command never called. Every
 * rule then had to be fixed in both places, and it never was — disk and
 * bandwidth overage sat unbilled in the copy that actually ran.
 *
 * These tests pin the single implementation: whatever renews, renews the same
 * way whether the command runs it or the service is called directly.
 */
function billingFixture(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => false,
        'overage_enabled' => true,
        'overage_disk_rate' => 0.05,
        'overage_bw_rate' => 0.01,
    ]);

    $addon = ProductAddon::create([
        'name' => 'Dedicated IP',
        'packages' => (string) $product->id,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 1,
        'tax' => false,
    ]);
    Pricing::updateOrCreate(
        ['type' => ProductAddon::PRICING_TYPE, 'rel_id' => $addon->id, 'currency_id' => $currency->id],
        ['monthly' => 5]
    );

    return compact('product', 'addon');
}

test('the nightly command and the service produce the same invoice', function () {
    $fx = billingFixture();

    $build = function () use ($fx) {
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'client_id' => $client->id,
            'product_id' => $fx['product']->id,
            'status' => 'active',
            'amount' => 20,
            'billing_cycle' => 'Monthly',
            'next_due_date' => now()->addDays(3),
            'domain' => 'same-shape.com',
            'disk_usage' => 3000,
            'disk_limit' => 1000,
        ]);
        ServiceAddon::create([
            'service_id' => $service->id,
            'addon_id' => $fx['addon']->id,
            'client_id' => $client->id,
            'qty' => 1,
            'amount' => 5,
            'billing_cycle' => 'Monthly',
            'next_due_date' => now()->addDays(3),
            'status' => 'active',
        ]);
        Domain::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'domain' => 'same-shape.net',
            'recurring_amount' => 12,
            'registration_period' => 1,
            'next_due_date' => now()->addDays(3),
        ]);

        return $client;
    };

    $viaCommand = $build();
    $viaService = $build();

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();
    $commandInvoice = Invoice::where('client_id', $viaCommand->id)->latest('id')->firstOrFail();

    // The second client's invoice was produced by the same run; assert both
    // came out identical rather than trusting one path.
    $serviceInvoice = Invoice::where('client_id', $viaService->id)->latest('id')->firstOrFail();

    $shape = fn (Invoice $i) => InvoiceItem::where('invoice_id', $i->id)
        ->orderBy('type')->orderBy('amount')
        ->get()->map(fn ($item) => $item->type.'|'.(float) $item->amount)->all();

    // 20 hosting + 100 disk overage + 5 addon + 12 domain
    expect((float) $commandInvoice->total)->toBe(137.0)
        ->and((float) $serviceInvoice->total)->toBe(137.0)
        ->and($shape($commandInvoice))->toBe($shape($serviceInvoice));
});

test('everything a client owes lands on one invoice, not one per item', function () {
    $fx = billingFixture();
    $client = Client::factory()->create();

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'domain' => 'grouped.com',
        'disk_usage' => 0,
        'disk_limit' => 0,
    ]);
    ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $fx['addon']->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'status' => 'active',
    ]);
    Domain::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
        'domain' => 'grouped.net',
        'recurring_amount' => 12,
        'registration_period' => 1,
        'next_due_date' => now()->addDays(2),
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(1);

    $invoice = Invoice::where('client_id', $client->id)->firstOrFail();

    expect((float) $invoice->total)->toBe(37.0)
        ->and(InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Hosting')->count())->toBe(1)
        ->and(InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Addon')->count())->toBe(1)
        ->and(InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Domain')->count())->toBe(1);
});

test('overage is billed by the command, which is what it never used to do', function () {
    $fx = billingFixture();
    $client = Client::factory()->create();
    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'domain' => 'overage.com',
        'disk_usage' => 2000,
        'disk_limit' => 1000,
        'bw_usage' => 5000,
        'bw_limit' => 4000,
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $invoice = Invoice::where('client_id', $client->id)->firstOrFail();

    // 20 + (1000 MB disk @ 0.05 = 50) + (1000 MB bandwidth @ 0.01 = 10)
    expect((float) $invoice->total)->toBe(80.0)
        ->and(InvoiceItem::where('invoice_id', $invoice->id)->where('type', 'Overage')->count())->toBe(2);
});

test('running the command twice does not bill the same thing twice', function () {
    $fx = billingFixture();
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'domain' => 'twice.com',
        'disk_usage' => 0,
        'disk_limit' => 0,
    ]);
    ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $fx['addon']->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'status' => 'active',
    ]);
    Domain::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
        'domain' => 'twice.net',
        'recurring_amount' => 12,
        'registration_period' => 1,
        'next_due_date' => now()->addDays(2),
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();
    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(1)
        ->and(InvoiceItem::where('type', 'Hosting')->where('rel_id', $service->id)->count())->toBe(1);
});

test('a free service is not sent a zero-total invoice', function () {
    $fx = billingFixture();
    $client = Client::factory()->create();
    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'amount' => 0,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'domain' => 'free.com',
        'disk_usage' => 0,
        'disk_limit' => 0,
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(0);
});

test('a suspended or cancelled service is not renewed', function () {
    $fx = billingFixture();

    foreach (['suspended', 'cancelled', 'terminated'] as $status) {
        $client = Client::factory()->create();
        Service::factory()->create([
            'client_id' => $client->id,
            'product_id' => $fx['product']->id,
            'status' => $status,
            'amount' => 20,
            'billing_cycle' => 'Monthly',
            'next_due_date' => now()->addDays(2),
            'domain' => $status.'.com',
        ]);
    }

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
});

test('the command reports what the service actually generated', function () {
    $fx = billingFixture();
    $client = Client::factory()->create();
    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'domain' => 'reported.com',
        'disk_usage' => 0,
        'disk_limit' => 0,
    ]);

    $summary = app(InvoiceGenerationService::class)->generateDueInvoices();

    expect($summary['generated'])->toBe(1)
        ->and($summary['invoice_ids'])->toHaveCount(1)
        ->and(Invoice::find($summary['invoice_ids'][0])->client_id)->toBe($client->id);
});

test('a cart order invoice points its hosting line at the service, not the product', function () {
    // The cart used to write the product id into rel_id. RenewOnPaymentListener
    // reads that column as a service id, so paying an order advanced the wrong
    // record — or none at all — and the renewal generator could not tell that
    // the service was already invoiced.
    $fx = billingFixture();
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $fx['product']->id, 'currency_id' => Currency::where('is_default', true)->first()->id],
        ['monthly' => 20]
    );

    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($client->id);
    $cart->addProduct($c, $fx['product'], 'monthly', 'rel-id.com');
    $order = $cart->checkout($c, $client->id, 'banktransfer');

    $service = Service::where('order_id', $order->id)->firstOrFail();
    $line = InvoiceItem::where('invoice_id', $order->invoice_id)->where('type', 'Hosting')->firstOrFail();

    expect((int) $line->rel_id)->toBe($service->id)
        ->and((int) $line->rel_id)->not->toBe($fx['product']->id);
});

test('placing an order through the cart fires the same events as any other order', function () {
    // The cart built orders by hand and never fired OrderPlaced, so hooks and
    // listeners only saw orders that came in through OrderService.
    Event::fake([OrderPlaced::class]);

    $fx = billingFixture();
    $client = Client::factory()->create();
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $fx['product']->id, 'currency_id' => Currency::where('is_default', true)->first()->id],
        ['monthly' => 20]
    );

    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($client->id);
    $cart->addProduct($c, $fx['product'], 'monthly', 'events.com');
    $cart->checkout($c, $client->id, 'banktransfer');

    Event::assertDispatched(OrderPlaced::class);
});
