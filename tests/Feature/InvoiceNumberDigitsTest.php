<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoiceService;

/*
 * {num} in a numbering scheme was always written with six digits. A shop that
 * issues a few hundred invoices a year wanted INV-0042, not INV-000042
 * (community.pnlcs.com/t/33). {num:4} names the width; {num} is six as before.
 */

function indInvoice(string $number, string $type = 'vat'): Invoice
{
    return Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'invoice_num' => $number, 'type' => $type]);
}

test('{num} is still six digits', function () {
    expect(app(InvoiceService::class)->renderInvoiceNumber('INV-{num}', 42))->toBe('INV-000042');
});

test('{num:4} pads to four digits, and any width from 1 to 9 is taken', function () {
    $numbers = app(InvoiceService::class);

    expect($numbers->renderInvoiceNumber('INV-{num:4}', 42))->toBe('INV-0042')
        ->and($numbers->renderInvoiceNumber('{num:1}', 7))->toBe('7')
        ->and($numbers->renderInvoiceNumber('F{num:9}', 7))->toBe('F000000007');
});

test('a series that has outgrown its width is written in full, never cut short', function () {
    expect(app(InvoiceService::class)->renderInvoiceNumber('INV-{num:4}', 12345))->toBe('INV-12345');
});

test('something that is not a width is left as it was typed', function () {
    $numbers = app(InvoiceService::class);

    expect($numbers->renderInvoiceNumber('INV-{num:0}', 42))->toBe('INV-{num:0}')
        ->and($numbers->renderInvoiceNumber('INV-{num:12}', 42))->toBe('INV-{num:12}');
});

test('changing the width continues the same series', function () {
    Setting::set('ProformaEnabled', '0');
    Setting::set('InvoiceNumberFormat', 'INV-{num:4}');
    indInvoice('INV-000041');

    $numbers = app(InvoiceService::class);

    expect($numbers->nextInvoiceSequence())->toBe(42)
        ->and($numbers->generateInvoiceNumber())->toBe('INV-0042');
});

test('a width works with the number in the middle of the scheme', function () {
    Setting::set('ProformaEnabled', '0');
    Setting::set('InvoiceNumberFormat', 'F-{num:3}/{year}');
    indInvoice('F-041/'.now()->format('Y'));

    expect(app(InvoiceService::class)->generateInvoiceNumber())->toBe('F-042/'.now()->format('Y'));
});
