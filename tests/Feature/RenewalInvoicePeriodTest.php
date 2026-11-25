<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

/*
 * A renewal is paid in advance: the invoice falls due on the day the paid-up
 * period ends, and paying it moves the due date on by one cycle. Its line
 * named the period before the due date - the one the previous invoice had
 * paid for - so a service due on 27 November was invoiced for "27 October -
 * 27 November" and read as a second bill for a month already settled.
 *
 * The end date is inclusive: the next due date is the first day the new
 * invoice covers, so a monthly period starting 1 December ends 31 December,
 * not 1 January.
 */

beforeEach(fn () => Mail::fake());

function ripService(string $cycle, string $due): Service
{
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => false]);

    return Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'status' => 'active',
        'auto_renew' => true, 'billing_cycle' => $cycle, 'amount' => 10, 'next_due_date' => $due, 'domain' => 'example.test']);
}

function ripLine(Service $service): InvoiceItem
{
    return InvoiceItem::where('type', 'Hosting')->where('rel_id', $service->id)->latest('id')->firstOrFail();
}

afterEach(fn () => Carbon::setTestNow());

test('a renewal line names the period being paid for, which starts on the due date', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    $service = ripService('Monthly', '2026-11-27');

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect(ripLine($service)->description)->toContain('2026/11/27 - 2026/12/26')
        ->and(ripLine($service)->description)->not->toContain('2026/10/27');
});

test('the period on the line ends where paying the invoice moves the due date to', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    $service = ripService('Quarterly', '2026-11-27');

    app(InvoiceGenerationService::class)->generateDueInvoices();
    $line = ripLine($service);
    app(InvoiceService::class)->markPaid(Invoice::findOrFail($line->invoice_id));

    $next = $service->fresh()->next_due_date;

    expect(Carbon::parse($next)->toDateString())->toBe('2027-02-27')
        ->and($line->description)->toContain('2026/11/27 - 2027/02/26');
});

test('a term ending on the last day of a month is not pushed into the month after', function () {
    Carbon::setTestNow('2027-01-20 10:00:00');
    $service = ripService('Monthly', '2027-01-31');

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect(ripLine($service)->description)->toContain('2027/01/31 - 2027/02/27');
});

test('a monthly period ends on the last day before the next renewal', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $service = ripService('Monthly', '2026-12-01');

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect(ripLine($service)->description)->toContain('2026/12/01 - 2026/12/31');
});

test('the first invoice for a monthly service also ends on the last paid day', function () {
    Carbon::setTestNow('2026-12-01 10:00:00');
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false]);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service',
        'product_id' => $product->id,
        'domain' => 'first.example.test',
        'amount' => 10,
        'billing_cycle' => 'Monthly',
    ]], 'banktransfer');

    $service = Service::where('order_id', $order->id)->firstOrFail();
    $line = InvoiceItem::where('type', 'Hosting')->where('rel_id', $service->id)->firstOrFail();

    expect($line->description)->toContain('2026/12/01 - 2026/12/31');
});
