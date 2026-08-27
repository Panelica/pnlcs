<?php

use App\Models\Admin;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;

/**
 * The applycredit API action was an alias of addcredit - two operations that
 * move money in OPPOSITE directions. Applying credit takes it off the
 * client's balance and pays an invoice down; the alias instead INCREASED the
 * balance and left the invoice untouched, while the reference screen
 * documented "clientid, invoiceid, amount" as if it worked.
 */
function creditApiHeaders(): array
{
    $admin = Admin::factory()->create();
    ApiCredential::create([
        'admin_id' => $admin->id,
        'identifier' => 'credit_key',
        'secret' => ApiCredential::hashSecret('credit_secret'),
        'active' => true,
    ]);

    return ['X-API-Key' => 'credit_key', 'X-API-Secret' => 'credit_secret'];
}

function clientWithInvoice(float $credit, float $total): array
{
    $client = Client::factory()->create(['credit' => $credit]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => $total,
        'subtotal' => $total,
        'due_date' => now()->addDays(14),
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'client_id' => $client->id,
        'type' => 'Hosting', 'description' => 'Hosting', 'amount' => $total, 'taxed' => false,
    ]);

    return [$client, $invoice];
}

it('moves money from the balance onto the invoice, not the other way round', function () {
    $headers = creditApiHeaders();
    [$client, $invoice] = clientWithInvoice(credit: 50, total: 80);

    $this->postJson('/api/v1/applycredit', [
        'invoiceid' => $invoice->id, 'amount' => 30,
    ], $headers)->assertOk();

    expect((float) $client->fresh()->credit)->toBe(20.0)
        ->and((float) $invoice->fresh()->credit)->toBe(30.0)
        ->and((float) $invoice->fresh()->total)->toBe(50.0);
});

it('marks the invoice paid when the credit covers it', function () {
    $headers = creditApiHeaders();
    [$client, $invoice] = clientWithInvoice(credit: 100, total: 80);

    $this->postJson('/api/v1/applycredit', [
        'invoiceid' => $invoice->id, 'amount' => 80,
    ], $headers)->assertOk();

    expect(strtolower((string) $invoice->fresh()->status))->toBe('paid')
        ->and((float) $client->fresh()->credit)->toBe(20.0);
});

it('refuses more than the client has instead of inventing money', function () {
    $headers = creditApiHeaders();
    [$client, $invoice] = clientWithInvoice(credit: 10, total: 80);

    $this->postJson('/api/v1/applycredit', [
        'invoiceid' => $invoice->id, 'amount' => 30,
    ], $headers)->assertStatus(400);

    expect((float) $client->fresh()->credit)->toBe(10.0)
        ->and((float) $invoice->fresh()->total)->toBe(80.0);
});

it('refuses a clientid that does not own the invoice', function () {
    $headers = creditApiHeaders();
    [, $invoice] = clientWithInvoice(credit: 50, total: 80);
    $stranger = Client::factory()->create(['credit' => 500]);

    $this->postJson('/api/v1/applycredit', [
        'clientid' => $stranger->id, 'invoiceid' => $invoice->id, 'amount' => 30,
    ], $headers)->assertStatus(400);

    expect((float) $stranger->fresh()->credit)->toBe(500.0);
});

it('leaves addcredit doing what it says: adding to the balance', function () {
    $headers = creditApiHeaders();
    $client = Client::factory()->create(['credit' => 5]);

    $this->postJson('/api/v1/addcredit', [
        'clientid' => $client->id, 'description' => 'Goodwill', 'amount' => 10,
    ], $headers)->assertOk();

    expect((float) $client->fresh()->credit)->toBe(15.0);
});
