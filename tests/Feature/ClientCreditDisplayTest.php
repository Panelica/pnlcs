<?php

use App\Models\Client;
use App\Models\User;

/*
 * The dashboard and the add-funds page show the account's balance.
 *
 * Both views read auth()->user()->credit. The balance lives on the account
 * (clients.credit); users has no such column, so the dashboard never showed
 * the credit and the add-funds page always said 0.
 */

function ccdCustomer(float $credit): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create(['credit' => $credit])->id);

    return $user;
}

it('shows the account balance on the dashboard', function () {
    $this->actingAs(ccdCustomer(125.50))->get(route('client.home'))
        ->assertOk()
        ->assertSee(money_fmt(125.50));
});

it('shows the account balance on the add-funds page', function () {
    $this->actingAs(ccdCustomer(125.50))->get(route('client.funds.index'))
        ->assertOk()
        ->assertSee(money_fmt(125.50));
});
