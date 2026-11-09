<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\MassPaymentService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Reports\IncomeByProductReport;
use Modules\Reports\SalesTaxLiabilityReport;

/*
 * A top-up and a payment for several invoices sell nothing.
 *
 * The money a customer adds to their balance, or pays on one invoice for
 * several, goes on to pay other invoices, and those invoices are the sale.
 * The invoice reports counted the top-up or the payment invoice as well, so
 * every amount paid that way was reported twice.
 */

beforeEach(function () {
    Carbon::setTestNow('2032-05-15 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function poaCustomer(): Client
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['credit' => 0, 'tax_exempt' => true]);
    $user->clients()->attach($client->id, ['owner' => true]);

    return $client;
}

function poaInvoice(Client $client, float $amount, string $type = 'Hosting', string $description = 'Report plan', int $qty = 1): Invoice
{
    $total = round($amount * $qty, 2);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'unpaid', 'type' => 'vat',
        'subtotal' => $total, 'total' => $total, 'credit' => 0, 'tax' => 0,
        'date' => now()->toDateString(), 'due_date' => now()->addDays(7),
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => $type,
        'description' => $description, 'amount' => $amount, 'qty' => $qty, 'taxed' => false,
    ]);

    return $invoice;
}

function poaRows(object $report): array
{
    return $report->generate(Request::create('/', 'GET', ['from' => '2032-05-01', 'to' => '2032-05-31']));
}

it('counts a sale paid from a top-up once in Income by Product', function () {
    $client = poaCustomer();
    $topUp = poaInvoice($client, 50, 'AddFunds', 'Add Funds');
    app(PaymentService::class)->applyPayment($topUp, 'banktransfer', 'poa-topup-1', null);
    expect((float) $client->fresh()->credit)->toBe(50.0);

    $sale = poaInvoice($client, 50);
    app(InvoiceService::class)->applyCredit($sale, 50);
    expect($sale->fresh()->status)->toBe('paid');

    $report = poaRows(new IncomeByProductReport);

    expect((float) collect($report['rows'])->sum('revenue'))->toEqual(50.0)
        ->and(collect($report['rows'])->pluck('product')->all())->toBe(['Report plan']);
});

it('counts invoices paid together once in Income by Product', function () {
    $client = poaCustomer();
    $a = poaInvoice($client, 30);
    $b = poaInvoice($client, 20);
    $mass = app(MassPaymentService::class)->create($client, [$a->id, $b->id])['invoice'];

    app(PaymentService::class)->applyPayment($mass, 'banktransfer', 'poa-mass-1', null);
    expect($a->fresh()->status)->toBe('paid')->and($b->fresh()->status)->toBe('paid');

    $report = poaRows(new IncomeByProductReport);

    expect((float) collect($report['rows'])->sum('revenue'))->toEqual(50.0)
        ->and((float) collect($report['rows'])->firstWhere('product', 'Report plan')->revenue)->toEqual(50.0);
});

it('counts a line by its quantity in Income by Product', function () {
    $client = poaCustomer();
    $invoice = poaInvoice($client, 40, 'Hosting', 'Report plan', 3);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'poa-qty-1', null);

    $report = poaRows(new IncomeByProductReport);

    expect((float) collect($report['rows'])->firstWhere('product', 'Report plan')->revenue)->toEqual(120.0);
});

it('leaves top-ups and payment invoices out of the Sales Tax Liability totals', function () {
    $client = poaCustomer();
    $topUp = poaInvoice($client, 50, 'AddFunds', 'Add Funds');
    app(PaymentService::class)->applyPayment($topUp, 'banktransfer', 'poa-topup-2', null);
    app(InvoiceService::class)->applyCredit(poaInvoice($client, 50), 50);

    $a = poaInvoice($client, 30);
    $b = poaInvoice($client, 20);
    $mass = app(MassPaymentService::class)->create($client, [$a->id, $b->id])['invoice'];
    app(PaymentService::class)->applyPayment($mass, 'banktransfer', 'poa-mass-2', null);

    $report = poaRows(new SalesTaxLiabilityReport);

    expect((float) $report['totals'][1])->toEqual(100.0)
        ->and((float) $report['totals'][4])->toEqual(100.0);
});

it('still counts an ordinary paid invoice in both reports', function () {
    $client = poaCustomer();
    $invoice = poaInvoice($client, 75);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'poa-plain-1', null);

    expect((float) collect(poaRows(new IncomeByProductReport)['rows'])->sum('revenue'))->toEqual(75.0)
        ->and((float) poaRows(new SalesTaxLiabilityReport)['totals'][4])->toEqual(75.0);
});

test('a line with no quantity is worth what the invoice charged for it, not nothing', function () {
    // The invoice charges max(1, qty); a bare multiplication would have made
    // this line worth 0 in the report while the customer paid 40.
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'paid', 'type' => 'vat',
        'date_paid' => now()->subDay(), 'subtotal' => 40, 'tax' => 0, 'tax2' => 0, 'total' => 40,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Hosting', 'rel_id' => 0,
        'description' => 'A line written with no quantity', 'amount' => 40, 'qty' => 0, 'taxed' => false,
    ]);

    $rows = collect((new IncomeByProductReport)->generate(new Request(['from' => now()->subWeek()->toDateString(), 'to' => now()->toDateString()]))['rows']);

    expect((float) $rows->firstWhere('product', 'A line written with no quantity')->revenue)->toBe(40.0);
});
