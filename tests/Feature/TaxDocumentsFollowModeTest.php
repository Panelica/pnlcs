<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Modules\Ksef\Support\InvoiceXmlBuilder;

/*
 * Review of #312: the invoice documents must say what the invoice charged.
 * The PDF, the client and admin invoice pages and KSeF each rebuilt the VAT
 * rows from the lines as amount × rate / 100, so with prices that include tax
 * a 6.00 line at 20% printed "6.00 net, 1.20 VAT" on an invoice of 6.00, and
 * with tax off a line carrying its own rate still printed a VAT row. They now
 * read Invoice::vatBreakdown()/lineTax(), under the tax mode the invoice was
 * issued with - which stays, whatever the settings say later.
 */

function tdfInvoice(array $lines, ?float $invoiceRate = null): Invoice
{
    $client = Client::factory()->create(['tax_exempt' => false, 'country' => 'PL']);
    if ($invoiceRate !== null) {
        TaxRule::create(['name' => 'VAT', 'country' => $client->country, 'state' => '', 'tax_rate' => $invoiceRate, 'is_default' => true]);
    }

    return app(InvoiceService::class)->createInvoice($client, $lines);
}

function tdfDocs(Invoice $invoice): array
{
    $user = User::factory()->create();
    $user->clients()->attach($invoice->client_id);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $invoice->load('client', 'items');

    return [
        'pdf' => view('pdf.invoice', ['invoice' => $invoice, 'company' => app(InvoicePdfService::class)->companyDetails(), 'ksef' => null])->render(),
        'client' => test()->actingAs($user)->get(route('client.invoices.show', $invoice))->getContent(),
        'admin' => test()->actingAs($admin, 'admin')->get(route('admin.invoices.show', $invoice))->getContent(),
        'ksef' => (new InvoiceXmlBuilder)->build($invoice, '1234567890'),
    ];
}

test('with prices that include tax every document shows 5.00 net and 1.00 VAT on a 6.00 line', function () {
    Setting::set('TaxInclusivePrices', '1');
    $invoice = tdfInvoice([['description' => 'Plan', 'amount' => 6.00, 'taxed' => true]], 20);

    expect($invoice->tax_mode)->toBe('inclusive')->and((float) $invoice->total)->toBe(6.0)
        ->and($invoice->vatBreakdown())->toBe(['20%' => ['amount' => 1.0, 'rate' => 20.0, 'net' => 5.0]]);

    $docs = tdfDocs($invoice);
    foreach (['pdf', 'client', 'admin'] as $doc) {
        expect($docs[$doc])->toContain(invoice_money_fmt(1.0, $invoice))->not->toContain(invoice_money_fmt(1.2, $invoice));
    }
    expect($docs['ksef'])->toContain('<P_11>5.00</P_11>')->toContain('<P_11Vat>1.00</P_11Vat>')->toContain('<P_9A>5.00</P_9A>');
});

test('an invoice keeps the mode it was issued with when the settings change', function () {
    Setting::set('TaxInclusivePrices', '1');
    $invoice = tdfInvoice([['description' => 'Plan', 'amount' => 6.00, 'taxed' => true]], 20);
    Setting::set('TaxInclusivePrices', '0');

    $again = app(InvoiceService::class)->recalculateTotals($invoice->fresh());

    expect((float) $again->total)->toBe(6.0)->and((float) $again->tax)->toBe(1.0)
        ->and($again->vatBreakdown()['20%']['net'])->toBe(5.0);
});

test('with tax off a line carrying its own rate gets no VAT row anywhere', function () {
    Setting::set('TaxEnabled', '0');
    $invoice = tdfInvoice([['description' => 'Work', 'amount' => 10.00, 'taxed' => true, 'tax_rate' => 20]]);

    expect($invoice->tax_mode)->toBe('none')->and((float) $invoice->tax)->toBe(0.0)->and($invoice->vatBreakdown())->toBe([]);
    $docs = tdfDocs($invoice);
    foreach (['pdf', 'client', 'admin'] as $doc) {
        expect($docs[$doc])->not->toContain(invoice_money_fmt(2.0, $invoice));
    }
    expect($docs['ksef'])->not->toContain('<P_11Vat>2.00</P_11Vat>');
});

test('per-line rates round the sum once, as before', function (array $lines, float $tax) {
    $invoice = tdfInvoice($lines);

    expect((float) $invoice->tax)->toBe($tax);
})->with([
    '3 × 3.35 at 15%' => [[['description' => 'A', 'amount' => 3.35, 'qty' => 3, 'taxed' => true, 'tax_rate' => 15]], 1.51],
    '0.25 + 0.25 at 10%' => [[['description' => 'A', 'amount' => 0.25, 'taxed' => true, 'tax_rate' => 10], ['description' => 'B', 'amount' => 0.25, 'taxed' => true, 'tax_rate' => 10]], 0.05],
]);

test('by default every document reads as before', function () {
    $invoice = tdfInvoice([['description' => 'Plan', 'amount' => 6.00, 'taxed' => true]], 20);

    expect($invoice->tax_mode)->toBeNull()->and((float) $invoice->total)->toBe(7.2)
        ->and($invoice->vatBreakdown())->toBe(['20%' => ['amount' => 1.2, 'rate' => 20.0, 'net' => 6.0]]);
    expect(tdfDocs($invoice)['ksef'])->toContain('<P_11>6.00</P_11>')->toContain('<P_11Vat>1.20</P_11Vat>');
});

test('the admin invoice builder previews the mode it will save with', function () {
    Setting::set('TaxInclusivePrices', '1');
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.invoices.create'))->assertOk()
        ->assertSee('taxMode: "inclusive"', false);
});
