<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Modules\Reports\AgingInvoicesReport;

/**
 * The aging report listed unpaid and overdue invoices and left out
 * partially_paid ones, which Invoice::scopeOutstanding() counts as owed. It
 * also showed only the total, so a half-paid invoice would have overstated
 * the debt.
 */
it('lists a part-paid invoice with what is still owed on it', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'partially_paid', 'subtotal' => 200, 'tax' => 0, 'total' => 200,
        'date' => now()->subDays(50)->toDateString(), 'due_date' => now()->subDays(45)->toDateString(),
    ]);
    Transaction::create([
        'client_id' => $client->id, 'invoice_id' => $invoice->id, 'gateway' => 'banktransfer',
        'date' => now()->subDays(40), 'description' => 'Part payment', 'amount_in' => 80, 'amount_out' => 0, 'fees' => 0,
        'transaction_id' => 'AGING-'.uniqid(),
    ]);

    $report = (new AgingInvoicesReport)->generate(Request::create('/'));
    $row = collect($report['rows'])->firstWhere('id', $invoice->id);

    expect($row)->not->toBeNull()
        ->and((float) $row->total)->toEqual(200.0)
        ->and((float) $row->balance)->toEqual(120.0)
        ->and($row->aging_bracket)->toBe('31-60 days')
        ->and($report['columns'])->toHaveCount(count((array) $row));
});
