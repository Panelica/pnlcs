<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketReply;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Storage;

/**
 * Asking the API for a ticket's attachments.
 *
 * The endpoint answered every call with "success, no attachments" without
 * looking at the ticket at all, so a caller could not tell an empty ticket
 * from one carrying the file they were asking for - and a ticket number that
 * did not exist got the same cheerful answer.
 */
function attachmentApiHeaders(): array
{
    return [
        'X-API-Key' => ApiCredential::factory()->create()->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

function ticketWithFile(string $contents = 'the log file'): Ticket
{
    Storage::fake('local');

    $ticket = Ticket::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'attachment' => 'ticket-attachments/1/error.txt',
    ]);

    Storage::disk('local')->put('ticket-attachments/1/error.txt', $contents);

    return $ticket;
}

test('the attachments of a ticket are listed', function () {
    $ticket = ticketWithFile();

    $this->getJson('/api/v1/getticketattachment?ticketid='.$ticket->id, attachmentApiHeaders())
        ->assertOk()
        ->assertJsonPath('attachments.0.filename', 'error.txt')
        ->assertJsonPath('attachments.0.index', 0);
});

test('a reply attachment is listed with its reply', function () {
    $ticket = ticketWithFile();

    $reply = TicketReply::create([
        'ticket_id' => $ticket->id,
        'client_id' => $ticket->client_id,
        'message' => 'and the screenshot',
        'attachment' => 'ticket-attachments/1/shot.png',
    ]);

    $this->getJson('/api/v1/getticketattachment?ticketid='.$ticket->id, attachmentApiHeaders())
        ->assertOk()
        ->assertJsonPath('attachments.1.replyid', $reply->id)
        ->assertJsonPath('attachments.1.filename', 'shot.png');
});

test('the file itself can be read', function () {
    $ticket = ticketWithFile('the log file');

    $this->getJson('/api/v1/getticketattachment?ticketid='.$ticket->id.'&attachmentindex=0', attachmentApiHeaders())
        ->assertOk()
        ->assertJsonPath('filename', 'error.txt')
        ->assertJsonPath('data', base64_encode('the log file'));
});

test('a ticket that does not exist is not answered with success', function () {
    $this->getJson('/api/v1/getticketattachment?ticketid=999999', attachmentApiHeaders())
        ->assertNotFound();
});

test('an attachment that is not there is not answered with success', function () {
    $ticket = ticketWithFile();

    $this->getJson('/api/v1/getticketattachment?ticketid='.$ticket->id.'&attachmentindex=7', attachmentApiHeaders())
        ->assertNotFound();
});
