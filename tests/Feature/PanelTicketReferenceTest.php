<?php

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;
use App\Services\TicketMailImportService;

/**
 * A ticket opened in the panel that its own customer cannot reply to by email.
 *
 * Tickets have one creator, TicketService::createTicket, which gives each one a
 * six-digit reference and checks it is not already taken. The mail import
 * matches replies on exactly that: six digits in the subject, [Ticket #482913].
 *
 * The client area's ticket form did not use it. It called Ticket::create by
 * hand with strtoupper(Str::random(6)) - letters and digits, and nothing
 * checking it was unique. So every ticket opened through the panel carried a
 * reference the import cannot recognise: the customer's emailed reply did not
 * join the thread, it opened a second ticket, and the staff answer they were
 * replying to sat in the first one.
 */
function panelTicketDepartment(): TicketDepartment
{
    return TicketDepartment::create(['name' => 'Support '.uniqid(), 'email' => 'support@example.com', 'hidden' => false]);
}

function panelTicketCustomer(): User
{
    $client = Client::factory()->create(['email' => 'buyer@example.test']);
    $user = User::factory()->create(['email' => 'buyer@example.test']);
    $user->clients()->attach($client->id);

    test()->actingAs($user);

    return $user;
}

function openPanelTicket(TicketDepartment $department): Ticket
{
    test()->post(route('client.tickets.store'), [
        'department_id' => $department->id,
        'subject' => 'Site is slow',
        'message' => 'Pages take ten seconds.',
        'priority' => 'medium',
    ])->assertRedirect();

    return Ticket::latest('id')->firstOrFail();
}

it('gives a panel ticket the reference its own mail import matches', function () {
    $department = panelTicketDepartment();
    panelTicketCustomer();

    expect(openPanelTicket($department)->tid)->toMatch('/^\d{6}$/');
});

it('adds an emailed reply to the ticket it answers', function () {
    $department = panelTicketDepartment();
    panelTicketCustomer();

    $ticket = openPanelTicket($department);

    $outcome = app(TicketMailImportService::class)->importRawMessage(
        $department,
        "From: buyer@example.test\r\nTo: support@example.com\r\nSubject: Re: [Ticket #{$ticket->tid}] Site is slow\r\n\r\nStill slow today."
    );

    expect($outcome)->toBe('replied')
        ->and(Ticket::count())->toBe(1)
        ->and($ticket->fresh()->replies()->count())->toBe(1);
});

it('opens the ticket the customer asked for', function () {
    $department = panelTicketDepartment();
    panelTicketCustomer();

    $ticket = openPanelTicket($department);

    expect($ticket->title)->toBe('Site is slow')
        ->and($ticket->department_id)->toBe($department->id)
        ->and(strtolower($ticket->status))->toBe('open')
        ->and($ticket->last_reply)->not->toBeNull()
        ->and($ticket->email)->toBe('buyer@example.test');
});

it('does not hand out a reference another ticket already has', function () {
    $department = panelTicketDepartment();
    panelTicketCustomer();

    $first = openPanelTicket($department);
    $second = openPanelTicket($department);

    expect($second->tid)->not->toBe($first->tid);
});
