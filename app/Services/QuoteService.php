<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\TaxRule;

class QuoteService
{
    public function createQuote(Client $client, array $data): Quote
    {
        $quote = Quote::create([
            'client_id' => $client->id,
            'subject' => $data['subject'],
            'date' => $data['date'] ?? now()->toDateString(),
            'valid_until' => $data['valid_until'],
            'status' => 'Draft',
            'notes' => $data['notes'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
            'proposal' => $data['proposal'] ?? null,
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
        ]);

        if (! empty($data['items'])) {
            foreach ($data['items'] as $i => $itemData) {
                $this->addItem($quote, array_merge($itemData, ['sort_order' => $i]));
            }
        }

        return $quote->fresh('items');
    }

    public function addItem(Quote $quote, array $itemData): QuoteItem
    {
        $quantity = max(1, (float) ($itemData['quantity'] ?? 1));
        $unitPrice = max(0, (float) ($itemData['unit_price'] ?? 0));
        $discount = max(0, (float) ($itemData['discount'] ?? 0));

        $item = QuoteItem::create([
            'quote_id' => $quote->id,
            'description' => $itemData['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'taxable' => (bool) ($itemData['taxable'] ?? true),
            'sort_order' => (int) ($itemData['sort_order'] ?? 0),
        ]);

        $this->recalculateTotals($quote);

        return $item;
    }

    public function recalculateTotals(Quote $quote): Quote
    {
        $quote->load('items');

        $subtotal = 0;
        $taxTotal = 0;
        $taxRate = TaxRule::defaultRate();

        foreach ($quote->items as $item) {
            $lineTotal = ($item->quantity * $item->unit_price) - $item->discount;
            $lineTotal = max(0, $lineTotal);
            $subtotal += $lineTotal;
            if ($item->taxable && $taxRate > 0) {
                $taxTotal += $lineTotal * ($taxRate / 100);
            }
        }

        $quote->subtotal = round($subtotal, 2);
        $quote->tax = round($taxTotal, 2);
        $quote->total = round($subtotal + $taxTotal, 2);
        $quote->save();

        return $quote;
    }

    public function updateQuote(Quote $quote, array $data): Quote
    {
        $quote->update([
            'subject' => $data['subject'] ?? $quote->subject,
            'date' => $data['date'] ?? $quote->date,
            'valid_until' => $data['valid_until'] ?? $quote->valid_until,
            'notes' => $data['notes'] ?? $quote->notes,
            'customer_notes' => $data['customer_notes'] ?? $quote->customer_notes,
            'proposal' => $data['proposal'] ?? $quote->proposal,
        ]);

        if (isset($data['items'])) {
            $quote->items()->delete();
            foreach ($data['items'] as $i => $itemData) {
                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'description' => $itemData['description'],
                    'quantity' => max(1, (float) ($itemData['quantity'] ?? 1)),
                    'unit_price' => max(0, (float) ($itemData['unit_price'] ?? 0)),
                    'discount' => max(0, (float) ($itemData['discount'] ?? 0)),
                    'taxable' => (bool) ($itemData['taxable'] ?? true),
                    'sort_order' => $i,
                ]);
            }
            $this->recalculateTotals($quote);
        }

        return $quote->fresh('items');
    }

    public function sendQuote(Quote $quote): Quote
    {
        $quote->update(['status' => 'Sent']);

        return $quote;
    }

    public function acceptQuote(Quote $quote): Quote
    {
        $quote->update(['status' => 'Accepted']);

        return $quote;
    }

    public function declineQuote(Quote $quote): Quote
    {
        $quote->update(['status' => 'Declined']);

        return $quote;
    }

    public function convertToInvoice(Quote $quote): Invoice
    {
        // r128-once: one quote, one invoice. The customer's accept button
        // refuses to run twice - the quote has to be "Sent" - and the API
        // returns early on an accepted quote. The admin button checked nothing,
        // so a second click raised a second invoice for the same work and the
        // customer was chased for both. An invoice that was cancelled is not
        // standing any more, so that quote can be raised again.
        $existing = Invoice::whereHas('items', fn ($q) => $q
            ->where('type', 'Quote')
            ->where('rel_id', $quote->id))
            ->whereNotIn('status', [InvoiceStatus::Cancelled->value, InvoiceStatus::Refunded->value])
            ->latest('id')
            ->first();

        if ($existing) {
            if ($quote->status !== 'Accepted') {
                $quote->update(['status' => 'Accepted']);
            }

            return $existing;
        }

        $quote->load('items', 'client');

        $items = [];

        foreach ($quote->items as $item) {
            $lineTotal = ($item->quantity * $item->unit_price) - $item->discount;

            $items[] = [
                'type' => 'Quote',
                'rel_id' => $quote->id,
                'description' => $item->description,
                // Whether a line is taxable is the quote's decision; how much
                // tax that comes to is the invoice's, using the same rules as
                // every other invoice.
                'taxed' => (bool) $item->taxable,
                'amount' => max(0, $lineTotal),
            ];
        }

        $invoice = app(InvoiceService::class)->createInvoice($quote->client, $items, [
            'due_date' => now()->addDays(30)->toDateString(),
            'notes' => $quote->notes,
        ]);

        $quote->update(['status' => 'Accepted']);

        return $invoice;
    }

    public function deleteQuote(Quote $quote): void
    {
        $quote->items()->delete();
        $quote->delete();
    }
}
