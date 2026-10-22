<?php

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketSpamFilter;
use App\Models\User;

/*
 * The client ticket form checked the spam filter against $request->input('email')
 * before the signed-in customer's own address. The form has no such field, but
 * a request that added one put any address it liked through the blocked-sender
 * list and the per-hour limit, while the ticket was still opened under the
 * customer's own address.
 */

function spamOwnUser(string $email): User
{
    $user = User::factory()->create(['email' => $email]);
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

test('a blocked customer stays blocked whatever address the request carries', function () {
    TicketSpamFilter::create(['type' => 'email', 'content' => 'blocked@example.test']);
    $department = TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1]);

    $this->actingAs(spamOwnUser('blocked@example.test'))->post(route('client.tickets.store'), [
        'department_id' => $department->id,
        'email' => 'someone-else@example.test',
        'subject' => 'Hello',
        'message' => 'Trying again under another address.',
    ])->assertSessionHas('error', __('messages.error.message_flagged_as_spam'));

    expect(Ticket::count())->toBe(0);
});

test('another customer is not blocked by naming a blocked address', function () {
    TicketSpamFilter::create(['type' => 'email', 'content' => 'blocked@example.test']);
    $department = TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1]);

    $this->actingAs(spamOwnUser('fine@example.test'))->post(route('client.tickets.store'), [
        'department_id' => $department->id,
        'email' => 'blocked@example.test',
        'subject' => 'A normal question',
        'message' => 'Nothing unusual here.',
    ])->assertSessionDoesntHaveErrors();

    expect(Ticket::count())->toBe(1);
});
