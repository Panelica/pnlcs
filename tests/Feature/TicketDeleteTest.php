<?php

/*
 * Deleting tickets from the admin area, one or many.
 *
 * Reported by an operator whose public contact form was found by spammers:
 * the form opens a ticket for anybody who fills it in, with no account behind
 * it, and nothing in the admin area could remove one. Deleting the client was
 * no way out either - these tickets have none. The API's deleteticket was
 * the only door, and it dropped the row and left the attachments on disk.
 */

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketNote;
use App\Models\TicketReply;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function tdStaff(array $permissions = ['list_tickets', 'view_tickets', 'reply_tickets', 'manage_tickets']): Admin
{
    return Admin::factory()->create([
        'username' => 'staff'.Str::random(6),
        'role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id,
    ]);
}

/** A ticket with a reply, a note and an attachment on each, as the client area stores them. */
function tdTicketWithEverything(?int $clientId = null): Ticket
{
    $ticket = Ticket::factory()->create(['client_id' => $clientId]);

    $path = UploadedFile::fake()->create('invoice.pdf', 10)->store("ticket-attachments/{$ticket->id}", 'local');
    $ticket->update(['attachment' => $path]);

    $replyPath = UploadedFile::fake()->create('log.txt', 1)->store("ticket-attachments/{$ticket->id}", 'local');
    TicketReply::create(['ticket_id' => $ticket->id, 'message' => 'more', 'attachment' => $replyPath]);
    TicketNote::create(['ticket_id' => $ticket->id, 'admin' => 'a', 'message' => 'internal']);

    return $ticket;
}

beforeEach(fn () => Storage::fake('local'));

test('a ticket is deleted with its replies, notes and attachments', function () {
    $ticket = tdTicketWithEverything();
    $staff = tdStaff();
    Storage::disk('local')->assertExists($ticket->attachment);

    $this->actingAs($staff, 'admin')
        ->delete(route('admin.tickets.destroy', $ticket))
        ->assertRedirect(route('admin.tickets.index'))
        ->assertSessionHas('success');

    expect(Ticket::find($ticket->id))->toBeNull()
        ->and(TicketReply::where('ticket_id', $ticket->id)->count())->toBe(0)
        ->and(TicketNote::where('ticket_id', $ticket->id)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles("ticket-attachments/{$ticket->id}"))->toBe([])
        ->and(ActivityLog::where('description', "Ticket #{$ticket->tid} deleted")->where('user', $staff->username)->exists())->toBeTrue();
});

test('the spam the contact form lets in can be found and deleted in bulk', function () {
    $dept = TicketDepartment::create(['name' => 'Sales', 'hidden' => false, 'sort_order' => 1]);
    foreach (['a', 'b', 'c'] as $who) {
        $this->post(route('client.contact.submit'), [
            'name' => 'Spammer '.$who, 'email' => "spam-{$who}@example.com",
            'department_id' => $dept->id, 'subject' => 'Cheap pills', 'message' => 'Buy now',
        ])->assertSessionHas('success');
    }
    $customer = Ticket::factory()->create(['client_id' => Client::factory()->create()->id, 'title' => 'Real question']);

    $guests = Ticket::whereNull('client_id')->pluck('id')->all();
    expect($guests)->toHaveCount(3);

    // The list narrowed to tickets without an account shows only those.
    $page = $this->actingAs(tdStaff(), 'admin')->get(route('admin.tickets.index', ['guests' => 1]))->assertOk()->getContent();
    expect($page)->toContain('Cheap pills')->not->toContain('Real question');

    $this->actingAs(tdStaff(), 'admin')
        ->post(route('admin.tickets.bulk-delete'), ['ticket_ids' => $guests])
        ->assertRedirect()
        ->assertSessionHas('success', __('admin.tickets.bulk_deleted', ['count' => 3]));

    expect(Ticket::whereNull('client_id')->count())->toBe(0)
        ->and(Ticket::find($customer->id))->not->toBeNull();
});

test('deleting a ticket that others were merged into leaves them pointing at nothing', function () {
    $target = tdTicketWithEverything();
    $merged = Ticket::factory()->create(['merged_ticket_id' => $target->id, 'status' => 'Closed']);

    $this->actingAs(tdStaff(), 'admin')->delete(route('admin.tickets.destroy', $target))->assertRedirect();

    expect($merged->fresh()->merged_ticket_id)->toBeNull();
});

test('staff who may not manage tickets cannot delete them, alone or in bulk', function () {
    $ticket = tdTicketWithEverything();
    $replier = tdStaff(['list_tickets', 'view_tickets', 'reply_tickets']);

    $this->actingAs($replier, 'admin')->delete(route('admin.tickets.destroy', $ticket))->assertForbidden();
    $this->actingAs($replier, 'admin')->post(route('admin.tickets.bulk-delete'), ['ticket_ids' => [$ticket->id]])->assertForbidden();

    expect(Ticket::find($ticket->id))->not->toBeNull();

    // And the controls are not offered to them.
    $list = $this->actingAs($replier, 'admin')->get(route('admin.tickets.index'))->assertOk()->getContent();
    $show = $this->actingAs($replier, 'admin')->get(route('admin.tickets.show', $ticket))->assertOk()->getContent();
    expect($list)->not->toContain('ticket-bulk-delete')
        // The delete URL is the ticket's own URL with another verb, so the
        // form is told apart by its confirmation text.
        ->and($show)->not->toContain('with its replies, notes and attachments');
});

test('the controls are on the list and the ticket page for staff who manage tickets', function () {
    $ticket = tdTicketWithEverything();
    $staff = tdStaff();

    $list = $this->actingAs($staff, 'admin')->get(route('admin.tickets.index'))->assertOk()->getContent();
    $show = $this->actingAs($staff, 'admin')->get(route('admin.tickets.show', $ticket))->assertOk()->getContent();

    expect($list)->toContain('name="ticket_ids[]"')
        ->and($list)->toContain(route('admin.tickets.bulk-delete'))
        ->and($show)->toContain('with its replies, notes and attachments');
});

test('a bulk delete needs a selection and ignores ids that do not exist', function () {
    $staff = tdStaff();
    $ticket = tdTicketWithEverything();

    $this->actingAs($staff, 'admin')->post(route('admin.tickets.bulk-delete'), [])->assertSessionHasErrors('ticket_ids');

    $this->actingAs($staff, 'admin')
        ->post(route('admin.tickets.bulk-delete'), ['ticket_ids' => [$ticket->id, 999999]])
        ->assertSessionHas('success', __('admin.tickets.bulk_deleted', ['count' => 1]));
});

test('the API deleteticket removes the attachments too', function () {
    $ticket = tdTicketWithEverything();
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $secret = Str::random(64);
    $cred = ApiCredential::create([
        'admin_id' => $admin->id, 'identifier' => Str::random(32),
        'secret' => ApiCredential::hashSecret($secret), 'description' => 'test', 'active' => true,
    ]);

    $this->withHeaders(['X-API-Key' => $cred->identifier, 'X-API-Secret' => $secret, 'Accept' => 'application/json'])
        ->post('/api/v1/deleteticket', ['ticketid' => $ticket->id])
        ->assertOk();

    expect(Ticket::find($ticket->id))->toBeNull()
        ->and(Storage::disk('local')->allFiles("ticket-attachments/{$ticket->id}"))->toBe([]);
});
