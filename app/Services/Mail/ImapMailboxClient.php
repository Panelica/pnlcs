<?php

namespace App\Services\Mail;

use App\Contracts\MailboxClientInterface;
use App\Models\TicketDepartment;
use Illuminate\Support\Facades\Log;

/**
 * php-imap based mailbox access (supports both IMAP and POP3).
 * Fetches raw message source; parsing happens in RawMessageParser.
 */
class ImapMailboxClient implements MailboxClientInterface
{
    /** @var \IMAP\Connection|false|null */
    private $stream = null;

    private ?int $connectedDepartmentId = null;

    public function fetchMessages(TicketDepartment $department, int $limit = 25): array
    {
        $stream = $this->connect($department);
        if (!$stream) {
            return [];
        }

        $isPop3 = $department->import_protocol === 'pop3';

        // POP3 has no \Seen flag — every listed message is unprocessed.
        $uids = $isPop3
            ? (imap_search($stream, 'ALL', SE_UID) ?: [])
            : (imap_search($stream, 'UNSEEN', SE_UID) ?: []);

        $messages = [];
        foreach (array_slice($uids, 0, $limit) as $uid) {
            $header = imap_fetchheader($stream, $uid, FT_UID);
            $body   = imap_body($stream, $uid, FT_UID | FT_PEEK);
            if ($header === false) {
                continue;
            }
            $messages[] = ['uid' => (string) $uid, 'raw' => $header . ($body ?: '')];
        }

        return $messages;
    }

    public function markProcessed(TicketDepartment $department, string $uid): void
    {
        if (!$this->stream) {
            return;
        }

        // POP3 keeps no state — messages must be deleted or they re-import forever.
        if ($department->import_delete || $department->import_protocol === 'pop3') {
            imap_delete($this->stream, $uid, FT_UID);
        } else {
            imap_setflag_full($this->stream, (string) $uid, '\\Seen', ST_UID);
        }
    }

    public function disconnect(): void
    {
        if ($this->stream) {
            imap_expunge($this->stream);
            imap_close($this->stream);
            $this->stream = null;
            $this->connectedDepartmentId = null;
        }
    }

    private function connect(TicketDepartment $department)
    {
        if ($this->stream && $this->connectedDepartmentId === $department->id) {
            return $this->stream;
        }
        $this->disconnect();

        $protocol = $department->import_protocol === 'pop3' ? 'pop3' : 'imap';
        $port = $department->import_port ?: ($protocol === 'pop3' ? 995 : 993);

        $flags = '/' . $protocol;
        $flags .= match ($department->import_encryption) {
            'ssl'  => '/ssl',
            'tls'  => '/tls',
            default => '/notls',
        };
        $flags .= '/novalidate-cert';

        $folder = $protocol === 'pop3' ? 'INBOX' : ($department->import_folder ?: 'INBOX');
        $mailbox = '{' . $department->import_host . ':' . $port . $flags . '}' . $folder;

        $stream = @imap_open($mailbox, (string) $department->import_username, (string) $department->import_password, 0, 1);

        if ($stream === false) {
            Log::error('Mail import: mailbox connection failed', [
                'department_id' => $department->id,
                'mailbox'       => preg_replace('/:[^:]*$/', ':***', $mailbox),
                'error'         => imap_last_error() ?: 'unknown',
            ]);
            return null;
        }

        $this->stream = $stream;
        $this->connectedDepartmentId = $department->id;

        return $stream;
    }
}
