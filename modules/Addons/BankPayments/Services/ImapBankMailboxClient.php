<?php

namespace Modules\Addons\BankPayments\Services;

use Carbon\CarbonInterface;
use IMAP\Connection;
use Modules\Addons\BankPayments\Models\BankMailbox;

/**
 * php-imap transport for a bank mailbox.
 *
 * Unlike the ticket importer this client does not use TicketDepartment and
 * does not search UNSEEN. Bank notifications may have been opened by someone
 * already, so UID/UIDVALIDITY is the source of truth.
 */
class ImapBankMailboxClient implements BankMailboxClient
{
    /** @var Connection|false|null */
    private $stream = null;

    private ?int $connectedMailboxId = null;

    private string $lastError = '';

    public function test(BankMailbox $mailbox): array
    {
        try {
            $stream = $this->connect($mailbox);

            if (! $stream) {
                return [
                    'success' => false,
                    'message' => $this->lastError ?: __('bank-payments::messages.connection_failed'),
                    'uidvalidity' => null,
                ];
            }

            $status = @imap_status($stream, $this->mailboxPath($mailbox), SA_UIDVALIDITY);
            $uidvalidity = is_object($status) && isset($status->uidvalidity)
                ? (int) $status->uidvalidity
                : null;

            $this->disconnect();

            return [
                'success' => true,
                'message' => __('bank-payments::messages.connection_ok'),
                'uidvalidity' => $uidvalidity,
            ];
        } catch (\Throwable $e) {
            $this->disconnect();

            return [
                'success' => false,
                'message' => __('bank-payments::messages.connection_failed').' '.$e->getMessage(),
                'uidvalidity' => null,
            ];
        }
    }

    public function fetch(BankMailbox $mailbox, ?CarbonInterface $since, int $limit): array
    {
        $limit = max(1, min(500, $limit));

        $stream = $this->connect($mailbox);

        if (! $stream) {
            return [
                'uidvalidity' => null,
                'messages' => [],
                'error' => $this->lastError ?: __('bank-payments::messages.connection_failed'),
            ];
        }

        $status = @imap_status($stream, $this->mailboxPath($mailbox), SA_UIDVALIDITY);
        $uidvalidity = is_object($status) && isset($status->uidvalidity)
            ? (int) $status->uidvalidity
            : null;

        // php-imap does not accept a bare UID set as an imap_search()
        // criterion (the server answers "Unknown search criterion: UID").
        // Search by date for the first run, then filter the result by the
        // mailbox's stored UID in PHP. This also keeps the first import
        // bounded and makes a UIDVALIDITY reset safe.
        $criteria = $since
            ? sprintf('SINCE "%s"', $since->format('d-M-Y'))
            : 'ALL';

        // @ is deliberate: an empty mailbox makes imap_search return false,
        // which is not an error. The caller records the result and the next
        // run continues from the stored UID.
        $uids = @imap_search($stream, $criteria, SE_UID) ?: [];
        $uids = array_values(array_filter(
            array_map('intval', $uids),
            fn (int $uid): bool => $mailbox->last_uid === null || $uid > $mailbox->last_uid
        ));
        sort($uids, SORT_NUMERIC);
        $uids = array_slice($uids, 0, $limit);

        $messages = [];

        foreach ($uids as $uid) {
            $header = @imap_fetchheader($stream, $uid, FT_UID);
            $body = @imap_body($stream, $uid, FT_UID | FT_PEEK);

            if ($header === false) {
                continue;
            }

            $messages[] = [
                'uid' => (int) $uid,
                'raw' => $header.($body ?: ''),
            ];
        }

        return [
            'uidvalidity' => $uidvalidity,
            'messages' => $messages,
            'error' => null,
        ];
    }

    public function markSeen(BankMailbox $mailbox, int $uid): void
    {
        $stream = $this->connect($mailbox);

        if (! $stream) {
            return;
        }

        @imap_setflag_full($stream, (string) $uid, '\\Seen', ST_UID);
    }

    public function disconnect(): void
    {
        if ($this->stream) {
            @imap_close($this->stream);
            $this->stream = null;
        }

        $this->connectedMailboxId = null;
    }

    /**
     * @return Connection|false|null
     */
    private function connect(BankMailbox $mailbox)
    {
        if (! function_exists('imap_open')) {
            $this->lastError = __('bank-payments::messages.imap_missing');

            return false;
        }

        if ($this->stream && $this->connectedMailboxId === $mailbox->id) {
            return $this->stream;
        }

        $this->disconnect();

        $stream = @imap_open(
            $this->mailboxPath($mailbox),
            (string) $mailbox->username,
            (string) $mailbox->password,
            0,
            1
        );

        if ($stream === false) {
            $this->lastError = imap_last_error() ?: __('bank-payments::messages.connection_failed');

            return false;
        }

        $this->stream = $stream;
        $this->connectedMailboxId = $mailbox->id;
        $this->lastError = '';

        return $stream;
    }

    private function mailboxPath(BankMailbox $mailbox): string
    {
        $port = (int) ($mailbox->port ?: ($mailbox->encryption === 'ssl' ? 993 : 143));
        $folder = trim((string) ($mailbox->folder ?: 'INBOX')) ?: 'INBOX';

        $flags = '/imap';

        $flags .= match (strtolower((string) $mailbox->encryption)) {
            'ssl' => '/ssl',
            'tls' => '/tls',
            default => '/notls',
        };

        // Certificate validation is the default. An operator may disable it
        // for a legacy server, but never silently.
        if (! $mailbox->validate_cert) {
            $flags .= '/novalidate-cert';
        }

        return '{'.$mailbox->host.':'.$port.$flags.'}'.$folder;
    }
}
