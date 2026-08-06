<?php

use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Models\Client;
use App\Models\NotificationProvider;
use App\Models\NotificationRule;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * An alert that names a ticket nobody can find.
 *
 * The Slack/webhook alert for a new ticket is built from
 * $ticket->subject - a column the tickets table does not have; the subject an
 * operator typed lives in title, which is what the ticket mails and both admin
 * screens read. So the alert arrives with the subject missing.
 *
 * It also numbers the ticket by its row id, while everything a person sees -
 * the mail subject, the mail body, the ticket list, the ticket page - numbers
 * it by tid. The operator is handed a reference that matches nothing they can
 * search for.
 */
function alertingTicket(array $attributes = []): Ticket
{
    return Ticket::create(array_merge([
        'tid' => '482913',
        'department_id' => TicketDepartment::create(['name' => 'Support '.uniqid()])->id,
        'client_id' => Client::factory()->create()->id,
        'name' => 'Aylin Kaya',
        'email' => 'aylin@example.test',
        'title' => 'Mail delivery is failing',
        'message' => 'Nothing leaves the server.',
        'status' => 'Open',
        'priority' => 'medium',
        'last_reply' => now(),
    ], $attributes));
}

/**
 * The text of the single Slack alert a rule sent for this event.
 */
function slackAlertTextFor(string $event, callable $raise): string
{
    Mail::fake();
    Http::fake(['*' => Http::response('ok', 200)]);

    $provider = NotificationProvider::create([
        'name' => 'Ops slack',
        'type' => 'slack',
        'settings' => ['webhook_url' => 'https://hooks.slack.test/services/T/B/X'],
        'active' => true,
    ]);

    NotificationRule::create([
        'event' => $event,
        'provider_id' => $provider->id,
        'active' => true,
    ]);

    $raise();

    $text = '';

    Http::assertSent(function ($request) use (&$text) {
        $text = $request->data()['text'] ?? '';

        return true;
    });

    return $text;
}

it('names the ticket subject in a new-ticket alert', function () {
    $ticket = alertingTicket();

    $text = slackAlertTextFor('ticket.opened', fn () => event(new TicketOpened($ticket, false)));

    expect($text)->toContain('Mail delivery is failing');
});

it('numbers a new-ticket alert the way the rest of the panel does', function () {
    $ticket = alertingTicket();

    $text = slackAlertTextFor('ticket.opened', fn () => event(new TicketOpened($ticket, false)));

    expect($text)->toContain('#482913')
        ->and($text)->not->toContain('#'.$ticket->id.':');
});

it('numbers a reply alert the same way', function () {
    $ticket = alertingTicket();

    $text = slackAlertTextFor('ticket.replied', fn () => event(new TicketReplied($ticket, 'Any news?', false)));

    expect($text)->toContain('#482913')
        ->and($text)->toContain('client');
});

it('still says something for a ticket that has no tid yet', function () {
    $ticket = alertingTicket(['tid' => '']);

    $text = slackAlertTextFor('ticket.opened', fn () => event(new TicketOpened($ticket, false)));

    expect($text)->toContain('#'.$ticket->id)
        ->and($text)->toContain('Mail delivery is failing');
});
