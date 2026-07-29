<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Events\InvoiceCreated;
use App\Events\InvoicePaid;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRule;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Create a new invoice for a client with line items.
     * Calculates totals with tax automatically.
     */
    public function createInvoice(Client $client, array $items, array $options = []): Invoice
    {
        $invoice = DB::transaction(function () use ($client, $items, $options) {
            $invoice = Invoice::create([
                'client_id' => $client->id,
                'invoice_num' => $options['invoice_num'] ?? $this->generateInvoiceNumber(),
                'date' => $options['date'] ?? now()->toDateString(),
                'due_date' => $options['due_date'] ?? now()->addDays(14)->toDateString(),
                'status' => $options['status'] ?? InvoiceStatus::Unpaid->value,
                'payment_method' => $options['payment_method'] ?? null,
                'notes' => $options['notes'] ?? null,
                'subtotal' => 0,
                'credit' => 0,
                'tax' => 0,
                'tax2' => 0,
                'total' => 0,
                'tax_rate' => 0,
                'tax_rate2' => 0,
            ]);

            foreach ($items as $itemData) {
                $this->addLineItem($invoice, $itemData);
            }

            return $this->recalculateTotals($invoice->fresh());
        });

        event(new InvoiceCreated($invoice));

        $this->applyAvailableCredit($invoice);

        return $invoice->fresh();
    }

    /**
     * Add a line item to an invoice and recalculate totals.
     */
    public function addLineItem(Invoice $invoice, array $itemData): InvoiceItem
    {
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'type' => $itemData['type'] ?? 'Other',
            'rel_id' => $itemData['rel_id'] ?? 0,
            'description' => $itemData['description'] ?? '',
            'amount' => $itemData['amount'] ?? 0,
            'taxed' => $itemData['taxed'] ?? true,
            'due_date' => $itemData['due_date'] ?? null,
        ]);

        $this->recalculateTotals($invoice->fresh());

        return $item;
    }

    /**
     * Recalculate subtotal, tax, and total from all line items.
     */
    public function recalculateTotals(Invoice $invoice): Invoice
    {
        $invoice->loadMissing('items', 'client');

        $subtotal = $invoice->items->sum('amount');

        $taxableAmount = $invoice->items
            ->where('taxed', true)
            ->sum('amount');

        // Use stored tax rates if available, otherwise calculate from rules
        $taxRate = (float) $invoice->tax_rate;
        $taxRate2 = (float) $invoice->tax_rate2;

        if ($taxRate === 0.0 && $taxRate2 === 0.0 && ! $invoice->client->tax_exempt) {
            $taxData = $this->calculateTax($taxableAmount, $invoice->client_id);
            $taxRate = $taxData['tax_rate'];
            $taxRate2 = $taxData['tax_rate2'];
        }

        $taxAmount = $taxRate > 0 ? round($taxableAmount * ($taxRate / 100), 2) : 0;
        $taxAmount2 = $taxRate2 > 0 ? round($taxableAmount * ($taxRate2 / 100), 2) : 0;

        $credit = (float) $invoice->credit;
        $total = max(0, $subtotal + $taxAmount + $taxAmount2 - $credit);

        $invoice->update([
            'subtotal' => $subtotal,
            'tax' => $taxAmount,
            'tax2' => $taxAmount2,
            'tax_rate' => $taxRate,
            'tax_rate2' => $taxRate2,
            'total' => $total,
        ]);

        return $invoice->fresh();
    }

    /**
     * Mark invoice as paid.
     * Creates a transaction record and updates client paid date.
     */
    public function markPaid(Invoice $invoice, ?string $transactionId = null, string $gateway = 'manual'): Invoice
    {
        if (strtolower((string) $invoice->status) === InvoiceStatus::Paid->value) {
            return $invoice;
        }

        // Settle the remaining balance through the single payment entry point
        // (partial payments, overpay-to-credit, AddFunds, affiliate, InvoicePaid).
        app(PaymentService::class)->applyPayment($invoice, $gateway, $transactionId, null);

        return $invoice->fresh();
    }

    /**
     * Spend whatever the customer has already paid in.
     *
     * Credit arrives from the Add Funds page, from overpayments and from money
     * landing on an already-settled invoice. Nothing called applyCredit(), so
     * the balance sat there while the customer was invoiced in full.
     */
    private function applyAvailableCredit(Invoice $invoice): void
    {
        $client = $invoice->client;

        if (! $client || (float) $client->credit <= 0) {
            return;
        }

        // An Add Funds invoice must not be settled out of the balance it is
        // meant to top up, or the money goes round in a circle.
        if ($invoice->items()->where('type', 'AddFunds')->exists()) {
            return;
        }

        $this->applyCredit($invoice, (float) $client->credit);
    }

    /**
     * Apply client credit to an invoice.
     * Reduces the balance by the given amount (capped at invoice total).
     */
    public function applyCredit(Invoice $invoice, float $amount): Invoice
    {
        $status = strtolower((string) $invoice->status);
        if (in_array($status, [InvoiceStatus::Paid->value, InvoiceStatus::Cancelled->value, InvoiceStatus::Refunded->value], true)) {
            return $invoice;
        }

        $client = $invoice->client;
        $availableCredit = (float) $client->credit;

        // Cap at available credit and the invoice's remaining balance
        // (balance accounts for partial payments already recorded).
        $balance = app(PaymentService::class)->balance($invoice);
        $amount = min($amount, $availableCredit, $balance);

        if ($amount <= 0) {
            return $invoice;
        }

        $invoice = DB::transaction(function () use ($invoice, $amount, $client) {
            $newCredit = (float) $invoice->credit + $amount;
            $newTotal = max(0, (float) $invoice->total - $amount);

            $invoice->update([
                'credit' => $newCredit,
                'total' => $newTotal,
            ]);

            // Deduct from client credit balance
            $client->decrement('credit', $amount);

            return $invoice->fresh();
        });

        // Fully covered? Settle through the payment chain (fires InvoicePaid).
        if (app(PaymentService::class)->balance($invoice) <= 0.009) {
            $this->markPaid($invoice, null, 'credit');
            $invoice = $invoice->fresh();
        }

        return $invoice;
    }

    /**
     * Generate a unique sequential invoice number with prefix.
     */
    public function generateInvoiceNumber(): string
    {
        $prefix = config('billing.invoice_prefix', 'INV-');
        $latest = Invoice::where('invoice_num', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_num');

        if ($latest) {
            $numeric = (int) ltrim(str_replace($prefix, '', $latest), '0');
            $next = $numeric + 1;
        } else {
            $next = 1;
        }

        return $prefix.str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Cancel an invoice (only if not already paid).
     */
    public function cancelInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Paid->value) {
            return $invoice;
        }

        $invoice->update(['status' => InvoiceStatus::Cancelled->value]);

        return $invoice->fresh();
    }

    /**
     * Calculate applicable tax for an amount based on client location and tax rules.
     * Returns ['tax' => float, 'tax_rate' => float].
     */
    public function calculateTax(float $amount, ?int $clientId = null): array
    {
        if ($clientId === null) {
            return ['tax' => 0.0, 'tax_rate' => 0.0];
        }

        $client = Client::find($clientId);

        if (! $client || $client->tax_exempt) {
            return ['tax' => 0.0, 'tax_rate' => 0.0];
        }

        $rate = $this->rateFor($client, 1);
        $rate2 = $this->rateFor($client, 2);

        return [
            'tax' => round($amount * ($rate / 100), 2),
            'tax_rate' => $rate,
            'tax2' => round($amount * ($rate2 / 100), 2),
            'tax_rate2' => $rate2,
        ];
    }

    /**
     * The rate that applies to a customer at one tax level.
     *
     * Matched on country and state, most specific first. Level 2 is the second
     * tax an operator can configure — a provincial one on top of a federal
     * one — and until now nothing asked for it.
     */
    private function rateFor(Client $client, int $level): float
    {
        $rule = TaxRule::where('country', $client->country)
            ->where(function ($q) use ($client) {
                $q->where('state', $client->state)
                    ->orWhere('state', '')
                    ->orWhereNull('state');
            })
            ->orderByRaw('CASE WHEN state = ? THEN 0 ELSE 1 END', [$client->state ?? ''])
            ->where('level', $level)
            ->first();

        return $rule ? (float) $rule->tax_rate : 0.0;
    }
}
