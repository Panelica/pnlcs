<?php

namespace Modules\Addons\BankPayments\Services;

use App\Models\Admin;
use App\Models\Invoice;
use App\Services\AddonManager;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Addons\BankPayments\Models\BankImport;
use Modules\Addons\BankPayments\Models\BankMatch;
use Modules\Addons\BankPayments\Models\BankTransaction;
use Throwable;

/**
 * The only place in BankPayments that turns a match into money.
 *
 * It re-checks the invoice and the match immediately before booking, calls the
 * native PaymentService, and writes an addon import row. It never writes an
 * invoice or a core transaction itself.
 */
class BankPaymentImporter
{
    public function __construct(
        private readonly InvoiceMatcher $matcher,
        private readonly PaymentService $payments,
        private readonly AddonManager $addons,
    ) {}

    public static function paymentReference(BankTransaction $transaction, Invoice $invoice): string
    {
        // Deterministic across retries and immutable for the bank transaction
        // plus invoice pair. One transfer covering several invoices therefore
        // gets one reference per invoice.
        return 'BANK:'.$transaction->id.':'.$invoice->id;
    }

    /**
     * @return array{success: bool, status: string, message: string, import_id?: int, duplicate?: bool}
     */
    public function import(BankMatch $match, ?Admin $admin = null, ?float $amount = null, string $source = 'manual'): array
    {
        if (! $this->addons->isActive('bank_payments')) {
            return $this->result(false, 'blocked', __('bank-payments::imports.addon_inactive'));
        }

        if (BankPaymentsSettings::mode() === 'test') {
            return $this->result(false, 'blocked', __('bank-payments::imports.test_mode'));
        }

        $lock = Cache::lock('bank-payments:import:'.$match->id, 30);

        if (! $lock->get()) {
            return $this->result(false, 'blocked', __('bank-payments::imports.busy'));
        }

        try {
            $match->refresh()->load(['transaction', 'invoice.client']);

            $transaction = $match->transaction;
            $invoice = $match->invoice;

            if (! $transaction || ! $invoice) {
                return $this->recordFailure($match, null, null, $amount, $source, 'blocked', __('bank-payments::imports.missing_data'));
            }

            $existing = BankImport::where('bank_match_id', $match->id)->first();

            if ($existing?->status === 'imported') {
                return $this->result(true, 'imported', __('bank-payments::imports.already_imported'), $existing->id, true);
            }

            if (in_array($match->status, ['imported', 'rejected'], true)) {
                return $this->result(false, 'blocked', __('bank-payments::imports.already_decided'));
            }

            if (strtoupper((string) $transaction->currency) !== 'PLN') {
                return $this->recordFailure($match, $transaction, $invoice, $amount, $source, 'blocked', __('bank-payments::imports.currency_not_supported'));
            }

            $invoiceCurrency = strtoupper((string) ($invoice->source_currency ?? ''));

            if ($invoiceCurrency !== '' && $invoiceCurrency !== 'PLN') {
                return $this->recordFailure($match, $transaction, $invoice, $amount, $source, 'blocked', __('bank-payments::imports.currency_not_supported'));
            }

            $candidate = $this->matcher->scoreInvoice($transaction, $invoice);

            if ($candidate['score'] < 60 || $candidate['invoice_balance'] <= 0.009) {
                return $this->recordFailure($match, $transaction, $invoice, $amount, $source, 'blocked', __('bank-payments::imports.match_changed'));
            }

            $amount = $amount === null
                ? round((float) $match->amount, 2)
                : round($amount, 2);

            if ($amount <= 0) {
                return $this->recordFailure($match, $transaction, $invoice, $amount, $source, 'blocked', __('bank-payments::imports.amount_invalid'));
            }

            if ($source === 'auto') {
                if (BankPaymentsSettings::mode() !== 'auto'
                    || $match->rank !== 1
                    || $transaction->matching_status !== 'auto_ready'
                    || $candidate['score'] < 90
                    || ! $candidate['number_match']
                    || ! $candidate['amount_match']
                    || ! $candidate['client_match']
                    || abs($amount - $candidate['invoice_balance']) > 0.01) {
                    return $this->recordFailure($match, $transaction, $invoice, $amount, $source, 'blocked', __('bank-payments::imports.not_unambiguous'));
                }
            }

            $reference = self::paymentReference($transaction, $invoice);

            $response = $this->payments->applyPayment(
                $invoice,
                'banktransfer',
                $reference,
                $amount,
                [
                    'description' => 'Bank transfer '.$transaction->id.($transaction->sender_name ? ' — '.$transaction->sender_name : ''),
                    'require_payable' => true,
                    'expected_balance' => $candidate['invoice_balance'],
                ]
            );

            if ($response['not_payable'] ?? false) {
                return $this->recordFailure($match, $transaction, $invoice, $amount, $source, 'blocked', __('bank-payments::imports.already_settled'), $response);
            }

            if ($response['balance_changed'] ?? false) {
                return $this->recordFailure($match, $transaction, $invoice, $amount, $source, 'blocked', __('bank-payments::imports.balance_changed'), $response);
            }

            if (! ($response['success'] ?? false)) {
                return $this->recordFailure($match, $transaction, $invoice, $amount, $source, 'failed', __('bank-payments::imports.failed'), $response);
            }

            $import = BankImport::updateOrCreate(
                ['bank_match_id' => $match->id],
                [
                    'bank_transaction_id' => $transaction->id,
                    'invoice_id' => $invoice->id,
                    'admin_id' => $admin?->id,
                    'source' => $source,
                    'gateway' => 'banktransfer',
                    'payment_reference' => $reference,
                    'amount' => $amount,
                    'status' => 'imported',
                    'response' => $response,
                    'error' => null,
                    'imported_at' => now(),
                ]
            );

            $match->update([
                'status' => 'imported',
                'reviewed_by' => $admin?->id,
                'reviewed_at' => now(),
            ]);

            $remaining = $transaction->matches()
                ->where('status', 'proposed')
                ->exists();

            $transaction->update([
                'matching_status' => $remaining ? 'review' : 'imported',
                'matching_reason' => $remaining
                    ? __('bank-payments::imports.remaining_candidates')
                    : __('bank-payments::imports.imported'),
                'matched_at' => now(),
            ]);

            return $this->result(true, 'imported', __('bank-payments::imports.imported'), $import->id, (bool) ($response['duplicate'] ?? false));
        } catch (Throwable $e) {
            Log::error('BankPayments: import failed', [
                'match_id' => $match->id,
                'error' => $e->getMessage(),
            ]);

            return $this->recordFailure($match, $match->transaction, $match->invoice, $amount, $source, 'failed', $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    private function recordFailure(
        BankMatch $match,
        ?BankTransaction $transaction,
        ?Invoice $invoice,
        ?float $amount,
        string $source,
        string $status,
        string $message,
        ?array $response = null,
    ): array {
        $import = BankImport::updateOrCreate(
            ['bank_match_id' => $match->id],
            [
                'bank_transaction_id' => $transaction?->id ?? $match->bank_transaction_id,
                'invoice_id' => $invoice?->id ?? $match->invoice_id,
                'admin_id' => null,
                'source' => $source,
                'gateway' => 'banktransfer',
                'payment_reference' => $transaction && $invoice ? self::paymentReference($transaction, $invoice) : 'BANK:'.$match->id,
                'amount' => round((float) ($amount ?? $match->amount), 2),
                'status' => $status,
                'response' => $response,
                'error' => $message,
                'imported_at' => null,
            ]
        );

        if ($transaction) {
            $transaction->update([
                'matching_status' => $status === 'blocked' ? 'review' : 'error',
                'matching_reason' => $message,
                'matched_at' => now(),
            ]);
        }

        return $this->result(false, $status, $message, $import->id);
    }

    private function result(bool $success, string $status, string $message, ?int $importId = null, bool $duplicate = false): array
    {
        return [
            'success' => $success,
            'status' => $status,
            'message' => $message,
            'import_id' => $importId,
            'duplicate' => $duplicate,
        ];
    }
}
