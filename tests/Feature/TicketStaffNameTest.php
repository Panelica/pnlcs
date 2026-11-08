<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketReply;
use App\Models\User;

/*
 * A staff reply stores the staff member's sign-in username, and the customer's
 * ticket page printed it: "Support Staff: sysadmin". A username is half of an
 * admin login. The customer now sees the staff member's first name, and only
 * "Support Staff" when the reply belongs to nobody the install still knows.
 */

function staffNameTicket(): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $ticket = Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => TicketDepartment::factory()->create()->id,
    ]);

    return [$user, $ticket];
}

test('the customer sees the staff member\'s first name, not their sign-in username', function () {
    [$user, $ticket] = staffNameTicket();
    Admin::factory()->create(['username' => 'sysadmin-login-7', 'first_name' => 'Derya']);
    TicketReply::create(['ticket_id' => $ticket->id, 'admin' => 'sysadmin-login-7', 'message' => 'We are on it.']);

    $this->actingAs($user)->get(route('client.tickets.show', $ticket))
        ->assertOk()
        ->assertSee(__('client.tickets.support_staff').': Derya')
        ->assertDontSee('sysadmin-login-7');
});

test('a reply from a staff member who is gone shows only "Support Staff"', function () {
    [$user, $ticket] = staffNameTicket();
    TicketReply::create(['ticket_id' => $ticket->id, 'admin' => 'former-staff-login', 'message' => 'Done.']);

    $this->actingAs($user)->get(route('client.tickets.show', $ticket))
        ->assertOk()
        ->assertSee(__('client.tickets.support_staff'))
        ->assertDontSee('former-staff-login');
});
