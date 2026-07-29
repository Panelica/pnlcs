<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketEscalation;
use App\Services\TicketEscalationService;
use App\Services\TicketService;
use Database\Factories\ApiCredentialFactory;

/**
 * A reply restarts the escalation clock — through every door.
 *
 * Escalating a ticket stamps escalated_at so a rule cannot fire twice on the
 * same silence, and the two web reply screens clear it again. The other two
 * doors did not: a customer answering by email, and an integration replying
 * through the API. A ticket that was escalated once could never be escalated
 * again, however long staff left the customer waiting afterwards.
 */
function silentTicket(array $attrs = []): Ticket
{
    return Ticket::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'status' => 'Open',
        'flag' => null,
        'escalated_at' => now()->subHours(2),
        'last_reply' => now()->subHours(5),
    ], $attrs));
}

test('a reply that arrives by email restarts the clock', function () {
    $ticket = silentTicket();

    app(TicketService::class)->addReply($ticket, [
        'client_id' => $ticket->client_id,
        'message' => 'Still not working, any news?',
    ]);

    expect($ticket->fresh()->escalated_at)->toBeNull();
});

test('a reply that arrives through the api restarts the clock', function () {
    $ticket = silentTicket();
    $credential = ApiCredential::factory()->create();

    $this->withHeaders([
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ])->postJson('/api/v1/addticketreply', [
        'ticketid' => $ticket->id,
        'message' => 'Replying from the helpdesk integration.',
    ])->assertSuccessful();

    expect($ticket->fresh()->escalated_at)->toBeNull();
});

test('a ticket answered by email can be escalated again if it goes quiet', function () {
    $ticket = silentTicket();

    TicketEscalation::create([
        'name' => 'Chase customer',
        'time_elapsed' => 60,
        'statuses' => ['Open', 'Customer-Reply'],
        'add_reply' => 'Are you still there?',
    ]);

    // The customer answers by email, then hears nothing for another hour.
    app(TicketService::class)->addReply($ticket, [
        'client_id' => $ticket->client_id,
        'message' => 'Still not working, any news?',
    ]);
    Ticket::whereKey($ticket->id)->update(['last_reply' => now()->subMinutes(61)]);

    expect(app(TicketEscalationService::class)->checkAndEscalate())->toBe(1);
});

test('the escalation auto-reply does not restart its own clock', function () {
    $ticket = silentTicket(['escalated_at' => null]);

    TicketEscalation::create([
        'name' => 'Chase customer',
        'time_elapsed' => 60,
        'statuses' => ['Open'],
        'add_reply' => 'Are you still there?',
    ]);

    $service = app(TicketEscalationService::class);

    expect($service->checkAndEscalate())->toBe(1);

    Ticket::whereKey($ticket->id)->update(['last_reply' => now()->subMinutes(61)]);

    expect($service->checkAndEscalate())->toBe(0)
        ->and($ticket->fresh()->replies()->count())->toBe(1);
});
