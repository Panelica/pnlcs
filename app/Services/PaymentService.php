<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Events\InvoicePaid;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for recording payments against invoices.
 *
 * Every payment source (gateway webhooks, JS-SDK captures, admin manual
 * mark-paid, bank transfer approvals, credit applications) MUST go through
 * applyPayment() so that partial payments, overpayment-to-credit, AddFunds
 * crediting, affiliate commission and the InvoicePaid event behave
 * identically regardless of how the money arrived.
 */
class PaymentService
{
    /**
     * Invoice statuses that can still receive a regular payment.
     */
    private const PAYABLE_STATUSES = [
        'draft',
        'unpaid',
        'overdue',
        'payment_pending',
        'partially_paid',
        'collections',
    ];

    /**
     * Record a payment against an invoice.
     *
     * @param  Invoice      $invoice
     * @param  string       $gateway        Gateway key (e.g. 'stripe', 'banktransfer', 'manual', 'credit')
     * @param  string|null  $transactionId  Gateway transaction reference (used for idempotency)
     * @param  float|null   $amount         Paid amount; null = remaining balance of the invoice
     * @param  array        $options        ['fees' => float, 'description' => string]
     * @return array{success: bool, status: string, balance: float, duplicate?: bool}
     */
    public function applyPayment(Invoice $invoice, string $gateway, ?string $transactionId, ?float $amount = null, array $options = []): array
    {
        $result = DB::transaction(function () use ($invoice, $gateway, $transactionId, $amount, $options) {
            /** @var Invoice $invoice */
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            // Idempotency — the same gateway transaction must never be double-recorded.
            if ($transactionId !== null && $transactionId !== '') {
                $exists = Transaction::where('gateway', $gateway)
                    ->where('transaction_id', $transactionId)
                    ->exists();
                if ($exists) {
                    Log::info("PaymentService: duplicate transaction ignored", [
                        'invoice_id' => $invoice->id, 'gateway' => $gateway, 'transaction_id' => $transactionId,
                    ]);
                    return ['success' => true, 'status' => $invoice->status, 'balance' => $this->balance($invoice), 'duplicate' => true, 'paid_event' => false];
                }
            }

            $status = strtolower((string) $invoice->status);

            // Money arriving on a non-payable invoice (already paid, cancelled,
            // refunded) is never discarded — it becomes client credit.
            if (!in_array($status, self::PAYABLE_STATUSES, true)) {
                $amount = (float) ($amount ?? 0);
                if ($amount > 0) {
                    $this->recordTransaction($invoice, $gateway, $transactionId, $amount, $options);
                    $this->addClientCredit($invoice, $amount, "Payment received on {$status} invoice #{$invoice->invoice_num} — added as credit");
                }
                return ['success' => true, 'status' => $invoice->status, 'balance' => 0.0, 'paid_event' => false];
            }

            $amount = (float) ($amount ?? $this->balance($invoice));

            if ($amount > 0) {
                $this->recordTransaction($invoice, $gateway, $transactionId, $amount, $options);
            }

            $balance = $this->balance($invoice);

            if ($amount <= 0 && $balance > 0.009) {
                // Nothing was paid and the invoice is not covered — no state change.
                return ['success' => false, 'status' => $invoice->status, 'balance' => $balance, 'paid_event' => false];
            }

            if ($balance > 0.009) {
                // Partial payment — keep collecting.
                $invoice->update(['status' => InvoiceStatus::PartiallyPaid->value]);
                Log::info("PaymentService: partial payment on invoice #{$invoice->id}", [
                    'gateway' => $gateway, 'amount' => $amount, 'remaining' => $balance,
                ]);
                return ['success' => true, 'status' => InvoiceStatus::PartiallyPaid->value, 'balance' => $balance, 'paid_event' => false];
            }

            // Fully covered. Overpayment goes to client credit.
            $overpay = -$balance;
            if ($overpay > 0.009) {
                $this->addClientCredit($invoice, $overpay, "Overpayment on invoice #{$invoice->invoice_num} — added as credit");
            }

            $invoice->update([
                'status'    => InvoiceStatus::Paid->value,
                'date_paid' => now(),
            ]);

            // AddFunds invoices: the paid amount becomes client credit.
            $this->creditAddFundsItems($invoice, $gateway);

            // Affiliate commission (never blocks the payment).
            try {
                app(AffiliateService::class)->processCommission($invoice);
            } catch (\Throwable $e) {
                Log::warning('PaymentService: affiliate commission failed for invoice #' . $invoice->id . ': ' . $e->getMessage());
            }

            return ['success' => true, 'status' => InvoiceStatus::Paid->value, 'balance' => 0.0, 'paid_event' => true];
        });

        // Fire outside the DB transaction so listeners see committed state.
        if ($result['paid_event'] ?? false) {
            event(new InvoicePaid($invoice->fresh(), $transactionId));
        }
        unset($result['paid_event']);

        return $result;
    }

    /**
     * Remaining balance of an invoice: total minus net recorded payments.
     * (invoice.total is already net of applied account credit.)
     */
    public function balance(Invoice $invoice): float
    {
        $paid = (float) Transaction::where('invoice_id', $invoice->id)->sum(DB::raw('amount_in - amount_out'));

        return round((float) $invoice->total - $paid, 2);
    }

    private function recordTransaction(Invoice $invoice, string $gateway, ?string $transactionId, float $amount, array $options): Transaction
    {
        return Transaction::create([
            'client_id'      => $invoice->client_id,
            'invoice_id'     => $invoice->id,
            'gateway'        => $gateway,
            'date'           => now()->toDateString(),
            'description'    => $options['description'] ?? ('Payment for Invoice #' . ($invoice->invoice_num ?? $invoice->id)),
            'amount_in'      => $amount,
            'fees'           => (float) ($options['fees'] ?? 0),
            'amount_out'     => 0,
            'rate'           => 1,
            'transaction_id' => $transactionId,
        ]);
    }

    private function addClientCredit(Invoice $invoice, float $amount, string $description): void
    {
        $client = $invoice->client;
        if (!$client) {
            return;
        }

        $client->increment('credit', $amount);
        Credit::create([
            'client_id'   => $client->id,
            'admin_id'    => null,
            'date'        => now()->toDateString(),
            'description' => $description,
            'amount'      => $amount,
        ]);
    }

    private function creditAddFundsItems(Invoice $invoice, string $gateway): void
    {
        // Credit-funded AddFunds would move credit in a circle; skip.
        if ($gateway === 'credit') {
            return;
        }

        $addFundsTotal = (float) $invoice->items()->where('type', 'AddFunds')->sum('amount');
        if ($addFundsTotal > 0) {
            $this->addClientCredit($invoice, $addFundsTotal, "Funds added via invoice #{$invoice->invoice_num}");
        }
    }
}
