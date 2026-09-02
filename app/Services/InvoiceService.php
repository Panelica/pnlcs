<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Events\InvoiceCreated;
use App\Events\InvoicePaid;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
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
                // Freeze the buyer alongside the money (issue #7)
                ...Invoice::buyerSnapshotFrom($client),
                'invoice_num' => $options['invoice_num'] ?? $this->generateInvoiceNumber($options['type'] ?? $this->defaultType()),
                'date' => $options['date'] ?? now()->toDateString(),
                'due_date' => $options['due_date'] ?? now()->addDays((int) Setting::get('InvoiceDueDays', 14))->toDateString(),
                'status' => $options['status'] ?? InvoiceStatus::Unpaid->value,
                'type' => $options['type'] ?? $this->defaultType(),
                'source_invoice_id' => $options['source_invoice_id'] ?? null,
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

            $this->applyGroupDiscount($invoice->fresh());

            return $this->recalculateTotals($invoice->fresh());
        });

        event(new InvoiceCreated($invoice));

        $this->applyAvailableCredit($invoice);

        return $invoice->fresh();
    }

    /**
     * Add a line item to an invoice and recalculate totals.
     */
    /**
     * Take the customer's group discount off the invoice.
     *
     * A line of its own rather than a quiet adjustment to the total: the
     * customer can see what they were given, and the taxable amount drops with
     * it so tax lands on what they actually pay.
     *
     * Topping up an account balance is never discounted — buying 100 of credit
     * for 85 would be a way to print money.
     */
    private function applyGroupDiscount(Invoice $invoice): void
    {
        $percent = (float) ($invoice->client?->group?->discount_percent ?? 0);

        if ($percent <= 0) {
            return;
        }

        if ($invoice->items->contains(fn ($item) => $item->type === 'AddFunds')) {
            return;
        }

        if ($invoice->items->contains(fn ($item) => $item->type === 'Discount')) {
            return;
        }

        $groupName = $invoice->client->group->name;

        // Taxable and untaxed lines are discounted separately so the taxable
        // amount falls by exactly the discount given on taxable work.
        foreach ([true, false] as $taxed) {
            $base = (float) $invoice->items->where('taxed', $taxed)->sum('amount');

            if ($base <= 0) {
                continue;
            }

            $this->addLineItem($invoice, [
                'type' => 'Discount',
                'rel_id' => 0,
                'description' => "{$groupName} discount ({$percent}%)",
                'amount' => -round($base * ($percent / 100), 2),
                'taxed' => $taxed,
            ]);
        }
    }

    public function addLineItem(Invoice $invoice, array $itemData): InvoiceItem
    {
        $taxRate = array_key_exists('tax_rate', $itemData) && $itemData['tax_rate'] !== null
            ? (float) $itemData['tax_rate']
            : null;

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'type' => $itemData['type'] ?? 'Other',
            'rel_id' => $itemData['rel_id'] ?? 0,
            'description' => $itemData['description'] ?? '',
            'qty' => $itemData['qty'] ?? 1,
            'amount' => $itemData['amount'] ?? 0,
            'taxed' => $itemData['taxed'] ?? ($taxRate === null ? true : $taxRate > 0),
            'tax_rate' => $taxRate,
            'tax_label' => $itemData['tax_label'] ?? null,
            'unit' => $itemData['unit'] ?? null,
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

        // Each line carries a unit price (amount) and a quantity; the line's
        // money value is the product of the two. Callers that predate the qty
        // column pass amount only and get qty 1, so nothing changes for them.
        $subtotal = $invoice->items->sum(fn ($item) => (float) $item->amount * (int) $item->qty);

        // Per-item VAT: when a line carries its own rate (the admin invoice
        // builder), tax is the sum of each line's amount × its own percentage.
        // Legacy lines without a rate fall back to the invoice-level rate.
        $perItem = $invoice->items->contains(fn ($item) => $item->tax_rate !== null);

        if ($perItem) {
            $taxAmount = round($invoice->items->sum(
                fn ($item) => (float) $item->amount * (int) $item->qty * ((float) ($item->tax_rate ?? 0) / 100)
            ), 2);

            $rates = $invoice->items->pluck('tax_rate')->filter(fn ($r) => $r !== null && (float) $r > 0)->unique()->values();
            $taxRate = $rates->count() === 1 ? (float) $rates->first() : 0.0;
        } else {
            $taxableAmount = $invoice->items
                ->where('taxed', true)
                ->sum(fn ($item) => (float) $item->amount * (int) $item->qty);

            $taxRate = (float) $invoice->tax_rate;

            if ($taxRate === 0.0 && ! $invoice->client->tax_exempt) {
                $taxRate = $this->calculateTax($taxableAmount, $invoice->client_id)['tax_rate'];
            }

            $taxAmount = $taxRate > 0 ? round($taxableAmount * ($taxRate / 100), 2) : 0;
        }

        $credit = (float) $invoice->credit;
        $total = max(0, $subtotal + $taxAmount - $credit);

        $invoice->update([
            'subtotal' => $subtotal,
            'tax' => $taxAmount,
            'tax2' => 0,
            'tax_rate' => $taxRate,
            'tax_rate2' => 0,
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
     * Generate a unique sequential invoice number from the configurable
     * format setting. The format accepts {year}, {yy}, {month}, {day} and
     * {num} placeholders; {num} is the next number in the series derived
     * from the last invoice stored in the database.
     */
    public function generateInvoiceNumber(?string $type = null): string
    {
        $type = $type ?: $this->defaultType();
        $format = $this->numberFormatFor($type);

        return $this->renderInvoiceNumber($format, $this->nextInvoiceSequence($format, $type));
    }

    /**
     * The default invoice type for new invoices: proforma when the proforma
     * scheme is enabled, otherwise VAT.
     */
    public function defaultType(): string
    {
        return Setting::get('ProformaEnabled', '0') === '1' ? 'proforma' : 'vat';
    }

    /**
     * The numbering format for a given invoice type.
     */
    public function numberFormatFor(?string $type = null): string
    {
        if ($type === 'proforma') {
            $format = (string) Setting::get('ProformaNumberFormat', 'PRO-{year}/{month}-{num}');

            return trim($format) !== '' ? $format : 'PRO-{year}/{month}-{num}';
        }

        $format = (string) Setting::get('InvoiceNumberFormat', 'INV-{num}');

        return trim($format) !== '' ? $format : 'INV-{num}';
    }

    /**
     * The next sequence number for previews (the highest number already
     * issued plus one).
     */
    public function nextInvoiceSequence(?string $format = null, ?string $type = null): int
    {
        $type = $type ?: $this->defaultType();
        $format ??= $this->numberFormatFor($type);

        // Without {num} the number has nowhere to grow: fall back to the
        // row id, which still keeps them unique.
        $pos = strpos((string) $format, '{num}');
        if ($pos === false) {
            return 1 + (int) Invoice::where('type', $type)->max('id');
        }

        // {num} last: the series continues across format changes, reading
        // the highest trailing number already issued wherever it hangs.
        // Non-numeric rows (the add-funds numbers place random characters
        // there) simply contribute nothing because they do not end in a
        // run of digits. The series only grows, so nothing is ever
        // issued twice.
        if (substr((string) $format, -5) === '{num}') {
            $query = Invoice::where('type', $type)
                ->where('invoice_num', 'regexp', '[0-9]$')
                ->selectRaw('MAX(CAST(REGEXP_REPLACE(invoice_num, "^.*[^0-9]", "") AS UNSIGNED)) as seq');

            // Reset each year: only the numbers issued this year count,
            // so January starts a fresh sequence again.
            if (Setting::get('InvoiceNumberYearlyReset', '0') === '1') {
                $query->whereYear('date', now()->year);
            }

            return 1 + (int) $query->value('seq');
        }

        // {num} in the middle: fall back to the static prefix before it,
        // which is where the digits actually sit on issued numbers.
        $prefix = substr((string) $format, 0, $pos);
        if ($prefix === '') {
            return 1 + (int) Invoice::where('type', $type)->max('id');
        }

        $like = addcslashes($prefix, '%_').'%';

        return 1 + (int) Invoice::where('type', $type)
            ->where('invoice_num', 'like', $like)
            ->selectRaw('MAX(CAST(SUBSTRING(invoice_num, ?) AS UNSIGNED)) as seq', [strlen($prefix) + 1])
            ->value('seq');
    }

    /**
     * Render a format with the given sequence number.
     */
    public function renderInvoiceNumber(string $format, int $sequence): string
    {
        $now = now();

        return str_replace(
            ['{year}', '{yy}', '{month}', '{day}', '{num}'],
            [$now->format('Y'), $now->format('y'), $now->format('m'), $now->format('d'), str_pad($sequence, 6, '0', STR_PAD_LEFT)],
            $format
        );
    }

    /**
     * Cancel an invoice (only if not already paid).
     */
    /**
     * Hand back the account balance that was applied to this invoice.
     *
     * The invoice is restored to what it was billed for and carries no credit
     * afterwards, so the same balance cannot be returned twice.
     */
    private function returnAppliedCredit(Invoice $invoice): void
    {
        $applied = round((float) $invoice->credit, 2);
        $client = $invoice->client;

        if ($applied <= 0.009 || ! $client) {
            return;
        }

        DB::transaction(function () use ($invoice, $client, $applied) {
            $client->increment('credit', $applied);

            Credit::create([
                'client_id' => $client->id,
                'admin_id' => null,
                'date' => now()->toDateString(),
                'description' => "Invoice #{$invoice->invoice_num} cancelled — credit returned",
                'amount' => $applied,
            ]);

            $invoice->update([
                'credit' => 0,
                'total' => round((float) $invoice->total + $applied, 2),
            ]);
        });
    }

    public function cancelInvoice(Invoice $invoice): Invoice
    {
        $status = strtolower((string) $invoice->status);

        // A paid invoice is refunded, not cancelled; an already cancelled one
        // must not hand its money over a second time.
        if (in_array($status, [InvoiceStatus::Paid->value, InvoiceStatus::Cancelled->value], true)) {
            return $invoice;
        }

        app(PaymentService::class)->returnPaymentsToCredit(
            $invoice,
            "Invoice #{$invoice->invoice_num} cancelled — payment returned as credit"
        );

        // r120-credit: balance spent on this invoice comes back too. Applying
        // credit writes no transaction - no money moved - so returning the
        // payments left it behind: the customer's balance was taken, the
        // invoice it was taken for was voided, and nothing gave it back.
        $this->returnAppliedCredit($invoice);

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

        $rate = $this->rateFor($client);

        return [
            'tax' => round($amount * ($rate / 100), 2),
            'tax_rate' => $rate,
        ];
    }

    /**
     * The rate that applies to a customer, falling back to the default rule.
     */
    private function rateFor(Client $client): float
    {
        $rule = $this->taxRuleFor($client);

        return $rule ? (float) $rule->tax_rate : 0.0;
    }

    /**
     * The tax rule that applies to a customer, or null.
     *
     * Rates are grouped by country and state: an exact country+state match
     * wins, then the country's default (empty state), then the global default
     * (empty country). Kept separate from rateFor so callers can also read the
     * rule's label (name) while building an invoice.
     */
    public function taxRuleFor(Client $client): ?TaxRule
    {
        $country = (string) $client->country;
        $state = (string) $client->state;

        $rule = TaxRule::where('country', $country)
            ->where('state', $state)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($rule) {
            return $rule;
        }

        $rule = TaxRule::where('country', $country)
            ->where('state', '')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($rule) {
            return $rule;
        }

        return TaxRule::where('country', '')
            ->where('state', '')
            ->where('is_default', true)
            ->first();
    }

    /**
     * Every rate configured for a customer's country/state group.
     *
     * Mirrors taxRuleFor() but returns the whole group so the invoice builder
     * can offer the operator a choice of the rates the customer is eligible
     * for, with the default first.
     *
     * @return \Illuminate\Support\Collection<int, TaxRule>
     */
    public function taxRatesFor(Client $client): \Illuminate\Support\Collection
    {
        $country = (string) $client->country;
        $state = (string) $client->state;

        $rates = TaxRule::where('country', $country)
            ->where('state', $state)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        if ($rates->isNotEmpty()) {
            return $rates;
        }

        $rates = TaxRule::where('country', $country)
            ->where('state', '')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        if ($rates->isNotEmpty()) {
            return $rates;
        }

        return TaxRule::where('country', '')
            ->where('state', '')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }
}
