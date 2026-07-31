<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;

/**
 * Screens comparing a status against the wrong spelling.
 *
 * Orders are stored lowercase and the order screen compared against
 * 'Pending', 'Fraud' and 'Cancelled', so on this installation — eighteen
 * orders waiting and three marked fraud — the Accept button never appeared on
 * any of them, the fraud report never showed, and the cancel and mark-fraud
 * buttons were offered on orders that were already cancelled or fraudulent.
 *
 * Tickets are stored capitalised and the client screen compared against
 * "closed", so the reply box stayed open on a closed ticket and the notice
 * telling the customer to open a new one never appeared.
 */
function orderScreenAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Orders',
            'permissions' => ['list_orders', 'view_orders', 'manage_orders', 'accept_orders', 'delete_orders'],
        ])->id,
    ]);
}

function orderWith(string $status): Order
{
    return Order::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => $status,
        'order_num' => strtoupper(substr(md5($status.microtime()), 0, 8)),
    ]);
}

function ticketWith(string $status): Ticket
{
    return Ticket::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'status' => $status,
    ]);
}

test('a waiting order can be accepted from its own screen', function () {
    $order = orderWith('pending');

    $this->actingAs(orderScreenAdmin(), 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee(route('admin.orders.accept', $order), false);
});

test('an order already marked fraud is not offered the fraud button again', function () {
    $order = orderWith('fraud');

    $this->actingAs(orderScreenAdmin(), 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertDontSee(route('admin.orders.fraud', $order), false)
        ->assertDontSee(route('admin.orders.cancel', $order), false);
});

test('a closed ticket does not invite another reply', function () {
    $user = User::factory()->create();
    $ticket = ticketWith('Closed');
    $user->clients()->attach($ticket->client_id);

    $this->actingAs($user)->get(route('client.tickets.show', $ticket))
        ->assertOk()
        ->assertDontSee(route('client.tickets.reply', $ticket), false);
});

test('an open ticket still offers the reply box', function () {
    $user = User::factory()->create();
    $ticket = ticketWith('Open');
    $user->clients()->attach($ticket->client_id);

    $this->actingAs($user)->get(route('client.tickets.show', $ticket))
        ->assertOk()
        ->assertSee(route('client.tickets.reply', $ticket), false);
});
