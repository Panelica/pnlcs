<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quote;
use App\Models\QuoteItem;

class QuoteService
{
    public function createQuote(Client $client, array $data): Quote
    {
        $quote = Quote::create([
            'client_id'      => $client->id,
            'subject'        => $data['subject'],
            'date'           => $data['date'] ?? now()->toDateString(),
            'valid_until'    => $data['valid_until'],
            'status'         => 'Draft',
            'notes'          => $data['notes'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
            'proposal'       => $data['proposal'] ?? null,
            'subtotal'       => 0,
            'tax'            => 0,
            'total'          => 0,
        ]);

        if (!empty($data['items'])) {
            foreach ($data['items'] as $i => $itemData) {
                $this->addItem($quote, array_merge($itemData, ['sort_order' => $i]));
            }
        }

        return $quote->fresh('items');
    }

    public function addItem(Quote $quote, array $itemData): QuoteItem
    {
        $quantity  = max(1, (float) ($itemData['quantity'] ?? 1));
        $unitPrice = max(0, (float) ($itemData['unit_price'] ?? 0));
        $discount  = max(0, (float) ($itemData['discount'] ?? 0));

        $item = QuoteItem::create([
            'quote_id'    => $quote->id,
            'description' => $itemData['description'],
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'discount'    => $discount,
            'taxable'     => (bool) ($itemData['taxable'] ?? true),
            'sort_order'  => (int) ($itemData['sort_order'] ?? 0),
        ]);

        $this->recalculateTotals($quote);

        return $item;
    }

    public function recalculateTotals(Quote $quote): Quote
    {
        $quote->load('items');

        $subtotal = 0;
        $taxTotal = 0;
        $taxRate  = (float) config('billing.default_tax_rate', 0);

        foreach ($quote->items as $item) {
            $lineTotal  = ($item->quantity * $item->unit_price) - $item->discount;
            $lineTotal  = max(0, $lineTotal);
            $subtotal  += $lineTotal;
            if ($item->taxable && $taxRate > 0) {
                $taxTotal += $lineTotal * ($taxRate / 100);
            }
        }

        $quote->subtotal = round($subtotal, 2);
        $quote->tax      = round($taxTotal, 2);
        $quote->total    = round($subtotal + $taxTotal, 2);
        $quote->save();

        return $quote;
    }

    public function updateQuote(Quote $quote, array $data): Quote
    {
        $quote->update([
            'subject'        => $data['subject']        ?? $quote->subject,
            'date'           => $data['date']           ?? $quote->date,
            'valid_until'    => $data['valid_until']    ?? $quote->valid_until,
            'notes'          => $data['notes']          ?? $quote->notes,
            'customer_notes' => $data['customer_notes'] ?? $quote->customer_notes,
            'proposal'       => $data['proposal']       ?? $quote->proposal,
        ]);

        if (isset($data['items'])) {
            $quote->items()->delete();
            foreach ($data['items'] as $i => $itemData) {
                QuoteItem::create([
                    'quote_id'    => $quote->id,
                    'description' => $itemData['description'],
                    'quantity'    => max(1, (float) ($itemData['quantity'] ?? 1)),
                    'unit_price'  => max(0, (float) ($itemData['unit_price'] ?? 0)),
                    'discount'    => max(0, (float) ($itemData['discount'] ?? 0)),
                    'taxable'     => (bool) ($itemData['taxable'] ?? true),
                    'sort_order'  => $i,
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
        $quote->load('items');

        $invoice = Invoice::create([
            'client_id'   => $quote->client_id,
            'invoice_num' => 'INV-' . strtoupper(uniqid()),
            'date'        => now()->toDateString(),
            'due_date'    => now()->addDays(30)->toDateString(),
            'subtotal'    => $quote->subtotal,
            'tax'         => $quote->tax,
            'total'       => $quote->total,
            'tax_rate'    => 0,
            'credit'      => 0,
            'tax2'        => 0,
            'tax_rate2'   => 0,
            'status'      => 'unpaid',
            'notes'       => $quote->notes,
        ]);

        foreach ($quote->items as $item) {
            $lineTotal = ($item->quantity * $item->unit_price) - $item->discount;
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'client_id'   => $quote->client_id,
                'description' => $item->description,
                'amount'      => max(0, $lineTotal),
                'taxed'       => $item->taxable,
            ]);
        }

        $quote->update(['status' => 'Accepted']);

        return $invoice;
    }

    public function deleteQuote(Quote $quote): void
    {
        $quote->items()->delete();
        $quote->delete();
    }
}
