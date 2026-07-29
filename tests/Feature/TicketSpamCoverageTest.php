<?php

use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketSpamFilter;
use App\Services\TicketMailImportService;

/**
 * The spam controls — banned addresses, banned keywords, a per-hour limit —
 * are set on an admin screen and were applied to exactly one path: the ticket
 * form of a customer who has already logged in.
 *
 * The two ways spam actually arrives were not checked at all: the public
 * contact form, which needs no account, and tickets piped in from email.
 */
function spamDepartment(): TicketDepartment
{
    return TicketDepartment::factory()->create(['import_allow_unknown' => true]);
}

function rawEmail(string $from, string $subject, string $body): string
{
    return "From: {$from}\r\nTo: support@example.com\r\nSubject: {$subject}\r\n\r\n{$body}";
}

test('the public contact form refuses a banned keyword', function () {
    TicketSpamFilter::create(['type' => 'keyword', 'content' => 'crypto casino']);
    $department = spamDepartment();

    $this->post(route('client.contact.submit'), [
        'name' => 'Spammer',
        'email' => 'spam@example.com',
        'department_id' => $department->id,
        'subject' => 'Best crypto casino offer',
        'message' => 'Click here',
    ])->assertRedirect();

    expect(Ticket::count())->toBe(0);
});

test('the public contact form refuses a banned address', function () {
    TicketSpamFilter::create(['type' => 'email', 'content' => '@spam.test']);
    $department = spamDepartment();

    $this->post(route('client.contact.submit'), [
        'name' => 'Spammer',
        'email' => 'someone@spam.test',
        'department_id' => $department->id,
        'subject' => 'Hello',
        'message' => 'A normal looking message',
    ])->assertRedirect();

    expect(Ticket::count())->toBe(0);
});

test('a genuine contact message still gets through', function () {
    TicketSpamFilter::create(['type' => 'keyword', 'content' => 'crypto casino']);
    $department = spamDepartment();

    $this->post(route('client.contact.submit'), [
        'name' => 'Real Customer',
        'email' => 'real@example.com',
        'department_id' => $department->id,
        'subject' => 'Question about my invoice',
        'message' => 'When is it due?',
    ])->assertRedirect();

    expect(Ticket::count())->toBe(1);
});

test('the contact form honours the per-hour limit', function () {
    Setting::updateOrCreate(['setting' => 'TicketSpamMaxPerHour'], ['value' => '2']);
    $department = spamDepartment();

    for ($i = 0; $i < 4; $i++) {
        $this->post(route('client.contact.submit'), [
            'name' => 'Flooder',
            'email' => 'flood@example.com',
            'department_id' => $department->id,
            'subject' => "Message {$i}",
            'message' => 'Again and again',
        ]);
    }

    expect(Ticket::where('email', 'flood@example.com')->count())->toBe(2);
});

test('an emailed ticket from a banned address is not opened', function () {
    TicketSpamFilter::create(['type' => 'email', 'content' => '@spam.test']);
    $department = spamDepartment();

    $result = app(TicketMailImportService::class)->importRawMessage(
        $department,
        rawEmail('bulk@spam.test', 'Cheap offers', 'Buy now')
    );

    expect($result)->toBe('rejected')
        ->and(Ticket::count())->toBe(0);
});

test('an emailed ticket with a banned keyword is not opened', function () {
    TicketSpamFilter::create(['type' => 'keyword', 'content' => 'crypto casino']);
    $department = spamDepartment();

    $result = app(TicketMailImportService::class)->importRawMessage(
        $department,
        rawEmail('someone@example.com', 'Best crypto casino', 'Click here')
    );

    expect($result)->toBe('rejected')
        ->and(Ticket::count())->toBe(0);
});

test('a genuine emailed ticket is still opened', function () {
    TicketSpamFilter::create(['type' => 'keyword', 'content' => 'crypto casino']);
    $department = spamDepartment();

    $result = app(TicketMailImportService::class)->importRawMessage(
        $department,
        rawEmail('customer@example.com', 'My site is down', 'Please help')
    );

    expect($result)->toBe('created')
        ->and(Ticket::count())->toBe(1);
});
