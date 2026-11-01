<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Events\InvoiceCreated;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Issues the VAT invoice for a paid proforma.
 *
 * When the proforma scheme is enabled, new invoices are proformas. Paying a
 * proforma replaces it with a VAT invoice: same items and totals, the next
 * number in the VAT series, and a link back to the source proforma.
 */
class ProformaService
{
    /**
     * Issue (once) the VAT invoice for a paid proforma.
     */
    public function issueVatInvoice(Invoice $proforma): ?Invoice
    {
        if (($proforma->type ?? 'vat') !== 'proforma') {
            return null;
        }

        $existing = Invoice::where('source_invoice_id', $proforma->id)->first();
        if ($existing) {
            return $existing;
        }

        try {
            $vat = DB::transaction(function () use ($proforma) {
                $vat = Invoice::create([
                    'client_id' => $proforma->client_id,
                    ...Invoice::buyerSnapshotFrom($proforma->client),
                    'invoice_num' => app(InvoiceService::class)->generateInvoiceNumber('vat'),
                    'date' => now()->toDateString(),
                    'due_date' => now()->toDateString(),
                    'date_paid' => now(),
                    'status' => InvoiceStatus::Paid->value,
                    'type' => 'vat',
                    'source_invoice_id' => $proforma->id,
                    'subtotal' => $proforma->subtotal,
                    'credit' => $proforma->credit,
                    'tax' => $proforma->tax,
                    'tax2' => $proforma->tax2,
                    'total' => $proforma->total,
                    'tax_rate' => $proforma->tax_rate,
                    'tax_rate2' => $proforma->tax_rate2,
                    'payment_method' => $proforma->payment_method,
                    'notes' => $this->withProformaReference($proforma),
                    // The same amounts at the same rate. Left out, the model
                    // stamped today's rate and bulletin on the VAT invoice, so
                    // a TRY customer's legal invoice showed a different lira
                    // amount from the proforma they had paid.
                    'source_currency' => $proforma->source_currency,
                    'billing_currency' => $proforma->billing_currency ?? $proforma->source_currency,
                    'exchange_rate' => $proforma->exchange_rate,
                    'exchange_rate_source' => $proforma->exchange_rate_source,
                    'exchange_rate_kind' => $proforma->exchange_rate_kind,
                    'exchange_rate_date' => $proforma->exchange_rate_date,
                    'exchange_rate_ref' => $proforma->exchange_rate_ref,
                    'tax_mode' => $proforma->tax_mode,
                ]);

                foreach ($proforma->items as $item) {
                    InvoiceItem::create([
                        'invoice_id' => $vat->id,
                        'client_id' => $proforma->client_id,
                        'type' => $item->type,
                        'rel_id' => $item->rel_id,
                        'description' => $item->description,
                        'qty' => $item->qty,
                        'amount' => $item->amount,
                        'taxed' => $item->taxed,
                        'tax_rate' => $item->tax_rate,
                        'tax_label' => $item->tax_label,
                        'unit' => $item->unit,
                        'due_date' => $item->due_date,
                    ]);
                }

                Log::info('Proforma #'.$proforma->id.' issued VAT invoice #'.$vat->id.' ('.$vat->invoice_num.')');

                return $vat;
            });

            // Notify the client (with the VAT invoice PDF) — outside the
            // transaction so listeners see committed state.
            event(new InvoiceCreated($vat));

            return $vat;
        } catch (\Throwable $e) {
            Log::error('Could not issue VAT invoice for proforma #'.$proforma->id.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * The notes the VAT invoice carries: the proforma's own notes, with a line
     * naming the proforma it was issued for, so the two documents link in
     * plain text. Existing notes are kept; the reference is appended, never
     * overwriting them. The number is read straight off the proforma, never
     * rebuilt from numbering or id.
     */
    private function withProformaReference(Invoice $proforma): ?string
    {
        $reference = __('client.invoices.proforma_reference', [
            'number' => $proforma->invoice_num,
        ], $proforma->client?->language);

        $notes = trim((string) $proforma->notes);

        return $notes === '' ? $reference : $notes."\n".$reference;
    }
}
