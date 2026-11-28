<?php

namespace Modules\Addons\BankPayments\Services;

use App\Models\Currency;
use App\Models\Invoice;
use Illuminate\Support\Str;
use Modules\Addons\BankPayments\Models\BankMatch;
use Modules\Addons\BankPayments\Models\BankTransaction;

/**
 * Proposes open invoices for an incoming PLN transfer.
 *
 * Stage 3 only records candidates. It never changes an invoice, writes a core
 * transaction, or calls PaymentService::applyPayment(). Stage 4 re-checks the
 * same facts under a lock immediately before booking.
 */
class InvoiceMatcher
{
    private const CANDIDATE_STATUSES = [
        'unpaid',
        'overdue',
        'partially_paid',
        'payment_pending',
        'collections',
    ];

    private const MIN_SCORE = 60;

    private const AUTO_SCORE = 90;

    private const MAX_CANDIDATES = 5;

    public function match(BankTransaction $transaction): array
    {
        $transaction->loadMissing('message.mailbox');
        $transaction->matches()->delete();

        $amount = round((float) $transaction->amount, 2);
        $currency = strtoupper(trim((string) $transaction->currency));

        if ($currency !== 'PLN') {
            return $this->finish($transaction, 'manual_currency', 0, __('bank-payments::matches.currency_not_supported'));
        }

        $shopCurrency = strtoupper((string) (Currency::getDefault()?->code ?? ''));

        if ($shopCurrency !== '' && $shopCurrency !== 'PLN') {
            return $this->finish($transaction, 'manual_currency', 0, __('bank-payments::matches.currency_not_supported'));
        }

        if ($amount <= 0) {
            return $this->finish($transaction, 'unmatched', 0, __('bank-payments::matches.no_amount'));
        }

        $candidates = [];

        Invoice::query()
            ->with('client')
            ->whereIn('status', self::CANDIDATE_STATUSES)
            ->where(function ($query): void {
                $query->whereNull('source_currency')
                    ->orWhere('source_currency', '')
                    ->orWhere('source_currency', 'PLN');
            })
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($transaction, &$candidates): void {
                $candidate = $this->scoreInvoice($transaction, $invoice);

                if ($candidate['score'] < self::MIN_SCORE) {
                    return;
                }

                $candidates[] = $candidate;
            });

        usort($candidates, function (array $a, array $b): int {
            return [$b['score'], $b['amount_match'], $b['number_match'], $a['invoice']->id]
                <=> [$a['score'], $a['amount_match'], $a['number_match'], $b['invoice']->id];
        });

        $candidates = array_slice($candidates, 0, self::MAX_CANDIDATES);

        if ($candidates === []) {
            return $this->finish($transaction, 'unmatched', 0, __('bank-payments::matches.no_candidate'));
        }

        foreach ($candidates as $index => $candidate) {
            /** @var Invoice $invoice */
            $invoice = $candidate['invoice'];

            BankMatch::updateOrCreate(
                [
                    'bank_transaction_id' => $transaction->id,
                    'invoice_id' => $invoice->id,
                ],
                [
                    'rank' => $index + 1,
                    'score' => $candidate['score'],
                    'amount' => round(min($amount, $candidate['invoice_balance']), 2),
                    'criteria' => [
                        'number_match' => $candidate['number_match'],
                        'amount_match' => $candidate['amount_match'],
                        'client_match' => $candidate['client_match'],
                        'transaction_amount' => $amount,
                        'invoice_balance' => $candidate['invoice_balance'],
                        'invoice_number' => $invoice->invoice_num,
                        'sender_name' => $transaction->sender_name,
                        'client_name' => $candidate['client_name'],
                        'currency' => $currency,
                    ],
                    'status' => 'proposed',
                    'reason' => null,
                ]
            );
        }

        $best = $candidates[0];
        $topScore = (int) $best['score'];

        if (count($candidates) > 1) {
            return $this->finish($transaction, 'ambiguous', $topScore, __('bank-payments::matches.multiple_candidates'));
        }

        if ($topScore >= self::AUTO_SCORE
            && $best['number_match']
            && $best['amount_match']
            && $best['client_match']) {
            return $this->finish($transaction, 'auto_ready', $topScore, __('bank-payments::matches.auto_ready'));
        }

        return $this->finish($transaction, 'review', $topScore, __('bank-payments::matches.review'));
    }

    /**
     * Recompute the facts for one invoice. Used by the matcher and again by the
     * importer immediately before booking, so a stale queue entry cannot be
     * booked after the invoice has changed.
     *
     * @return array<string, mixed>
     */
    public function scoreInvoice(BankTransaction $transaction, Invoice $invoice): array
    {
        $amount = round((float) $transaction->amount, 2);
        $sender = $this->normalizeName((string) $transaction->sender_name);
        $referenceTokens = $this->referenceTokens($transaction->reference);
        $balance = round((float) $invoice->amountDue(), 2);

        $invoiceNumber = $this->normalizeNumber((string) $invoice->invoice_num);
        $numberMatch = $invoiceNumber !== '' && in_array($invoiceNumber, $referenceTokens, true);
        $amountMatch = $balance > 0.009 && abs($balance - $amount) <= 0.01;
        $clientMatch = $this->clientMatches($sender, $invoice->client);
        $score = ($numberMatch ? 40 : 0) + ($amountMatch ? 30 : 0) + ($clientMatch ? 20 : 0);

        return [
            'invoice' => $invoice,
            'score' => $score,
            'number_match' => $numberMatch,
            'amount_match' => $amountMatch,
            'client_match' => $clientMatch,
            'invoice_balance' => $balance,
            'client_name' => $this->clientName($invoice),
        ];
    }

    private function finish(BankTransaction $transaction, string $status, int $score, string $reason): array
    {
        $transaction->update([
            'matching_status' => $status,
            'matching_score' => $score,
            'matching_reason' => $reason,
            'matched_at' => now(),
        ]);

        return [
            'status' => $status,
            'score' => $score,
            'reason' => $reason,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function referenceTokens(?string $reference): array
    {
        $reference = trim((string) $reference);

        if ($reference === '') {
            return [];
        }

        preg_match_all('/[A-Z0-9][A-Z0-9\/._-]{2,}/iu', $reference, $matches);

        $tokens = [];
        foreach ($matches[0] ?? [] as $token) {
            $normalized = $this->normalizeNumber($token);

            if ($normalized !== '' && preg_match('/\d/', $normalized)) {
                $tokens[] = $normalized;
            }
        }

        return array_values(array_unique($tokens));
    }

    private function normalizeNumber(string $value): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii(trim($value))));
    }

    private function normalizeName(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = (string) preg_replace('/[^a-z0-9]+/', ' ', $value);

        // Legal forms are not part of the trading name. Order matters: the
        // longest phrase goes first so "sp. z o.o. sp.k." leaves no fragments.
        foreach ([
            'spolka z ograniczona odpowiedzialnoscia spolka komandytowa',
            'spolka z ograniczona odpowiedzialnoscia',
            'spolka akcyjna',
            'sp z o o',
            'sp z oo',
            'spolka komandytowa',
            'spolka jawna',
            'spolka cywilna',
            'limited liability company',
            'limited',
            'ltd',
            'llc',
            'inc',
            'gmbh',
            'company',
        ] as $legalForm) {
            $value = str_replace($legalForm, ' ', $value);
        }

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function clientMatches(string $sender, mixed $client): bool
    {
        if ($sender === '' || ! $client) {
            return false;
        }

        foreach ([$this->clientName($client), trim((string) $client->first_name.' '.(string) $client->last_name)] as $name) {
            $clientName = $this->normalizeName((string) $name);

            if ($clientName === '') {
                continue;
            }

            if ($clientName === $sender) {
                return true;
            }

            if (mb_strlen($clientName) >= 5
                && mb_strlen($sender) >= 5
                && (str_contains($sender, $clientName) || str_contains($clientName, $sender))) {
                return true;
            }
        }

        return false;
    }

    private function clientName(mixed $client): string
    {
        if (! $client) {
            return '';
        }

        return trim((string) ($client->company_name ?: $client->full_name ?: ''));
    }
}
