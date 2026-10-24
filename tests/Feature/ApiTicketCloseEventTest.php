<?php

use App\Events\TicketClosed;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Closing a ticket from the admin or client screen goes through
 * TicketService::closeTicket(), which fires TicketClosed (the TicketClosed /
 * TicketClose hooks and notification rules listen to it). The API's
 * updateticket and mergeticket set the status themselves, so a ticket closed
 * through the API was closed without anyone being told.
 */
function closeEventApiHeaders(): array
{
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $secret = Str::random(64);
    $cred = ApiCredential::create([
        'admin_id' => $admin->id, 'identifier' => Str::random(32),
        'secret' => ApiCredential::hashSecret($secret), 'description' => 'close event', 'active' => true,
    ]);

    return ['X-API-Key' => $cred->identifier, 'X-API-Secret' => $secret, 'Accept' => 'application/json'];
}

beforeEach(function () {
    TicketStatus::firstOrCreate(['title' => 'Closed'], ['color' => '#999', 'sort_order' => 10]);
    TicketStatus::firstOrCreate(['title' => 'Answered'], ['color' => '#999', 'sort_order' => 3]);
});

test('closing a ticket through updateticket fires TicketClosed', function () {
    Event::fake([TicketClosed::class]);
    $ticket = Ticket::factory()->create(['status' => 'Open']);

    $this->withHeaders(closeEventApiHeaders())
        ->post('/api/v1/updateticket', ['ticketid' => $ticket->id, 'status' => 'closed', 'subject' => 'Renamed'])
        ->assertOk();

    expect($ticket->fresh()->status)->toBe('Closed')
        ->and($ticket->fresh()->title)->toBe('Renamed');
    Event::assertDispatched(TicketClosed::class, fn ($e) => $e->ticket->id === $ticket->id && $e->byClient === false);
});

test('other status changes and an already closed ticket fire nothing', function () {
    Event::fake([TicketClosed::class]);
    $open = Ticket::factory()->create(['status' => 'Open']);
    $closed = Ticket::factory()->create(['status' => 'Closed']);
    $h = closeEventApiHeaders();

    $this->withHeaders($h)->post('/api/v1/updateticket', ['ticketid' => $open->id, 'status' => 'Answered'])->assertOk();
    $this->withHeaders($h)->post('/api/v1/updateticket', ['ticketid' => $closed->id, 'status' => 'Closed'])->assertOk();

    expect($open->fresh()->status)->toBe('Answered');
    Event::assertNotDispatched(TicketClosed::class);
});

test('the ticket merged away fires TicketClosed', function () {
    Event::fake([TicketClosed::class]);
    [$from, $into] = [Ticket::factory()->create(['status' => 'Open']), Ticket::factory()->create(['status' => 'Open'])];

    $this->withHeaders(closeEventApiHeaders())
        ->post('/api/v1/mergeticket', ['ticketid' => $from->id, 'mergeid' => $into->id])
        ->assertOk();

    expect($from->fresh()->status)->toBe('Closed');
    Event::assertDispatched(TicketClosed::class, fn ($e) => $e->ticket->id === $from->id);
    Event::assertNotDispatched(TicketClosed::class, fn ($e) => $e->ticket->id === $into->id);
});
