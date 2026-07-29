<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;

/**
 * A login that belongs to more than one customer account.
 *
 * Every page in the client area asked for "the user's first client", so a
 * login attached to two accounts could only ever see one of them — the other
 * account's invoices, services and domains were unreachable. Worse, an admin
 * using "log in as this customer" landed on whichever account happened to be
 * first, not the one they clicked, and could act on the wrong customer
 * believing they were looking at the right one.
 */
function userWithTwoAccounts(): array
{
    $user = User::factory()->create();

    $first = Client::factory()->create(['first_name' => 'First', 'last_name' => 'Account']);
    $second = Client::factory()->create(['first_name' => 'Second', 'last_name' => 'Account']);

    $user->clients()->attach([$first->id, $second->id]);

    return [$user, $first, $second];
}

function adminWhoCanImpersonate(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Support',
            'permissions' => ['edit_clients'],
        ])->id,
    ]);
}

test('the customer sees the account they are on, not always the first', function () {
    [$user, $first, $second] = userWithTwoAccounts();

    Invoice::factory()->create(['client_id' => $first->id, 'invoice_num' => 'INV-FIRST', 'status' => 'unpaid', 'total' => 10]);
    Invoice::factory()->create(['client_id' => $second->id, 'invoice_num' => 'INV-SECOND', 'status' => 'unpaid', 'total' => 20]);

    $this->actingAs($user)->get(route('client.invoices.index'))
        ->assertOk()
        ->assertSee('INV-FIRST')
        ->assertDontSee('INV-SECOND');

    $this->actingAs($user)->post(route('client.account.switch', $second))->assertRedirect();

    $this->actingAs($user)->get(route('client.invoices.index'))
        ->assertOk()
        ->assertSee('INV-SECOND')
        ->assertDontSee('INV-FIRST');
});

test('a customer cannot switch to an account that is not theirs', function () {
    [$user] = userWithTwoAccounts();
    $stranger = Client::factory()->create();

    $this->actingAs($user)->post(route('client.account.switch', $stranger))->assertForbidden();
});

test('logging in as a customer opens that customer, not their first account', function () {
    [$user, $first, $second] = userWithTwoAccounts();

    Invoice::factory()->create(['client_id' => $first->id, 'invoice_num' => 'INV-FIRST', 'status' => 'unpaid', 'total' => 10]);
    Invoice::factory()->create(['client_id' => $second->id, 'invoice_num' => 'INV-SECOND', 'status' => 'unpaid', 'total' => 20]);

    $this->actingAs(adminWhoCanImpersonate(), 'admin')
        ->post(route('admin.clients.impersonate', $second))
        ->assertRedirect();

    $this->get(route('client.invoices.index'))
        ->assertOk()
        ->assertSee('INV-SECOND')
        ->assertDontSee('INV-FIRST');
});

test('a login with one account behaves exactly as before', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    Invoice::factory()->create(['client_id' => $client->id, 'invoice_num' => 'INV-ONLY', 'status' => 'unpaid', 'total' => 10]);

    $this->actingAs($user)->get(route('client.invoices.index'))
        ->assertOk()
        ->assertSee('INV-ONLY');
});
