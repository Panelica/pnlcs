<?php

namespace App\Services;

use App\Contracts\MailboxClientInterface;
use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketMailLog;
use App\Services\Mail\RawMessageParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Email → ticket pipeline (WHMCS mail import parity).
 *
 * Subject containing [Ticket #123456] appends a reply to that ticket;
 * anything else opens a new ticket. Auto-replies and mail loops are
 * dropped, unknown senders are rejected unless the department allows them.
 */
class TicketMailImportService
{
    private const TID_PATTERN = '/\[?\s*Ticket\s*(?:ID\s*)?[:#]?\s*#?\s*(\d{6})\s*\]?/i';

    private const MAX_ATTACHMENTS = 5;
    private const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;
    private const ALLOWED_ATTACHMENT_EXT = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'log', 'zip'];

    public function __construct(
        private MailboxClientInterface $mailbox,
        private RawMessageParser $parser,
        private TicketService $tickets,
    ) {}

    /**
     * Import every active department's mailbox.
     *
     * @return array<string, int> counters
     */
    public function importAll(): array
    {
        $totals = ['created' => 0, 'replied' => 0, 'rejected' => 0, 'skipped' => 0, 'errors' => 0];

        $departments = TicketDepartment::where('import_active', true)->get();

        foreach ($departments as $department) {
            $counters = $this->importDepartment($department);
            foreach ($counters as $key => $count) {
                $totals[$key] += $count;
            }
        }

        return $totals;
    }

    /** @return array<string, int> */
    public function importDepartment(TicketDepartment $department): array
    {
        $counters = ['created' => 0, 'replied' => 0, 'rejected' => 0, 'skipped' => 0, 'errors' => 0];

        try {
            $messages = $this->mailbox->fetchMessages($department);

            foreach ($messages as $message) {
                try {
                    $outcome = $this->importRawMessage($department, $message['raw']);
                    $counters[$outcome]++;
                    $this->mailbox->markProcessed($department, $message['uid']);
                } catch (\Throwable $e) {
                    $counters['errors']++;
                    Log::error('Mail import: message failed', [
                        'department_id' => $department->id, 'uid' => $message['uid'], 'error' => $e->getMessage(),
                    ]);
                    // Message intentionally NOT marked processed — retried next run.
                }
            }

            $department->update(['last_import_at' => now()]);
        } finally {
            $this->mailbox->disconnect();
        }

        return $counters;
    }

    /**
     * Import one raw MIME message. Returns the outcome counter key:
     * created | replied | rejected | skipped.
     */
    public function importRawMessage(TicketDepartment $department, string $raw): string
    {
        $mail = $this->parser->parse($raw);

        // Loop / auto-reply protection
        if ($mail['auto_submitted']
            || $mail['from_email'] === ''
            || preg_match('/mailer-daemon|postmaster@|no-?reply@/i', $mail['from_email'])
            || strcasecmp($mail['from_email'], (string) $department->email) === 0) {
            $this->log($department, $mail, 'skipped_auto');
            return 'skipped';
        }

        $client = Client::whereRaw('LOWER(email) = ?', [$mail['from_email']])->first();
        $subject = $mail['subject'] !== '' ? $mail['subject'] : '(no subject)';
        $body = $mail['body_text'] !== '' ? $mail['body_text'] : '(empty message)';

        // Reply to an existing ticket?
        if (preg_match(self::TID_PATTERN, $subject, $m)) {
            $ticket = Ticket::where('tid', $m[1])->first();

            if ($ticket && $this->senderMayReply($ticket, $client, $mail['from_email'])) {
                $attachment = $this->storeAttachments($ticket, $mail['attachments']);

                $reply = $this->tickets->addReply($ticket, [
                    'client_id' => $ticket->client_id,
                    'message'   => $body,
                ]);
                if ($attachment) {
                    $reply->update(['attachment' => $attachment]);
                }

                event(new TicketReplied($ticket->fresh(), $body, false));
                $this->log($department, $mail, "reply_added:{$ticket->tid}");

                return 'replied';
            }
            // Unknown/foreign tid → fall through and treat as a new ticket request.
        }

        if (!$client && !$department->import_allow_unknown) {
            $this->log($department, $mail, 'rejected_unknown');
            return 'rejected';
        }

        $ticket = $this->tickets->createTicket([
            'department_id' => $department->id,
            'client_id'     => $client?->id,
            'name'          => $mail['from_name'] ?: ($client ? trim($client->first_name . ' ' . $client->last_name) : $mail['from_email']),
            'email'         => $mail['from_email'],
            'title'         => Str::limit($subject, 250, ''),
            'message'       => $body,
            'priority'      => 'medium',
        ]);

        $attachment = $this->storeAttachments($ticket, $mail['attachments']);
        if ($attachment) {
            $ticket->update(['attachment' => $attachment]);
        }

        event(new TicketOpened($ticket->fresh(), false));
        $this->log($department, $mail, "ticket_created:{$ticket->tid}");

        return 'created';
    }

    /**
     * A reply is accepted when the sender owns the ticket (same client) or
     * the sender address matches the ticket's email.
     */
    private function senderMayReply(Ticket $ticket, ?Client $client, string $fromEmail): bool
    {
        if ($client && $ticket->client_id && $ticket->client_id === $client->id) {
            return true;
        }

        return strcasecmp((string) $ticket->email, $fromEmail) === 0;
    }

    /**
     * Store safe attachments under ticket-attachments/{id}; returns the
     * comma-separated path list (matches TicketController convention).
     */
    private function storeAttachments(Ticket $ticket, array $attachments): ?string
    {
        $stored = [];

        foreach (array_slice($attachments, 0, self::MAX_ATTACHMENTS) as $attachment) {
            $ext = strtolower(pathinfo($attachment['filename'], PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_ATTACHMENT_EXT, true)) {
                continue;
            }
            if (strlen($attachment['content']) > self::MAX_ATTACHMENT_BYTES || $attachment['content'] === '') {
                continue;
            }

            $safeName = Str::random(20) . '.' . $ext;
            $path = "ticket-attachments/{$ticket->id}/{$safeName}";
            Storage::disk('local')->put($path, $attachment['content']);
            $stored[] = $path;
        }

        return $stored ? implode(',', $stored) : null;
    }

    private function log(TicketDepartment $department, array $mail, string $status): void
    {
        try {
            TicketMailLog::create([
                'date'    => now(),
                'to'      => $department->email ?: $department->import_username,
                'name'    => Str::limit((string) $mail['from_name'], 250, ''),
                'email'   => Str::limit((string) $mail['from_email'], 250, ''),
                'subject' => Str::limit((string) $mail['subject'], 250, ''),
                'message' => Str::limit((string) $mail['body_text'], 5000),
                'status'  => $status,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Mail import: log write failed: ' . $e->getMessage());
        }
    }
}
