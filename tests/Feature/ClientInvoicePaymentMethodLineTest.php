<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;

/*
 * The customer's invoice page printed "Paid via <method>" for every invoice
 * that carried a payment method, with no look at its status. That was quiet
 * while generated invoices carried none; once they name how the customer is
 * expected to pay (#481), an unpaid renewal would have told the customer it
 * was already paid.
 */
function cipmSignedInClient(array $attrs = []): array
{
    $client = Client::factory()->create($attrs + ['default_payment_method' => 'banktransfer']);
    $user = User::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    return [$client, $user];
}

it('does not tell a customer an unpaid invoice was paid', function () {
    [$client, $user] = cipmSignedInClient();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => 25,
        'payment_method' => 'banktransfer',
    ]);

    $html = $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk()->getContent();

    expect($html)->not->toContain(__('client.invoices.paid_via'))
        ->and($html)->toContain(__('client.invoices.payment_method'))
        ->and($html)->toContain(payment_method_label('banktransfer'));
});

it('still says how a paid invoice was paid', function () {
    [$client, $user] = cipmSignedInClient();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'paid',
        'total' => 25,
        'date_paid' => now(),
        'payment_method' => 'banktransfer',
    ]);

    $html = $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk()->getContent();

    expect($html)->toContain(__('client.invoices.paid_via'))
        ->and($html)->toContain(payment_method_label('banktransfer'));
});

it('names the method the customer would use even when the document carries none', function () {
    [$client, $user] = cipmSignedInClient(['default_payment_method' => 'stripe']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => 25,
        'payment_method' => null,
    ]);

    $html = $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk()->getContent();
    // The page also lists the gateways it can be paid with, so the method has
    // to be read where the document describes itself, not anywhere on the page.
    $header = preg_replace('/\s+/', ' ', strip_tags($html));

    expect($header)->toContain(__('client.invoices.payment_method').': '.payment_method_label('stripe'))
        ->and($html)->not->toContain(__('client.invoices.paid_via'));
});
