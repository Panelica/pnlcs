<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;

/**
 * An invoice called overdue on the morning it is due.
 *
 * invoices.due_date is a date. The command compared it against now(), so at
 * 06:30 an invoice due today - 2026-08-08 00:00 against 2026-08-08 06:30 - was
 * already past its due date and was stamped overdue, hours before the day it
 * was due had ended. The customer saw OVERDUE on an invoice they had all day to
 * pay, and every clock hanging off that status - the dunning stages, the late
 * fee, the suspension grace - started a day early.
 *
 * The same job exists, written correctly, in
 * InvoiceGenerationService::markOverdueInvoices: strictly before today.
 * Nothing called it.
 */
function invoiceDue(string $status, string $dueDate): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => $status,
        'due_date' => $dueDate,
        'total' => 50,
    ]);
}

it('leaves an invoice alone on the day it is due', function () {
    $invoice = invoiceDue(InvoiceStatus::Unpaid->value, today()->toDateString());

    $this->artisan('pnlcs:mark-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

it('marks an invoice that is actually past its due date', function () {
    $invoice = invoiceDue(InvoiceStatus::Unpaid->value, today()->subDay()->toDateString());

    $this->artisan('pnlcs:mark-overdue')
        ->expectsOutputToContain('1')
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue->value);
});

it('does not reopen an invoice that has been paid', function () {
    $invoice = invoiceDue(InvoiceStatus::Paid->value, today()->subMonth()->toDateString());

    $this->artisan('pnlcs:mark-overdue')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});
