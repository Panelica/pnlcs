<?php

use App\Mail\TicketReplyMail;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketEscalation;
use App\Services\TicketEscalationService;
use Illuminate\Support\Facades\Mail;

/**
 * The message the escalation rule was told to send.
 *
 * An escalation rule can carry a reply - "sorry for the delay, we are looking
 * into this" - and the point of it is that the customer hears something while
 * nobody has answered them. Every other reply, from staff or from the API,
 * raises the reply event that sends it on.
 *
 * This one wrote the reply straight into the ticket and raised nothing. The
 * message sat in the panel, and the customer who had been waiting long enough
 * to trigger an escalation heard nothing at all - which is the one thing the
 * rule existed to prevent.
 */
function autoReplyRule(string $message = 'Sorry for the delay, we are looking into this.'): TicketEscalation
{
    return TicketEscalation::create([
        'name' => 'Chase',
        'time_elapsed' => 60,
        'statuses' => ['Open'],
        'departments' => null,
        'priorities' => null,
        'add_reply' => $message,
    ]);
}

function waitingTicket(int $silentMinutes = 90): Ticket
{
    return Ticket::create([
        'tid' => strtoupper(substr(md5(uniqid('', true)), 0, 6)),
        'department_id' => TicketDepartment::create(['name' => 'Support '.uniqid()])->id,
        'client_id' => Client::factory()->create()->id,
        'name' => 'A customer',
        'email' => 'waiting@example.test',
        'title' => 'Nobody has answered',
        'message' => 'Hello?',
        'status' => 'Open',
        'priority' => 'low',
        'last_reply' => now()->subMinutes($silentMinutes),
    ]);
}

it('sends the customer the reply the rule was told to send', function () {
    Mail::fake();

    autoReplyRule('Sorry for the delay, we are looking into this.');
    $ticket = waitingTicket();

    app(TicketEscalationService::class)->checkAndEscalate();

    Mail::assertQueued(TicketReplyMail::class, function (TicketReplyMail $mail) use ($ticket) {
        return $mail->ticket->id === $ticket->id
            && $mail->isStaffReply === true
            && str_contains($mail->replyMessage, 'Sorry for the delay');
    });
});

it('sends it to the customer, not to the support inbox', function () {
    Mail::fake();

    autoReplyRule();
    $ticket = waitingTicket();

    app(TicketEscalationService::class)->checkAndEscalate();

    Mail::assertQueued(TicketReplyMail::class, fn (TicketReplyMail $mail) => $mail->hasTo($ticket->email));
});

it('says nothing when the rule carries no reply', function () {
    Mail::fake();

    TicketEscalation::create([
        'name' => 'Quiet nudge',
        'time_elapsed' => 60,
        'statuses' => ['Open'],
        'new_priority' => 'high',
    ]);

    waitingTicket();

    app(TicketEscalationService::class)->checkAndEscalate();

    Mail::assertNothingQueued();
});

it('does not send it twice while the silence lasts', function () {
    Mail::fake();

    autoReplyRule();
    waitingTicket();

    $service = app(TicketEscalationService::class);
    $service->checkAndEscalate();
    $service->checkAndEscalate();

    Mail::assertQueuedCount(1);
});
