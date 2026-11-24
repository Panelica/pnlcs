<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AddonService;
use App\Services\InvoiceGenerationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

/*
 * The invoice line used to print the stored cycle verbatim: a Polish customer
 * read "Hosting (monthly)" although the panel and every table say
 * "Miesięcznie". The line is written once, in the invoice's language.
 */

beforeEach(fn () => Mail::fake());

afterEach(fn () => Carbon::setTestNow());

function cycleTranslationService(string $language, string $cycle = 'Monthly'): Service
{
    $client = Client::factory()->create(['language' => $language, 'tax_exempt' => true]);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => false,
    ]);

    return Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'auto_renew' => true,
        'billing_cycle' => $cycle,
        'amount' => 10,
        'next_due_date' => now()->addDays(3),
        'domain' => 'example.test',
    ]);
}

it('writes a renewal cycle in the customer language', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    $service = cycleTranslationService('pl');

    app(InvoiceGenerationService::class)->generateDueInvoices();

    $line = InvoiceItem::where('type', 'Hosting')->where('rel_id', $service->id)->latest('id')->firstOrFail();

    expect($line->description)->toContain('(Miesięcznie)')
        ->not->toContain('(monthly)')
        ->not->toContain('(Monthly)')
        ->and($line->description)->toContain('2026/11/18 - 2026/12/17');
});

it('writes an English cycle for an English invoice', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    $service = cycleTranslationService('en');

    app(InvoiceGenerationService::class)->generateDueInvoices();

    $line = InvoiceItem::where('type', 'Hosting')->where('rel_id', $service->id)->latest('id')->firstOrFail();

    expect($line->description)->toContain('(Monthly)')
        ->not->toContain('(monthly)');
});

it('writes the cycle in an addon description in the customer language', function () {
    $client = Client::factory()->create(['language' => 'pl']);
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'domain' => 'addon.example.test',
    ]);
    $productAddon = ProductAddon::create([
        'name' => 'Extra IP',
        'packages' => null,
        'hidden' => false,
        'retired' => false,
        'sort_order' => 0,
        'tax' => true,
    ]);
    $serviceAddon = ServiceAddon::create([
        'service_id' => $service->id,
        'client_id' => $client->id,
        'addon_id' => $productAddon->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonth(),
        'status' => 'active',
    ]);

    expect(app(AddonService::class)->describe($serviceAddon))
        ->toContain('(Miesięcznie)')
        ->not->toContain('(Monthly)')
        ->not->toContain('(monthly)');
});

it('translates raw cycles in open invoice lines only', function () {
    $client = Client::factory()->create(['language' => 'pl']);
    $open = Invoice::factory()->create([
        'client_id' => $client->id,
        'language' => 'pl',
        'status' => 'unpaid',
    ]);
    $paid = Invoice::factory()->paid()->create([
        'client_id' => $client->id,
        'language' => 'pl',
    ]);

    $openLine = InvoiceItem::create([
        'invoice_id' => $open->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'rel_id' => 1,
        'description' => 'VPS (monthly) — example.test — 2026/12/01 - 2027/01/01',
        'amount' => 10,
        'taxed' => false,
    ]);
    $paidLine = InvoiceItem::create([
        'invoice_id' => $paid->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'rel_id' => 2,
        'description' => 'VPS (monthly) — old.example.test — 2026/01/01 - 2026/02/01',
        'amount' => 10,
        'taxed' => false,
    ]);

    $this->artisan('pnlcs:translate-invoice-cycles')->assertSuccessful();

    expect($openLine->fresh()->description)->toContain('(Miesięcznie)')
        ->not->toContain('(monthly)')
        ->and($paidLine->fresh()->description)->toContain('(monthly)');
});

it('reports but does not save in a dry run', function () {
    $client = Client::factory()->create(['language' => 'pl']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'language' => 'pl',
        'status' => 'unpaid',
    ]);
    $line = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'rel_id' => 1,
        'description' => 'VPS (monthly) — example.test',
        'amount' => 10,
        'taxed' => false,
    ]);

    $this->artisan('pnlcs:translate-invoice-cycles', ['--dry-run' => true])
        ->expectsOutputToContain('Would update 1')
        ->assertSuccessful();

    expect($line->fresh()->description)->toContain('(monthly)');
});
