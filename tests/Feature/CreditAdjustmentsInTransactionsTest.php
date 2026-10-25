<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Credit;

/*
 * #307. Staff can add credit to a client's balance or take it away, with a
 * reason, and the line is kept with who did it - but only on that client's
 * own credit page. Billing → Transactions, where staff look for money moving,
 * showed none of it, so a balance changed by hand could not be seen across
 * clients. It now lists those adjustments under the transactions, apart from
 * them: no money moved, so they are not income.
 */

function catAdmin(string $first = 'Ayse', string $last = 'Kaya'): Admin
{
    return Admin::factory()->create(['first_name' => $first, 'last_name' => $last, 'role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('a credit added and one taken by hand appear on Transactions with the reason and who did it', function () {
    $admin = catAdmin();
    $client = Client::factory()->create(['credit' => 0]);

    $this->actingAs($admin, 'admin')->post(route('admin.clients.credit.store', $client), ['type' => 'add', 'amount' => 25, 'description' => 'Goodwill for the outage'])->assertSessionHasNoErrors();
    $this->actingAs($admin, 'admin')->post(route('admin.clients.credit.store', $client), ['type' => 'remove', 'amount' => 5, 'description' => 'Correction'])->assertSessionHasNoErrors();

    $html = $this->actingAs($admin, 'admin')->get(route('admin.config.transactions'))->assertOk()->getContent();

    expect($html)->toContain(__('admin.transactions.adjustments_title'))
        ->toContain('Goodwill for the outage')->toContain('Correction')
        ->toContain(__('admin.transactions.credit_added'))->toContain(__('admin.transactions.credit_deducted'))
        ->toContain('+'.money_fmt(25))->toContain('-'.money_fmt(5))
        ->toContain('Ayse Kaya');
});

test('credit the system writes (a payment into balance, an overpayment) is not listed as a staff adjustment', function () {
    $client = Client::factory()->create();
    Credit::create(['client_id' => $client->id, 'date' => now(), 'description' => 'Overpayment on invoice #9', 'amount' => 3]);

    $this->actingAs(catAdmin(), 'admin')->get(route('admin.config.transactions'))->assertOk()
        ->assertDontSee('Overpayment on invoice #9')
        ->assertSee(__('admin.transactions.no_adjustments'));
});
