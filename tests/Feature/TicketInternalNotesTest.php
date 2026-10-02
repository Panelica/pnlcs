<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketNote;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
 * Staff write internal notes on a ticket from its admin page.
 *
 * Notes were shown there, but could only be written through the API or by
 * escalation rules.
 */

function tinStaff(string $username, array $permissions = ['view_tickets', 'list_tickets', 'reply_tickets']): Admin
{
    return Admin::factory()->create(['username' => $username, 'role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function tinTicket(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $ticket = Ticket::create(['tid' => (string) random_int(100000, 999999), 'client_id' => $client->id,
        'department_id' => TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1])->id,
        'name' => 'Ayşe', 'email' => 'ayse@example.test', 'title' => 'Mail bounces', 'message' => 'x', 'status' => 'Open', 'priority' => 'Medium']);

    return [$user, $ticket];
}

it('lets staff add a note, which the customer never sees or receives', function () {
    Mail::fake();
    [$user, $ticket] = tinTicket();

    test()->actingAs(tinStaff('mert'), 'admin')->post(route('admin.tickets.notes.store', $ticket), ['note' => 'Customer is on the old plan - check DNS first.'])
        ->assertSessionHas('success');

    $note = TicketNote::where('ticket_id', $ticket->id)->sole();
    expect($note->admin)->toBe('mert');
    test()->actingAs($user)->get(route('client.tickets.show', $ticket))->assertOk()->assertDontSee('check DNS first');
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('shows the note and lets only its author delete it', function () {
    [, $ticket] = tinTicket();
    $note = $ticket->notes()->create(['admin' => 'mert', 'message' => 'Waiting for the registrar.']);

    test()->actingAs(tinStaff('mert'), 'admin')->get(route('admin.tickets.show', $ticket))->assertOk()
        ->assertSee('Waiting for the registrar.')->assertSee(route('admin.tickets.notes.destroy', [$ticket, $note]), false);

    test()->actingAs(tinStaff('selin'), 'admin')->delete(route('admin.tickets.notes.destroy', [$ticket, $note]))->assertForbidden();
    test()->actingAs(tinStaff('mert2', ['view_tickets', 'list_tickets', 'reply_tickets']), 'admin')->delete(route('admin.tickets.notes.destroy', [$ticket, $note]))->assertForbidden();
    expect(TicketNote::find($note->id))->not->toBeNull();

    test()->actingAs(Admin::where('username', 'mert')->first(), 'admin')->delete(route('admin.tickets.notes.destroy', [$ticket, $note]))->assertRedirect();
    expect(TicketNote::find($note->id))->toBeNull();
});

it('keeps writing notes to staff who may reply', function () {
    [, $ticket] = tinTicket();
    $viewer = tinStaff('viewer', ['view_tickets', 'list_tickets']);

    test()->actingAs($viewer, 'admin')->get(route('admin.tickets.show', $ticket))->assertOk()->assertDontSee(route('admin.tickets.notes.store', $ticket), false);
    test()->actingAs($viewer, 'admin')->post(route('admin.tickets.notes.store', $ticket), ['note' => 'x'])->assertForbidden();
});

it('refuses a note on another ticket\'s address', function () {
    [, $ticket] = tinTicket();
    [, $other] = tinTicket();
    $note = $other->notes()->create(['admin' => 'mert', 'message' => 'x']);

    test()->actingAs(tinStaff('mert'), 'admin')->delete(route('admin.tickets.notes.destroy', [$ticket, $note]))->assertNotFound();
});
