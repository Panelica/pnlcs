<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRule;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Events\InvoiceCreated;
use App\Events\InvoicePaid;

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
                'client_id'      => $client->id,
                'invoice_num'    => $options['invoice_num'] ?? $this->generateInvoiceNumber(),
                'date'           => $options['date'] ?? now()->toDateString(),
                'due_date'       => $options['due_date'] ?? now()->addDays(14)->toDateString(),
                'status'         => $options['status'] ?? 'Unpaid',
                'payment_method' => $options['payment_method'] ?? null,
                'notes'          => $options['notes'] ?? null,
                'subtotal'       => 0,
                'credit'         => 0,
                'tax'            => 0,
                'tax2'           => 0,
                'total'          => 0,
                'tax_rate'       => 0,
                'tax_rate2'      => 0,
            ]);

            foreach ($items as $itemData) {
                $this->addLineItem($invoice, $itemData);
            }

            return $this->recalculateTotals($invoice->fresh());
        });

        event(new InvoiceCreated($invoice));

        return $invoice;
    }

    /**
     * Add a line item to an invoice and recalculate totals.
     */
    public function addLineItem(Invoice $invoice, array $itemData): InvoiceItem
    {
        $item = InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'client_id'   => $invoice->client_id,
            'type'        => $itemData['type'] ?? 'Other',
            'rel_id'      => $itemData['rel_id'] ?? 0,
            'description' => $itemData['description'] ?? '',
            'amount'      => $itemData['amount'] ?? 0,
            'taxed'       => $itemData['taxed'] ?? true,
            'due_date'    => $itemData['due_date'] ?? null,
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
        $taxRate  = (float) $invoice->tax_rate;
        $taxRate2 = (float) $invoice->tax_rate2;

        if ($taxRate === 0.0 && !$invoice->client->tax_exempt) {
            $taxData = $this->calculateTax($taxableAmount, $invoice->client_id);
            $taxRate = $taxData['tax_rate'];
        }

        $taxAmount  = $taxRate  > 0 ? round($taxableAmount * ($taxRate  / 100), 2) : 0;
        $taxAmount2 = $taxRate2 > 0 ? round($taxableAmount * ($taxRate2 / 100), 2) : 0;

        $credit = (float) $invoice->credit;
        $total  = max(0, $subtotal + $taxAmount + $taxAmount2 - $credit);

        $invoice->update([
            'subtotal' => $subtotal,
            'tax'      => $taxAmount,
            'tax2'     => $taxAmount2,
            'tax_rate' => $taxRate,
            'total'    => $total,
        ]);


        return $invoice->fresh();
    }

    /**
     * Mark invoice as paid.
     * Creates a transaction record and updates client paid date.
     */
    public function markPaid(Invoice $invoice, ?string $transactionId = null, string $gateway = 'manual'): Invoice
    {
        if ($invoice->status === 'Paid') {
            return $invoice;
        }

        $paidInvoice = DB::transaction(function () use ($invoice, $transactionId, $gateway) {
            $invoice->update([
                'status'    => 'Paid',
                'date_paid' => now(),
            ]);

            Transaction::create([
                'client_id'      => $invoice->client_id,
                'currency_id'    => null,
                'gateway'        => $gateway,
                'date'           => now()->toDateString(),
                'description'    => 'Invoice #' . $invoice->invoice_num . ' Payment',
                'amount_in'      => $invoice->total,
                'fees'           => 0,
                'amount_out'     => 0,
                'rate'           => 1,
                'transaction_id' => $transactionId,
                'invoice_id'     => $invoice->id,
            ]);


            // Process affiliate commission if applicable
            try {
                app(\App\Services\AffiliateService::class)->processCommission($invoice);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Affiliate commission failed for invoice #" . $invoice->id . ": " . $e->getMessage());
            }
            return $invoice->fresh();
        });

        event(new InvoicePaid($paidInvoice, $transactionId));

        return $paidInvoice;
    }

    /**
     * Apply client credit to an invoice.
     * Reduces the balance by the given amount (capped at invoice total).
     */
    public function applyCredit(Invoice $invoice, float $amount): Invoice
    {
        if ($invoice->status === 'Paid' || $invoice->status === 'Cancelled') {
            return $invoice;
        }

        $client = $invoice->client;
        $availableCredit = (float) $client->credit;

        // Cap at available credit and invoice remaining balance
        $amount = min($amount, $availableCredit, (float) $invoice->total);

        if ($amount <= 0) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $amount, $client) {
            $newCredit = (float) $invoice->credit + $amount;
            $newTotal  = max(0, (float) $invoice->subtotal + (float) $invoice->tax + (float) $invoice->tax2 - $newCredit);

            $invoice->update([
                'credit' => $newCredit,
                'total'  => $newTotal,
            ]);

            // Deduct from client credit balance
            $client->decrement('credit', $amount);

            // Auto-mark paid if fully covered
            if ($newTotal <= 0.001) {
                $this->markPaid($invoice->fresh(), null, 'credit');
            }


            return $invoice->fresh();
        });
    }

    /**
     * Generate a unique sequential invoice number with prefix.
     */
    public function generateInvoiceNumber(): string
    {
        $prefix = config('billing.invoice_prefix', 'INV-');
        $latest = Invoice::where('invoice_num', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('invoice_num');

        if ($latest) {
            $numeric = (int) ltrim(str_replace($prefix, '', $latest), '0');
            $next = $numeric + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Cancel an invoice (only if not already paid).
     */
    public function cancelInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'Paid') {
            return $invoice;
        }

        $invoice->update(['status' => 'Cancelled']);


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

        $client = \App\Models\Client::find($clientId);

        if (!$client || $client->tax_exempt) {
            return ['tax' => 0.0, 'tax_rate' => 0.0];
        }

        // Match by country + state (most specific first), then country only
        $rule = TaxRule::where('country', $client->country)
            ->where(function ($q) use ($client) {
                $q->where('state', $client->state)
                  ->orWhere('state', '')
                  ->orWhereNull('state');
            })
            ->orderByRaw("CASE WHEN state = ? THEN 0 ELSE 1 END", [$client->state ?? ''])
            ->where('level', 1)
            ->first();

        if (!$rule) {
            return ['tax' => 0.0, 'tax_rate' => 0.0];
        }

        $rate      = (float) $rule->tax_rate;
        $taxAmount = round($amount * ($rate / 100), 2);

        return ['tax' => $taxAmount, 'tax_rate' => $rate];
    }
}
