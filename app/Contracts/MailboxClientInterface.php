<?php

namespace App\Contracts;

use App\Models\TicketDepartment;

interface MailboxClientInterface
{
    /**
     * Fetch unprocessed raw messages from the department mailbox.
     *
     * @return array<int, array{uid: string, raw: string}>
     */
    public function fetchMessages(TicketDepartment $department, int $limit = 25): array;

    /**
     * Mark a message as processed (seen) or delete it, per department config.
     */
    public function markProcessed(TicketDepartment $department, string $uid): void;

    public function disconnect(): void;
}
