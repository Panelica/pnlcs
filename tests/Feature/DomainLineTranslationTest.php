<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\OrderService;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

function domainLineInvoice(string $status = 'unpaid', string $language = 'pl'): Invoice
{
    $client = Client::factory()->create(['language' => $language]);

    return Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => $status,
        'language' => $language,
    ]);
}

function domainLine(Invoice $invoice, string $description): InvoiceItem
{
    return InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $invoice->client_id,
        'type' => 'Domain',
        'rel_id' => 1,
        'description' => $description,
        'amount' => 12.50,
        'qty' => 1,
    ]);
}

it('translates the old domain registration, renewal and transfer lines', function () {
    $invoice = domainLineInvoice();
    $registration = domainLine($invoice, 'Domain Registration: twist.tw — 1 Year(s) + WHOIS privacy');
    $renewal = domainLine($invoice, 'Domain Renewal: beetsma.pl (1y)');
    $transfer = domainLine($invoice, 'Domain Transfer: move-me.com — 2 Year(s)');

    $this->artisan('pnlcs:translate-domain-lines')->assertSuccessful();

    expect($registration->fresh()->description)->toBe('Rejestracja domeny: twist.tw — 1 rok + Prywatność WHOIS')
        ->and($renewal->fresh()->description)->toBe('Odnowienie domeny: beetsma.pl (1 rok)')
        ->and($transfer->fresh()->description)->toBe('Transfer domeny: move-me.com — 2 lata')
        ->and((float) $registration->fresh()->amount)->toBe(12.5);
});

it('leaves paid invoices alone unless asked', function () {
    $invoice = domainLineInvoice('paid');
    $line = domainLine($invoice, 'Domain Registration: twist.tw — 1 Year(s)');

    $this->artisan('pnlcs:translate-domain-lines')->assertSuccessful();
    expect($line->fresh()->description)->toBe('Domain Registration: twist.tw — 1 Year(s)');

    $this->artisan('pnlcs:translate-domain-lines', ['--include-paid' => true])->assertSuccessful();
    expect($line->fresh()->description)->toBe('Rejestracja domeny: twist.tw — 1 rok');
});

it('does not save anything in a dry run', function () {
    $invoice = domainLineInvoice();
    $line = domainLine($invoice, 'Domain Renewal: beetsma.pl (1y)');

    $this->artisan('pnlcs:translate-domain-lines', ['--dry-run' => true])->assertSuccessful();

    expect($line->fresh()->description)->toBe('Domain Renewal: beetsma.pl (1y)');
});

it('is idempotent on already translated lines', function () {
    $invoice = domainLineInvoice();
    $line = domainLine($invoice, 'Rejestracja domeny: twist.tw — 1 rok');

    $this->artisan('pnlcs:translate-domain-lines')->assertSuccessful();

    expect($line->fresh()->description)->toBe('Rejestracja domeny: twist.tw — 1 rok');
});

it('writes a new domain order line in the client language', function () {
    $client = Client::factory()->create(['language' => 'pl']);

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'domain',
        'domain' => 'new-domain.test',
        'domain_type' => 'register',
        'registration_period' => 1,
        'amount' => 10,
    ]], 'banktransfer');

    $line = InvoiceItem::where('invoice_id', $order->invoice_id)
        ->where('type', 'Domain')
        ->firstOrFail();

    expect($line->description)->toContain('Rejestracja domeny: new-domain.test')
        ->and($line->description)->toContain('(1 rok)');
});
