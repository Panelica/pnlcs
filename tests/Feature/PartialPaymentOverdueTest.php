<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Services\PaymentService;

/*
 * A customer who pays a token amount on an invoice used to step outside the
 * whole collections chain: the invoice sat in partially_paid past its due
 * date, and the overdue marker, the reminders, the late fee and the
 * suspension job all only ever looked for unpaid or overdue.
 */

test('a partially paid invoice past its due date becomes overdue like any other', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100, 'due_date' => now()->subDays(5)]);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'tx-1', 1.0);
    expect($invoice->fresh()->status)->toBe('partially_paid');

    $this->artisan('pnlcs:mark-overdue');

    expect($invoice->fresh()->status)->toBe('overdue')
        ->and(app(PaymentService::class)->balance($invoice->fresh()))->toBe(99.0);
});

test('a partially paid invoice that is not yet due is left alone', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100, 'due_date' => now()->addDays(5)]);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'tx-2', 1.0);

    $this->artisan('pnlcs:mark-overdue');

    expect($invoice->fresh()->status)->toBe('partially_paid');
});
