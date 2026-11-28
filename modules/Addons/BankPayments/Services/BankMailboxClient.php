<?php

namespace Modules\Addons\BankPayments\Services;

use Carbon\CarbonInterface;
use Modules\Addons\BankPayments\Models\BankMailbox;

/**
 * Addon-local mailbox transport.
 *
 * It is intentionally separate from the ticket MailboxClientInterface: bank
 * mailboxes are configured on the addon's own table and are read by UID, not
 * by the ticket importer's UNSEEN rule.
 */
interface BankMailboxClient
{
    /**
     * Open the mailbox once and report whether it can be read.
     *
     * @return array{success: bool, message: string, uidvalidity: int|null}
     */
    public function test(BankMailbox $mailbox): array;

    /**
     * Fetch raw messages. A null $since means "continue after the mailbox's
     * last UID"; a non-null $since is used for the first sync and after a
     * UIDVALIDITY reset.
     *
     * @return array{uidvalidity: int|null, messages: array<int, array{uid: int, raw: string}>, error: string|null}
     */
    public function fetch(BankMailbox $mailbox, ?CarbonInterface $since, int $limit): array;

    public function markSeen(BankMailbox $mailbox, int $uid): void;

    public function disconnect(): void;
}
