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

it('settles an invoice through the payment chain when the api sets it paid', function () {
    // Writing "paid" onto the row skipped everything a payment does: no
    // transaction, no InvoicePaid, so the order behind it was never
    // provisioned and the books showed a paid invoice nobody paid.
    Illuminate\Support\Facades\Event::fake([App\Events\InvoicePaid::class]);
    $invoice = App\Models\Invoice::factory()->create(['client_id' => App\Models\Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 40]);

    $this->withHeaders(invoiceUpdateApiHeaders())
        ->postJson('/api/v1/updateinvoice', ['invoiceid' => $invoice->id, 'status' => 'paid'])
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) App\Models\Transaction::where('invoice_id', $invoice->id)->sum('amount_in'))->toBe(40.0);
    Illuminate\Support\Facades\Event::assertDispatched(App\Events\InvoicePaid::class);
});

it('cancels an invoice through the same door the panel uses when the api sets it cancelled', function () {
    $client = App\Models\Client::factory()->create(['credit' => 0]);
    $invoice = App\Models\Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 40]);
    app(App\Services\PaymentService::class)->applyPayment($invoice, 'banktransfer', 'tx-api-part', 10.0);

    $this->withHeaders(invoiceUpdateApiHeaders())
        ->postJson('/api/v1/updateinvoice', ['invoiceid' => $invoice->id, 'status' => 'cancelled'])
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('cancelled')
        ->and((float) $client->fresh()->credit)->toBe(10.0);
});

it('updates a transaction amount without an error page', function () {
    $client = App\Models\Client::factory()->create();
    $tx = App\Models\Transaction::create(['client_id' => $client->id, 'gateway' => 'banktransfer', 'date' => now()->toDateString(), 'description' => 'x', 'amount_in' => 5, 'amount_out' => 0, 'transaction_id' => 'tx-upd']);

    $this->withHeaders(invoiceUpdateApiHeaders())
        ->postJson('/api/v1/updatetransaction', ['transactionid' => $tx->id, 'amount' => 7.5, 'description' => 'changed'])
        ->assertSuccessful();

    expect((float) $tx->fresh()->amount_in)->toBe(7.5)->and($tx->fresh()->description)->toBe('changed');
});
