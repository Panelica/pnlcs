<?php

namespace Modules\Addons\BankPayments\Services;

use App\Services\Mail\RawMessageParser;
use Illuminate\Support\Facades\Log;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Models\BankMessage;
use Modules\Addons\BankPayments\Models\BankSyncLog;
use Modules\Addons\BankPayments\Models\BankTransaction;
use Throwable;

/**
 * Reads one mailbox and stores its messages and bank transactions.
 *
 * This is the read-and-match pipeline: it records messages, parses incoming
 * transfers and asks InvoiceMatcher for proposals. It never calls
 * PaymentService::applyPayment(), so test mode cannot book a payment.
 */
class BankSyncService
{
    public function __construct(
        private readonly BankMailboxClient $client,
        private readonly BankMessageParserRegistry $parsers,
        private readonly RawMessageParser $rawParser,
        private readonly InvoiceMatcher $matcher,
        private readonly BankPaymentImporter $importer,
    ) {}

    /**
     * @return array{status: string, fetched: int, parsed: int, new_transactions: int, duplicates: int, errors: int, message: string}
     */
    public function sync(BankMailbox $mailbox, ?int $days = null, ?int $limit = null): array
    {
        $settings = BankPaymentsSettings::resolve();

        $days = max(1, min(365, $days ?? $settings['initial_sync_days']));
        $limit = max(1, min(500, $limit ?? $settings['sync_limit']));

        $log = BankSyncLog::create([
            'bank_mailbox_id' => $mailbox->id,
            'started_at' => now(),
            'status' => 'running',
        ]);

        $fetched = 0;
        $parsed = 0;
        $newTransactions = 0;
        $duplicates = 0;
        $errors = 0;

        try {
            $probe = $this->client->test($mailbox);

            if (! $probe['success']) {
                throw new \RuntimeException($probe['message']);
            }

            $currentUidvalidity = $probe['uidvalidity'];
            $uidvalidityReset = $mailbox->last_uidvalidity !== null
                && $currentUidvalidity !== null
                && $mailbox->last_uidvalidity !== $currentUidvalidity;

            if ($uidvalidityReset) {
                $mailbox->last_uid = null;
                $mailbox->last_uidvalidity = null;
            }

            $since = $mailbox->last_uid === null
                ? now()->subDays($days)->startOfDay()
                : null;

            $batch = $this->client->fetch($mailbox, $since, $limit);

            if ($batch['error']) {
                throw new \RuntimeException($batch['error']);
            }

            $uidvalidity = $batch['uidvalidity'] ?? $currentUidvalidity ?? 0;
            $lastUid = $mailbox->last_uid;
            $parser = $this->parsers->for($mailbox->parser ?: $mailbox->bank);

            foreach ($batch['messages'] as $item) {
                $fetched++;
                $lastUid = max((int) ($lastUid ?? 0), $item['uid']);

                $contentHash = hash('sha256', $item['raw']);

                $duplicate = BankMessage::query()
                    ->where('bank_mailbox_id', $mailbox->id)
                    ->where(function ($query) use ($uidvalidity, $item, $contentHash): void {
                        $query->where(function ($q) use ($uidvalidity, $item): void {
                            $q->where('uidvalidity', $uidvalidity)->where('uid', $item['uid']);
                        })->orWhere('content_hash', $contentHash);
                    })
                    ->exists();

                if ($duplicate) {
                    $duplicates++;

                    continue;
                }

                $raw = $this->rawParser->parse($item['raw']);
                $message = BankMessage::create([
                    'bank_mailbox_id' => $mailbox->id,
                    'uid' => $item['uid'],
                    'uidvalidity' => $uidvalidity,
                    'message_id' => $raw['message_id'] ?? null,
                    'from_email' => $raw['from_email'] ?? null,
                    'from_name' => $raw['from_name'] ?? null,
                    'subject' => $raw['subject'] ?? null,
                    'received_at' => now(),
                    'body_text' => $raw['body_text'] ?? null,
                    'content_hash' => $contentHash,
                    'status' => 'received',
                ]);

                $transactions = [];
                $parseError = null;

                if (! $parser->isRelevant($item['raw'], $raw)) {
                    $message->update(['status' => 'ignored', 'error' => null]);

                    if ($settings['mark_seen']) {
                        $this->client->markSeen($mailbox, $item['uid']);
                    }

                    continue;
                }

                try {
                    $transactions = $parser->parse($item['raw'], $raw);
                } catch (Throwable $e) {
                    $parseError = $e->getMessage();
                    Log::warning('BankPayments: parser failed', [
                        'mailbox_id' => $mailbox->id,
                        'uid' => $item['uid'],
                        'error' => $parseError,
                    ]);
                }

                $message->update([
                    'status' => $transactions === [] ? 'unparsed' : 'parsed',
                    'error' => $parseError,
                ]);

                if ($transactions !== []) {
                    $parsed++;
                } else {
                    $errors++;
                }

                foreach ($transactions as $data) {
                    $fingerprint = $this->fingerprint($data);

                    $transaction = BankTransaction::firstOrCreate(
                        [
                            'bank_message_id' => $message->id,
                            'fingerprint' => $fingerprint,
                        ],
                        [
                            'bank' => $mailbox->bank,
                            'operation_type' => $data['operation_type'] ?? null,
                            'transaction_date' => $data['transaction_date'] ?? null,
                            'amount' => $data['amount'],
                            'currency' => strtoupper((string) ($data['currency'] ?? 'PLN')),
                            'sender_name' => $data['sender_name'] ?? null,
                            'sender_account' => $data['sender_account'] ?? null,
                            'recipient_account' => $data['recipient_account'] ?? null,
                            'reference' => $data['reference'] ?? null,
                            'raw_payload' => $data['raw_payload'] ?? null,
                        ]
                    );

                    if ($transaction->wasRecentlyCreated) {
                        $newTransactions++;

                        try {
                            $matchResult = $this->matcher->match($transaction);

                            if ($settings['mode'] === 'auto' && ($matchResult['status'] ?? null) === 'auto_ready') {
                                $match = $transaction->matches()->orderBy('rank')->first();

                                if ($match) {
                                    $importResult = $this->importer->import($match, null, null, 'auto');

                                    if (! ($importResult['success'] ?? false)) {
                                        Log::info('BankPayments: automatic import not completed', [
                                            'transaction_id' => $transaction->id,
                                            'status' => $importResult['status'] ?? null,
                                            'message' => $importResult['message'] ?? null,
                                        ]);
                                    }
                                }
                            }
                        } catch (Throwable $e) {
                            $transaction->update([
                                'matching_status' => 'error',
                                'matching_reason' => $e->getMessage(),
                                'matched_at' => now(),
                            ]);

                            Log::warning('BankPayments: invoice matching failed', [
                                'transaction_id' => $transaction->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                if ($settings['mark_seen']) {
                    $this->client->markSeen($mailbox, $item['uid']);
                }
            }

            $mailbox->update([
                'last_uid' => $lastUid,
                'last_uidvalidity' => $uidvalidity ?: $mailbox->last_uidvalidity,
                'last_sync_at' => now(),
                'last_error' => null,
            ]);

            $message = __('bank-payments::messages.sync_ok');

            $log->update([
                'finished_at' => now(),
                'status' => $errors > 0 ? 'partial' : 'ok',
                'fetched' => $fetched,
                'parsed' => $parsed,
                'new_transactions' => $newTransactions,
                'duplicates' => $duplicates,
                'errors' => $errors,
                'message' => $message,
            ]);

            return $this->result($errors > 0 ? 'partial' : 'ok', $fetched, $parsed, $newTransactions, $duplicates, $errors, $message);
        } catch (Throwable $e) {
            Log::error('BankPayments: mailbox sync failed', [
                'mailbox_id' => $mailbox->id,
                'error' => $e->getMessage(),
            ]);

            $mailbox->update(['last_error' => $e->getMessage(), 'last_sync_at' => now()]);

            $log->update([
                'finished_at' => now(),
                'status' => 'error',
                'fetched' => $fetched,
                'parsed' => $parsed,
                'new_transactions' => $newTransactions,
                'duplicates' => $duplicates,
                'errors' => $errors + 1,
                'message' => $e->getMessage(),
            ]);

            return $this->result('error', $fetched, $parsed, $newTransactions, $duplicates, $errors + 1, $e->getMessage());
        } finally {
            $this->client->disconnect();
        }
    }

    private function fingerprint(array $data): string
    {
        return hash('sha256', implode('|', [
            (string) ($data['transaction_date'] ?? ''),
            number_format((float) $data['amount'], 2, '.', ''),
            strtoupper((string) ($data['currency'] ?? 'PLN')),
            (string) ($data['sender_account'] ?? ''),
            (string) ($data['reference'] ?? ''),
        ]));
    }

    private function result(
        string $status,
        int $fetched,
        int $parsed,
        int $newTransactions,
        int $duplicates,
        int $errors,
        string $message,
    ): array {
        return [
            'status' => $status,
            'fetched' => $fetched,
            'parsed' => $parsed,
            'new_transactions' => $newTransactions,
            'duplicates' => $duplicates,
            'errors' => $errors,
            'message' => $message,
        ];
    }
}
