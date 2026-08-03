<?php

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketStatus;
use App\Models\User;

/**
 * How many tickets the customer is told are open.
 *
 * The panel keeps a table of ticket statuses and each one carries a flag
 * saying whether it counts as still open. The home page ignored it and counted
 * two spellings it had been given in code: Open and Customer-Reply. Answered,
 * On Hold and In Progress are all marked as open on this installation and none
 * of them was counted - four of the eleven live tickets.
 *
 * A customer whose only ticket has been answered was told they had none.
 */
function openCountCustomer(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

function countedTicket(Client $client, string $status): Ticket
{
    return Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'status' => $status,
    ]);
}

test('every status the operator calls open is counted', function () {
    [$user, $client] = openCountCustomer();

    TicketStatus::query()->delete();
    foreach (['Open', 'Answered', 'Customer-Reply', 'On Hold', 'In Progress'] as $i => $title) {
        TicketStatus::create(['title' => $title, 'sort_order' => $i, 'show_active' => true]);
    }
    TicketStatus::create(['title' => 'Closed', 'sort_order' => 9, 'show_active' => false]);

    foreach (['Open', 'Answered', 'Customer-Reply', 'On Hold', 'In Progress'] as $status) {
        countedTicket($client, $status);
    }

    countedTicket($client, 'Closed');

    $this->actingAs($user)->get(route('client.home'))
        ->assertOk()
        ->assertViewHas('openTickets', 5);
});

test('a status the operator closes is not counted', function () {
    [$user, $client] = openCountCustomer();

    TicketStatus::query()->delete();
    TicketStatus::create(['title' => 'Open', 'sort_order' => 1, 'show_active' => true]);
    TicketStatus::create(['title' => 'Answered', 'sort_order' => 2, 'show_active' => false]);

    countedTicket($client, 'Open');
    countedTicket($client, 'Answered');

    $this->actingAs($user)->get(route('client.home'))
        ->assertOk()
        ->assertViewHas('openTickets', 1);
});

test('with no statuses configured the count still means something', function () {
    [$user, $client] = openCountCustomer();

    TicketStatus::query()->delete();

    countedTicket($client, 'Open');
    countedTicket($client, 'Closed');

    $this->actingAs($user)->get(route('client.home'))
        ->assertOk()
        ->assertViewHas('openTickets', 1);
});
