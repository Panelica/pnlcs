<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\PaymentService;

/**
 * Mark Paid in the admin pre-filled the invoice total, so on a partly paid
 * invoice submitting the form as shown paid the total again and the excess
 * went to the client's credit. "Credit" as the method recorded a payment and
 * marked the invoice paid without taking anything from the credit balance.
 */
beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('prefills what is still owed, not the total', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => 100, 'total' => 100]);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', null, 40);

    $this->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('name="amount" step="0.01" value="60.00"', false);
});

it('pays from the client credit balance when Credit is chosen', function () {
    $client = Client::factory()->create(['credit' => 150]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => 100, 'total' => 100]);

    $this->post(route('admin.invoices.mark-paid', $invoice), ['gateway' => 'credit'])
        ->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) $client->fresh()->credit)->toBe(50.0);
});

it('takes only what the balance covers and leaves the rest owed', function () {
    $client = Client::factory()->create(['credit' => 30]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => 100, 'total' => 100]);

    $this->post(route('admin.invoices.mark-paid', $invoice), ['gateway' => 'credit', 'amount' => 100]);

    expect($invoice->fresh()->status)->not->toBe('paid')
        ->and((float) $client->fresh()->credit)->toBe(0.0)
        ->and($invoice->fresh()->amountDue())->toBe(70.0);
});

it('refuses Credit when the client has no balance', function () {
    $client = Client::factory()->create(['credit' => 0]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => 100, 'total' => 100]);

    $this->post(route('admin.invoices.mark-paid', $invoice), ['gateway' => 'credit'])
        ->assertSessionHas('error', __('admin.messages.no_credit_available'));

    expect($invoice->fresh()->status)->toBe('unpaid');
});
