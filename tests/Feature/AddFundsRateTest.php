<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;

/*
 * An Add Funds invoice sells the shop currency itself, and stays payable for
 * as long as nobody pays it. Paid later at the rate of the day it was opened,
 * it bought balance at an old price: with the billing currency falling
 * against the shop's, opening one and waiting was a way to buy balance
 * cheaply. An unpaid one now takes today's rate when it is opened or a
 * payment is started. Ordinary invoices keep the rate they were raised at.
 */
function fundsRateSetup(): array
{
    Currency::updateOrCreate(['code' => 'USD'], ['name' => 'USD', 'prefix' => '$', 'rate' => 1, 'is_default' => true]);
    Currency::updateOrCreate(['code' => 'TRY'], ['name' => 'TRY', 'prefix' => '', 'suffix' => 'TL', 'rate' => 40, 'is_default' => false]);
    Setting::set('BillingCurrency', 'TRY');
    GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'active'], ['value' => '1']);

    $client = Client::factory()->create(['country' => 'TR', 'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000']);
    $user = User::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    return [$client, $user];
}

test('an unpaid Add Funds invoice is shown at today\'s rate, not the one it was opened at', function () {
    [$client, $user] = fundsRateSetup();

    $this->actingAs($user)->post(route('client.funds.store'), ['amount' => 4000, 'payment_method' => 'banktransfer'])
        ->assertSessionHasNoErrors();
    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();
    expect((float) $invoice->exchange_rate)->toBe(40.0)->and((float) $invoice->total)->toBe(100.0);

    // The lira falls; the invoice is opened again a month later.
    Currency::where('code', 'TRY')->update(['rate' => 45]);
    $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk();

    $invoice->refresh();
    expect((float) $invoice->exchange_rate)->toBe(45.0)
        // The balance bought is the same; what it costs in lira is today's.
        ->and((float) $invoice->total)->toBe(100.0)
        ->and($invoice->inBillingCurrency($invoice->total))->toBe(4500.0)
        ->and($invoice->items()->first()->description)->toContain('4.500,00');
});

test('a payment started on a stale Add Funds invoice is started at today\'s rate', function () {
    [$client, $user] = fundsRateSetup();
    $this->actingAs($user)->post(route('client.funds.store'), ['amount' => 4000, 'payment_method' => 'banktransfer']);
    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    Currency::where('code', 'TRY')->update(['rate' => 45]);
    // Straight to the gateway, without opening the invoice page.
    $this->actingAs($user)->postJson(route('gateway.stripe.intent', $invoice));

    expect((float) $invoice->fresh()->exchange_rate)->toBe(45.0);
});

test('an ordinary invoice keeps the rate it was raised at', function () {
    [$client, $user] = fundsRateSetup();
    $invoice = Invoice::create([
        'client_id' => $client->id, 'invoice_num' => 'T-1', 'date' => today(), 'due_date' => today(),
        'subtotal' => 100, 'total' => 100, 'status' => 'unpaid',
    ]);
    $invoice->items()->create(['client_id' => $client->id, 'type' => 'Hosting', 'description' => 'Plan', 'amount' => 100]);

    Currency::where('code', 'TRY')->update(['rate' => 45]);
    $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk();

    expect((float) $invoice->fresh()->exchange_rate)->toBe(40.0);
});

test('an Add Funds invoice with money already on it keeps its rate', function () {
    [$client, $user] = fundsRateSetup();
    $this->actingAs($user)->post(route('client.funds.store'), ['amount' => 4000, 'payment_method' => 'banktransfer']);
    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();
    Transaction::create(['client_id' => $client->id, 'invoice_id' => $invoice->id, 'gateway' => 'banktransfer', 'date' => now(), 'amount_in' => 10, 'transaction_id' => 'part-1']);

    Currency::where('code', 'TRY')->update(['rate' => 45]);
    $invoice->refreshAddFundsRate();

    expect((float) $invoice->fresh()->exchange_rate)->toBe(40.0);
});
