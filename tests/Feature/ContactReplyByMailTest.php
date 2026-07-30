<?php

use App\Models\Client;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketReply;
use App\Services\TicketMailImportService;

/**
 * A customer's colleague answering a support email.
 *
 * Support mail is copied to the contacts who asked for it — the accounts
 * department, a technical colleague. When one of them replied, the import
 * refused to recognise them: their address is neither the account's own login
 * nor the address the ticket was opened from, so their answer was filed as a
 * brand new ticket and the thread they were replying to went quiet.
 */
function contactReplyDept(): TicketDepartment
{
    return TicketDepartment::factory()->create([
        'email' => 'support@pnlcs-test.com',
        'import_active' => true,
        'import_allow_unknown' => true,
    ]);
}

function ticketFor(Client $client): Ticket
{
    return Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => contactReplyDept()->id,
        'tid' => '551234',
        'title' => 'Website is slow',
        'email' => $client->email,
        'status' => 'Open',
    ]);
}

function rawReply(string $from, string $tid): string
{
    return "From: Colleague <{$from}>\r\n"
        ."To: support@pnlcs-test.com\r\n"
        ."Subject: Re: [Ticket #{$tid}] Website is slow\r\n"
        .'Message-ID: <'.uniqid().'@example.test>'."\r\n"
        ."\r\n"
        ."Any news on this please?\r\n";
}

test('a contact of the account may answer the thread', function () {
    $client = Client::factory()->create(['email' => 'owner@example.test']);
    $ticket = ticketFor($client);

    Contact::create([
        'client_id' => $client->id,
        'first_name' => 'Technical',
        'last_name' => 'Colleague',
        'email' => 'tech@example.test',
        'support_emails' => true,
    ]);

    $before = Ticket::count();

    app(TicketMailImportService::class)->importRawMessage(
        $ticket->department,
        rawReply('tech@example.test', $ticket->tid)
    );

    expect(Ticket::count())->toBe($before)
        ->and(TicketReply::where('ticket_id', $ticket->id)->count())->toBe(1);
});

test('the account holder can still answer', function () {
    $client = Client::factory()->create(['email' => 'owner2@example.test']);
    $ticket = ticketFor($client);

    $before = Ticket::count();

    app(TicketMailImportService::class)->importRawMessage(
        $ticket->department,
        rawReply('owner2@example.test', $ticket->tid)
    );

    expect(Ticket::count())->toBe($before)
        ->and(TicketReply::where('ticket_id', $ticket->id)->count())->toBe(1);
});

test('a stranger quoting the number does not get into the thread', function () {
    $client = Client::factory()->create(['email' => 'owner3@example.test']);
    $ticket = ticketFor($client);

    $before = Ticket::count();

    app(TicketMailImportService::class)->importRawMessage(
        $ticket->department,
        rawReply('nosy@elsewhere.test', $ticket->tid)
    );

    expect(Ticket::count())->toBe($before + 1)
        ->and(TicketReply::where('ticket_id', $ticket->id)->count())->toBe(0);
});
