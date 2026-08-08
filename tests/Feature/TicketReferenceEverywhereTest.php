<?php

use App\Models\ApiCredential;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Services\TicketMailImportService;
use Database\Factories\ApiCredentialFactory;

/**
 * The other two doors that made up their own ticket reference.
 *
 * A ticket's reference is six digits, checked to be free, because the mail
 * import matches replies on exactly that. The client area's form was fixed to
 * go through the one creator; the public contact form and the API's openticket
 * were still calling Ticket::create by hand with
 * strtoupper(Str::random(6)) - letters and digits, unchecked.
 *
 * The contact form is the door where it matters most: whoever writes in gets a
 * ticket-opened email with the reference in the subject, replies to it, and
 * their reply opens a second ticket instead of joining the first.
 */
function referenceDepartment(): TicketDepartment
{
    return TicketDepartment::create([
        'name' => 'Sales '.uniqid(),
        'email' => 'sales@example.com',
        'hidden' => false,
    ]);
}

it('gives a contact form ticket the reference the mail import matches', function () {
    $department = referenceDepartment();

    $this->post(route('client.contact.submit'), [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'department_id' => $department->id,
        'subject' => 'Pre-sales question',
        'message' => 'Do you offer daily backups?',
    ])->assertRedirect();

    expect(Ticket::firstOrFail()->tid)->toMatch('/^\d{6}$/');
});

it('keeps a contact form reply on the ticket it answers', function () {
    $department = referenceDepartment();

    $this->post(route('client.contact.submit'), [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'department_id' => $department->id,
        'subject' => 'Pre-sales question',
        'message' => 'Do you offer daily backups?',
    ])->assertRedirect();

    $ticket = Ticket::firstOrFail();

    $outcome = app(TicketMailImportService::class)->importRawMessage(
        $department,
        "From: visitor@example.test\r\nTo: sales@example.com\r\nSubject: Re: [Ticket #{$ticket->tid}] Pre-sales question\r\n\r\nAny news?"
    );

    expect($outcome)->toBe('replied')
        ->and(Ticket::count())->toBe(1);
});

it('gives an api ticket the same kind of reference', function () {
    $department = referenceDepartment();
    $credential = ApiCredential::factory()->create();

    $response = $this->withHeaders([
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ])->postJson('/api/v1/openticket', [
        'deptid' => $department->id,
        'subject' => 'Opened over the api',
        'message' => 'Please look into this.',
        'email' => 'api@example.test',
    ]);

    $response->assertSuccessful();

    $ticket = Ticket::firstOrFail();

    expect($ticket->tid)->toMatch('/^\d{6}$/')
        ->and($response->json('tid') ?? $response->json('data.tid'))->toBe($ticket->tid);
});

it('still records what the contact form was told', function () {
    $department = referenceDepartment();

    $this->post(route('client.contact.submit'), [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'department_id' => $department->id,
        'subject' => 'Pre-sales question',
        'message' => 'Do you offer daily backups?',
    ])->assertRedirect();

    $ticket = Ticket::firstOrFail();

    expect($ticket->name)->toBe('Visitor')
        ->and($ticket->email)->toBe('visitor@example.test')
        ->and($ticket->title)->toBe('Pre-sales question')
        ->and($ticket->department_id)->toBe($department->id)
        ->and(strtolower((string) $ticket->status))->toBe('open')
        ->and($ticket->last_reply)->not->toBeNull();
});
