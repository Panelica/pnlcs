<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;

/*
 * Three things the invoice screens said wrong: the customer coming back from
 * a gateway saw a translation key (messages.iyzico.paid) instead of a
 * sentence, the admin saw a refund as "+0.00", and the buyer's address was
 * labelled "Name".
 */

function historyAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Billing',
            'permissions' => ['list_invoices', 'view_invoices', 'manage_invoices'],
        ])->id,
    ]);
}

test('a customer back from a gateway reads a sentence, not a key', function (string $code, string $key) {
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 20]);

    $this->actingAs($user)
        ->get(route('client.invoices.show', $invoice).'?payment='.$code)
        ->assertOk()
        ->assertSee(__($key))
        ->assertDontSee($key);
})->with([
    'paid' => ['success', 'messages.payment_return.paid'],
    'failed' => ['failed', 'messages.payment_return.failed'],
    'orphan' => ['orphan', 'messages.payment_return.invoice_missing'],
]);

test('the admin sees a refund as money going out', function () {
    $client = Client::factory()->create(['address1' => '1 Test Street', 'city' => 'Leeds']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'refunded', 'total' => 40]);
    Transaction::create([
        'client_id' => $client->id, 'invoice_id' => $invoice->id, 'gateway' => 'banktransfer',
        'date' => now(), 'description' => 'Payment', 'amount_in' => 40, 'amount_out' => 0, 'fees' => 0,
    ]);
    Transaction::create([
        'client_id' => $client->id, 'invoice_id' => $invoice->id, 'gateway' => 'banktransfer',
        'date' => now(), 'description' => 'Refund', 'amount_in' => 0, 'amount_out' => 15, 'fees' => 0,
    ]);

    $html = $this->actingAs(historyAdmin(), 'admin')
        ->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('+'.money_fmt(40))
        ->and($html)->toContain('-'.money_fmt(15))
        ->and($html)->not->toContain('+'.money_fmt(0));
});

test('the buyer address row is labelled as an address', function () {
    $client = Client::factory()->create(['address1' => '1 Test Street', 'city' => 'Leeds']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'total' => 40]);

    $html = $this->actingAs(historyAdmin(), 'admin')
        ->get(route('admin.invoices.show', $invoice))
        ->getContent();

    expect($html)->toMatch('/'.preg_quote(__('common.form.address'), '/').'<\/td><td[^>]*>1 Test Street/');
});
