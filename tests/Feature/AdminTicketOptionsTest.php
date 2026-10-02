<?php

use App\Events\TicketClosed;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\Event;

/*
 * Staff change a ticket's status, priority, department and assignee from its
 * page. The page could only reply; the rest needed the API.
 */

function atoStaff(array $permissions = ['view_tickets', 'list_tickets', 'reply_tickets', 'manage_tickets'], string $username = 'mert'): Admin
{
    return Admin::factory()->create(['username' => $username, 'role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function atoTicket(): Ticket
{
    foreach (['Open', 'Answered', 'Customer-Reply', 'Closed'] as $i => $title) {
        TicketStatus::firstOrCreate(['title' => $title], ['color' => '#999', 'sort_order' => $i]);
    }
    $client = Client::factory()->create();

    return Ticket::create(['tid' => (string) random_int(100000, 999999), 'client_id' => $client->id,
        'department_id' => TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1])->id,
        'name' => 'Ayşe', 'email' => 'ayse@example.test', 'title' => 'Mail bounces', 'message' => 'x', 'status' => 'Open', 'priority' => 'Medium']);
}

function atoPost(Admin $admin, Ticket $ticket, array $data)
{
    return test()->actingAs($admin, 'admin')->put(route('admin.tickets.update', $ticket), array_merge([
        'status' => $ticket->status, 'priority' => $ticket->priority, 'department_id' => $ticket->department_id, 'flag' => $ticket->flag,
    ], $data));
}

it('changes priority, department and assignee', function () {
    $ticket = atoTicket();
    $billing = TicketDepartment::create(['name' => 'Billing', 'hidden' => false, 'sort_order' => 2]);
    $admin = atoStaff();
    $colleague = atoStaff(['view_tickets'], 'selin');

    atoPost($admin, $ticket, ['priority' => 'High', 'department_id' => $billing->id, 'flag' => $colleague->id])->assertSessionHas('success');

    $ticket->refresh();
    expect($ticket->priority)->toBe('High')->and($ticket->department_id)->toBe($billing->id)->and((int) $ticket->flag)->toBe($colleague->id);
    test()->actingAs($admin, 'admin')->get(route('admin.tickets.show', $ticket))->assertOk()->assertSee('selin');
});

it('closes the ticket through the service, so TicketClosed fires', function () {
    Event::fake([TicketClosed::class]);
    $ticket = atoTicket();

    atoPost(atoStaff(), $ticket, ['status' => 'Closed'])->assertSessionHas('success');

    expect($ticket->fresh()->status)->toBe('Closed');
    Event::assertDispatched(TicketClosed::class, fn ($e) => $e->ticket->id === $ticket->id);
});

it('refuses a status that is not configured, or a priority out of the list', function () {
    $ticket = atoTicket();

    atoPost(atoStaff(), $ticket, ['status' => 'Whatever'])->assertSessionHasErrors('status');
    atoPost(atoStaff(username: 'ali'), $ticket, ['priority' => 'Urgent'])->assertSessionHasErrors('priority');

    expect($ticket->fresh()->status)->toBe('Open');
});

it('keeps the panel and the change to staff who manage tickets', function () {
    $ticket = atoTicket();
    $replier = atoStaff(['view_tickets', 'list_tickets', 'reply_tickets'], 'replier');

    test()->actingAs($replier, 'admin')->get(route('admin.tickets.show', $ticket))->assertOk()->assertDontSee('name="flag"', false);
    atoPost($replier, $ticket, ['priority' => 'High'])->assertForbidden();

    test()->actingAs(atoStaff(username: 'boss'), 'admin')->get(route('admin.tickets.show', $ticket))->assertSee('name="flag"', false);
});
