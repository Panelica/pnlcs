<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Invoice;
use Database\Factories\ApiCredentialFactory;

/**
 * The money half of the unchecked status.
 *
 * updateinvoice copied status and due_date onto the record with nothing
 * checked, and invoices.status is not cast to the InvoiceStatus enum. Every
 * part of collecting the money reads that field: the overdue run marks unpaid
 * invoices, the late fee and the suspension act on overdue ones, the reminders
 * go out for unpaid and overdue, and the client area lists what is owed.
 *
 * A status outside the nine the panel knows - 'Pending' carried over from
 * another system, a typo - leaves the invoice in none of those. The customer
 * owes the money and is never asked for it again, and the invoice does not even
 * appear as outstanding.
 *
 * due_date is the clock all of that runs on, and it was taking any string at
 * all.
 */
function invoiceUpdateApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function apiEditableInvoice(): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => 'unpaid',
        'due_date' => today()->addWeek()->toDateString(),
        'total' => 120,
    ]);
}

it('refuses an invoice status the collection runs would not recognise', function () {
    $invoice = apiEditableInvoice();

    $this->withHeaders(invoiceUpdateApiHeaders())->postJson('/api/v1/updateinvoice', [
        'invoiceid' => $invoice->id,
        'status' => 'Pending',
    ])->assertStatus(422);

    expect($invoice->fresh()->status)->toBe('unpaid');
});

it('still accepts a status the panel uses', function () {
    $invoice = apiEditableInvoice();

    $this->withHeaders(invoiceUpdateApiHeaders())->postJson('/api/v1/updateinvoice', [
        'invoiceid' => $invoice->id,
        'status' => 'cancelled',
    ])->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('cancelled');
});

it('refuses a due date that is not a date', function () {
    $invoice = apiEditableInvoice();

    $this->withHeaders(invoiceUpdateApiHeaders())->postJson('/api/v1/updateinvoice', [
        'invoiceid' => $invoice->id,
        'due_date' => 'end of the month',
    ])->assertStatus(422);

    expect($invoice->fresh()->due_date->toDateString())->toBe(today()->addWeek()->toDateString());
});

it('still moves the due date when given a real one', function () {
    $invoice = apiEditableInvoice();
    $when = today()->addMonth()->toDateString();

    $this->withHeaders(invoiceUpdateApiHeaders())->postJson('/api/v1/updateinvoice', [
        'invoiceid' => $invoice->id,
        'due_date' => $when,
    ])->assertSuccessful();

    expect($invoice->fresh()->due_date->toDateString())->toBe($when);
});

it('still changes the fields it was always free to change', function () {
    $invoice = apiEditableInvoice();

    $this->withHeaders(invoiceUpdateApiHeaders())->postJson('/api/v1/updateinvoice', [
        'invoiceid' => $invoice->id,
        'notes' => 'Paid by bank transfer, reference 4471.',
    ])->assertSuccessful();

    expect($invoice->fresh()->notes)->toBe('Paid by bank transfer, reference 4471.');
});
