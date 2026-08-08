<?php

use App\Events\TicketReplied;
use App\Mail\TicketReplyMail;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * A staff answer the customer never hears about.
 *
 * Four places add a reply to a ticket - the client area, the admin screen, the
 * escalation rule and the mail import - and all four raise TicketReplied, which
 * is what sends the customer the answer and what the notification rules listen
 * for. The API's addticketreply raised nothing: the reply was written into the
 * ticket and the customer was left waiting, with the panel showing the ticket
 * as answered.
 *
 * It also wrote the status in lower case, 'answered', where every other door
 * writes 'Answered'. MySQL compares the two the same, so nothing complained.
 */
function apiTicketHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function apiRepliableTicket(): Ticket
{
    return Ticket::create([
        'tid' => '482913',
        'department_id' => TicketDepartment::create(['name' => 'Support '.uniqid()])->id,
        'client_id' => Client::factory()->create(['email' => 'buyer@example.test'])->id,
        'name' => 'Aylin Kaya',
        'email' => 'buyer@example.test',
        'title' => 'Mail delivery is failing',
        'message' => 'Nothing leaves the server.',
        'status' => 'Open',
        'priority' => 'medium',
        'last_reply' => now()->subDay(),
    ]);
}

it('tells the customer when staff answer over the api', function () {
    Mail::fake();

    $ticket = apiRepliableTicket();

    $this->withHeaders(apiTicketHeaders())->postJson('/api/v1/addticketreply', [
        'ticketid' => $ticket->id,
        'message' => 'We have restarted the mail service.',
        'adminusername' => 'support',
    ])->assertSuccessful();

    Mail::assertQueued(TicketReplyMail::class, fn ($mail) => $mail->hasTo('buyer@example.test'));
});

it('raises the same event the other doors raise', function () {
    Event::fake([TicketReplied::class]);

    $ticket = apiRepliableTicket();

    $this->withHeaders(apiTicketHeaders())->postJson('/api/v1/addticketreply', [
        'ticketid' => $ticket->id,
        'message' => 'We have restarted the mail service.',
        'adminusername' => 'support',
    ])->assertSuccessful();

    Event::assertDispatched(TicketReplied::class, fn ($event) => $event->isStaffReply === true
        && $event->ticket->id === $ticket->id);
});

it('marks a customer reply as coming from the customer', function () {
    Event::fake([TicketReplied::class]);

    $ticket = apiRepliableTicket();

    $this->withHeaders(apiTicketHeaders())->postJson('/api/v1/addticketreply', [
        'ticketid' => $ticket->id,
        'message' => 'Still not working.',
    ])->assertSuccessful();

    Event::assertDispatched(TicketReplied::class, fn ($event) => $event->isStaffReply === false);

    expect(strtolower((string) $ticket->fresh()->status))->toBe('customer-reply');
});

it('writes the status the way every other door writes it', function () {
    $ticket = apiRepliableTicket();

    $this->withHeaders(apiTicketHeaders())->postJson('/api/v1/addticketreply', [
        'ticketid' => $ticket->id,
        'message' => 'We have restarted the mail service.',
        'adminusername' => 'support',
    ])->assertSuccessful();

    expect($ticket->fresh()->status)->toBe('Answered');
});

it('still records the reply and hands back its id', function () {
    $ticket = apiRepliableTicket();

    $response = $this->withHeaders(apiTicketHeaders())->postJson('/api/v1/addticketreply', [
        'ticketid' => $ticket->id,
        'message' => 'We have restarted the mail service.',
        'adminusername' => 'support',
    ])->assertSuccessful();

    expect($ticket->fresh()->replies()->count())->toBe(1)
        ->and($response->json('replyid') ?? $response->json('data.replyid'))->not->toBeNull();
});
