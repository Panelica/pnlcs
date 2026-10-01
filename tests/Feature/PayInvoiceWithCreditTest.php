<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;

/*
 * A customer can pay an open invoice from their account balance.
 *
 * Credit is applied when an invoice is created, but an invoice issued before
 * the customer topped up stayed unpaid with the money sitting on the account:
 * InvoiceService::applyCredit() was reachable only through the API.
 */

function picInvoice(float $credit, float $total = 50.0, string $itemType = 'Hosting'): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['credit' => $credit]);
    $user->clients()->attach($client->id);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => $total, 'total' => $total, 'credit' => 0,
        'due_date' => now()->addWeek(),
    ]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => $itemType, 'description' => 'Item', 'amount' => $total]);

    return [$user, $client, $invoice];
}

it('offers the balance on an open invoice and settles it in full', function () {
    [$user, $client, $invoice] = picInvoice(80.0);

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->assertSee(route('client.invoices.pay-with-credit', $invoice), false);

    $this->actingAs($user)->post(route('client.invoices.pay-with-credit', $invoice))
        ->assertRedirect(route('client.invoices.show', $invoice));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and((float) $client->fresh()->credit)->toBe(30.0);
});

it('pays part of the invoice when the balance is smaller', function () {
    [$user, $client, $invoice] = picInvoice(20.0);

    $this->actingAs($user)->post(route('client.invoices.pay-with-credit', $invoice));

    expect($invoice->fresh()->status)->not->toBe(InvoiceStatus::Paid->value)
        ->and((float) $invoice->fresh()->credit)->toBe(20.0)
        ->and((float) $client->fresh()->credit)->toBe(0.0);
});

it('offers nothing without a balance', function () {
    [$user, , $invoice] = picInvoice(0.0);

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee(route('client.invoices.pay-with-credit', $invoice), false);
});

it('never pays an Add Funds invoice from the balance it tops up', function () {
    [$user, $client, $invoice] = picInvoice(80.0, itemType: 'AddFunds');

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))
        ->assertDontSee(route('client.invoices.pay-with-credit', $invoice), false);
    $this->actingAs($user)->post(route('client.invoices.pay-with-credit', $invoice));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and((float) $client->fresh()->credit)->toBe(80.0);
});

it('does not let a customer spend credit on someone else\'s invoice', function () {
    [, $client, $invoice] = picInvoice(80.0);
    [$intruder] = picInvoice(80.0);

    $this->actingAs($intruder)->post(route('client.invoices.pay-with-credit', $invoice))->assertForbidden();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and((float) $client->fresh()->credit)->toBe(80.0);
});
