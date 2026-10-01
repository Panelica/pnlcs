<?php

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;

/*
 * A customer can close their own ticket once the problem is solved.
 *
 * TicketService::closeTicket() existed and nothing called it, so a solved
 * ticket stayed open until staff noticed and the customer could only post
 * "you can close this" as one more reply. Closing also had no hook, unlike
 * opening and replying.
 */

function cctTicket(?Client $client = null, string $status = 'Open'): array
{
    $user = User::factory()->create();
    $client ??= Client::factory()->create();
    $user->clients()->attach($client->id);
    $department = TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1]);

    $ticket = Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => $department->id,
        'status' => $status,
    ]);

    return [$user, $ticket];
}

it('lets the customer close their own ticket', function () {
    [$user, $ticket] = cctTicket();

    $this->actingAs($user)->get(route('client.tickets.show', $ticket))
        ->assertOk()
        ->assertSee(route('client.tickets.close', $ticket), false);

    $this->actingAs($user)->post(route('client.tickets.close', $ticket))
        ->assertRedirect(route('client.tickets.show', $ticket));

    expect($ticket->fresh()->status)->toBe('Closed');
});

it('does not let a customer close someone else\'s ticket', function () {
    [, $ticket] = cctTicket();
    [$intruder] = cctTicket();

    $this->actingAs($intruder)->post(route('client.tickets.close', $ticket))->assertForbidden();

    expect($ticket->fresh()->status)->toBe('Open');
});

it('offers no close button on a closed ticket', function () {
    [$user, $ticket] = cctTicket(status: 'Closed');

    $this->actingAs($user)->get(route('client.tickets.show', $ticket))
        ->assertOk()
        ->assertDontSee(route('client.tickets.close', $ticket), false);
});

it('runs the TicketClose hook, and only once', function () {
    [$user, $ticket] = cctTicket();
    $calls = [];
    add_hook('TicketClose', function (array $vars) use (&$calls) {
        $calls[] = $vars;
    });

    $this->actingAs($user)->post(route('client.tickets.close', $ticket));
    $this->actingAs($user)->post(route('client.tickets.close', $ticket));

    expect($calls)->toHaveCount(1)
        ->and($calls[0]['ticket']->id)->toBe($ticket->id)
        ->and($calls[0]['byClient'])->toBeTrue();
});
