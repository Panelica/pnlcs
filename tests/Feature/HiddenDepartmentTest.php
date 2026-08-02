<?php

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;

/**
 * Opening a ticket in a department the customer was not shown.
 *
 * The form lists departments that are not hidden. The request behind it took
 * any department id at all, so a hidden one - an internal queue, or a mailbox
 * the operator imports from and does not want posted into - could be chosen by
 * editing the select. The same fault the code already acknowledges for the
 * related-service picker, which was fixed and this was not.
 */
function ticketOpener(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

test('a hidden department is not offered', function () {
    [$user] = ticketOpener();

    $hidden = TicketDepartment::create(['name' => 'Internal', 'hidden' => true, 'sort_order' => 9]);
    $open = TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1]);

    $this->actingAs($user)->get(route('client.tickets.create'))
        ->assertOk()
        ->assertSee('Support', false)
        ->assertDontSee('Internal', false);
});

test('a hidden department cannot be chosen by hand', function () {
    [$user] = ticketOpener();

    $hidden = TicketDepartment::create(['name' => 'Internal', 'hidden' => true, 'sort_order' => 9]);

    $this->actingAs($user)->post(route('client.tickets.store'), [
        'department_id' => $hidden->id,
        'subject' => 'Straight to the internal queue',
        'message' => 'Posting where I was not invited.',
    ])->assertSessionHasErrors('department_id');

    expect(Ticket::where('department_id', $hidden->id)->exists())->toBeFalse();
});

test('an open department still works', function () {
    [$user, $client] = ticketOpener();

    $open = TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1]);

    $this->actingAs($user)->post(route('client.tickets.store'), [
        'department_id' => $open->id,
        'subject' => 'A normal question',
        'message' => 'Nothing unusual here.',
    ])->assertRedirect();

    expect(Ticket::where('client_id', $client->id)->where('department_id', $open->id)->exists())->toBeTrue();
});
