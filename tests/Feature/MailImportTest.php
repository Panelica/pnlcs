<?php

use App\Contracts\MailboxClientInterface;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketMailLog;
use App\Models\TicketReply;
use App\Services\Mail\RawMessageParser;
use App\Services\TicketMailImportService;

class FakeMailboxClient implements MailboxClientInterface
{
    public array $messages = [];
    public array $processed = [];

    public function fetchMessages(TicketDepartment $department, int $limit = 25): array
    {
        return $this->messages;
    }

    public function markProcessed(TicketDepartment $department, string $uid): void
    {
        $this->processed[] = $uid;
    }

    public function disconnect(): void {}
}

function mailDept(array $attrs = []): TicketDepartment
{
    return TicketDepartment::factory()->create(array_merge([
        'email'         => 'support@pnlcs-test.com',
        'import_active' => true,
    ], $attrs));
}

function rawPlainMail(string $from, string $subject, string $body): string
{
    return "From: Test Sender <{$from}>\r\n"
        . "To: support@pnlcs-test.com\r\n"
        . "Subject: {$subject}\r\n"
        . "Message-ID: <" . uniqid() . "@test>\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "\r\n"
        . $body . "\r\n";
}

// ---------------------------------------------------------------------------
// RawMessageParser
// ---------------------------------------------------------------------------

test('parser handles a plain text mail', function () {
    $parsed = app(RawMessageParser::class)->parse(rawPlainMail('user@example.com', 'Help me', "My site is down.\nPlease check."));

    expect($parsed['from_email'])->toBe('user@example.com')
        ->and($parsed['from_name'])->toBe('Test Sender')
        ->and($parsed['subject'])->toBe('Help me')
        ->and($parsed['body_text'])->toContain('My site is down.')
        ->and($parsed['auto_submitted'])->toBeFalse();
});

test('parser decodes RFC2047 subject and base64 multipart with utf-8 turkish text', function () {
    $bodyText = base64_encode("Merhaba, şifremi sıfırlayabilir misiniz? Teşekkürler — Ünal");
    $raw = "From: =?UTF-8?B?w5xuYWwgWcSxbGRpeg==?= <unal@example.com>\r\n"
        . "Subject: =?UTF-8?B?xZ5pZnJlIHPEsWbEsXJsYW1h?=\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: multipart/alternative; boundary=\"BOUND1\"\r\n"
        . "\r\n"
        . "--BOUND1\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "\r\n"
        . chunk_split($bodyText) . "\r\n"
        . "--BOUND1\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "\r\n"
        . "<p>html ignored when plain exists</p>\r\n"
        . "--BOUND1--\r\n";

    $parsed = app(RawMessageParser::class)->parse($raw);

    expect($parsed['subject'])->toBe('Şifre sıfırlama')
        ->and($parsed['from_email'])->toBe('unal@example.com')
        ->and($parsed['body_text'])->toContain('şifremi sıfırlayabilir')
        ->and($parsed['body_text'])->not->toContain('html ignored');
});

test('parser extracts attachments and falls back to html body', function () {
    $pdf = base64_encode('%PDF-1.4 fake');
    $raw = "From: user@example.com\r\n"
        . "Subject: With attachment\r\n"
        . "Content-Type: multipart/mixed; boundary=\"OUTER\"\r\n"
        . "\r\n"
        . "--OUTER\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "\r\n"
        . "<p>Hello <b>world</b></p><br><p>Second line</p>\r\n"
        . "--OUTER\r\n"
        . "Content-Type: application/pdf; name=\"invoice.pdf\"\r\n"
        . "Content-Disposition: attachment; filename=\"invoice.pdf\"\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "\r\n"
        . chunk_split($pdf) . "\r\n"
        . "--OUTER--\r\n";

    $parsed = app(RawMessageParser::class)->parse($raw);

    expect($parsed['body_text'])->toContain('Hello world')
        ->and($parsed['attachments'])->toHaveCount(1)
        ->and($parsed['attachments'][0]['filename'])->toBe('invoice.pdf')
        ->and($parsed['attachments'][0]['content'])->toBe('%PDF-1.4 fake');
});

// ---------------------------------------------------------------------------
// Import flow
// ---------------------------------------------------------------------------

test('mail from a known client creates a ticket', function () {
    $dept = mailDept();
    $client = Client::factory()->create(['email' => 'known@example.com']);

    $fake = new FakeMailboxClient();
    $fake->messages = [['uid' => '1', 'raw' => rawPlainMail('known@example.com', 'Server down', 'Please help.')]];
    app()->instance(MailboxClientInterface::class, $fake);

    $totals = app(TicketMailImportService::class)->importDepartment($dept);

    $ticket = Ticket::where('client_id', $client->id)->first();

    expect($totals['created'])->toBe(1)
        ->and($ticket)->not->toBeNull()
        ->and($ticket->title)->toBe('Server down')
        ->and($ticket->department_id)->toBe($dept->id)
        ->and($fake->processed)->toBe(['1'])
        ->and(TicketMailLog::where('status', 'like', 'ticket_created:%')->exists())->toBeTrue();
});

test('reply subject with ticket number appends a reply instead of a new ticket', function () {
    $dept = mailDept();
    $client = Client::factory()->create(['email' => 'replier@example.com']);
    $ticket = Ticket::factory()->create([
        'client_id' => $client->id, 'department_id' => $dept->id,
        'email' => 'replier@example.com', 'status' => 'Answered',
    ]);

    $fake = new FakeMailboxClient();
    $fake->messages = [['uid' => '9', 'raw' => rawPlainMail('replier@example.com', "Re: [Ticket #{$ticket->tid}] Server down", 'Still broken!')]];
    app()->instance(MailboxClientInterface::class, $fake);

    $totals = app(TicketMailImportService::class)->importDepartment($dept);

    expect($totals['replied'])->toBe(1)
        ->and(Ticket::count())->toBe(1)
        ->and(TicketReply::where('ticket_id', $ticket->id)->count())->toBe(1)
        ->and($ticket->fresh()->status)->toBe('Customer-Reply');
});

test('unknown sender is rejected when department does not allow it', function () {
    $dept = mailDept(['import_allow_unknown' => false]);

    $fake = new FakeMailboxClient();
    $fake->messages = [['uid' => '2', 'raw' => rawPlainMail('stranger@example.com', 'Buy my SEO', 'spam')]];
    app()->instance(MailboxClientInterface::class, $fake);

    $totals = app(TicketMailImportService::class)->importDepartment($dept);

    expect($totals['rejected'])->toBe(1)
        ->and(Ticket::count())->toBe(0)
        ->and(TicketMailLog::where('status', 'rejected_unknown')->exists())->toBeTrue();
});

test('unknown sender creates a ticket when the department allows it', function () {
    $dept = mailDept(['import_allow_unknown' => true]);

    $fake = new FakeMailboxClient();
    $fake->messages = [['uid' => '3', 'raw' => rawPlainMail('newlead@example.com', 'Presales question', 'Do you sell VPS?')]];
    app()->instance(MailboxClientInterface::class, $fake);

    $totals = app(TicketMailImportService::class)->importDepartment($dept);

    $ticket = Ticket::first();

    expect($totals['created'])->toBe(1)
        ->and($ticket->client_id)->toBeNull()
        ->and($ticket->email)->toBe('newlead@example.com');
});

test('auto-replies and mailer-daemon bounces are skipped', function () {
    $dept = mailDept(['import_allow_unknown' => true]);
    Client::factory()->create(['email' => 'ooo@example.com']);

    $auto = "From: ooo@example.com\r\nSubject: Out of office\r\nAuto-Submitted: auto-replied\r\nContent-Type: text/plain\r\n\r\nI am away.\r\n";
    $bounce = rawPlainMail('MAILER-DAEMON@mx.example.com', 'Undelivered Mail', 'bounce');
    $selfLoop = rawPlainMail('support@pnlcs-test.com', 'Loop', 'loop');

    $fake = new FakeMailboxClient();
    $fake->messages = [
        ['uid' => '4', 'raw' => $auto],
        ['uid' => '5', 'raw' => $bounce],
        ['uid' => '6', 'raw' => $selfLoop],
    ];
    app()->instance(MailboxClientInterface::class, $fake);

    $totals = app(TicketMailImportService::class)->importDepartment($dept);

    expect($totals['skipped'])->toBe(3)
        ->and(Ticket::count())->toBe(0);
});

test('attachments are stored and linked to the ticket', function () {
    \Illuminate\Support\Facades\Storage::fake('local');

    $dept = mailDept();
    Client::factory()->create(['email' => 'attach@example.com']);

    $png = base64_encode('fake-png-bytes');
    $raw = "From: attach@example.com\r\n"
        . "Subject: Screenshot attached\r\n"
        . "Content-Type: multipart/mixed; boundary=\"XX\"\r\n"
        . "\r\n"
        . "--XX\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nSee screenshot.\r\n"
        . "--XX\r\nContent-Type: image/png; name=\"shot.png\"\r\nContent-Disposition: attachment; filename=\"shot.png\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split($png)
        . "--XX--\r\n";

    $fake = new FakeMailboxClient();
    $fake->messages = [['uid' => '7', 'raw' => $raw]];
    app()->instance(MailboxClientInterface::class, $fake);

    app(TicketMailImportService::class)->importDepartment($dept);

    $ticket = Ticket::first();

    expect($ticket->attachment)->not->toBeNull()
        ->and(\Illuminate\Support\Facades\Storage::disk('local')->exists(explode(',', $ticket->attachment)[0]))->toBeTrue();
});

test('import password is stored encrypted', function () {
    $dept = mailDept(['import_password' => 'super-secret']);

    $rawValue = \Illuminate\Support\Facades\DB::table('ticket_departments')->where('id', $dept->id)->value('import_password');

    expect($rawValue)->not->toContain('super-secret')
        ->and($dept->fresh()->import_password)->toBe('super-secret');
});
