<?php

namespace Modules\Addons\BankPayments\Services;

/**
 * Turns a raw bank notification into zero or more transaction rows.
 *
 * $message is the output of App\Services\Mail\RawMessageParser. Keeping the
 * parser behind this interface lets a real Nest Bank format replace the
 * starter implementation without touching the sync pipeline.
 */
interface BankMessageParser
{
    /**
     * Whether this message is a bank-transfer notification the parser should
     * try to read. Other bank mail (for example a BLIK payment notice) is
     * stored as ignored rather than as a parser failure.
     *
     * @param  array<string, mixed>  $message
     */
    public function isRelevant(string $raw, array $message): bool;

    /**
     * @param  array<string, mixed>  $message
     * @return array<int, array{
     *     operation_type?: string|null,
     *     transaction_date?: string|null,
     *     amount: float,
     *     currency?: string,
     *     sender_name?: string|null,
     *     sender_account?: string|null,
     *     recipient_account?: string|null,
     *     reference?: string|null,
     *     raw_payload?: array<string, mixed>
     * }>
     */
    public function parse(string $raw, array $message): array;
}
