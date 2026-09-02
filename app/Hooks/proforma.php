<?php

use App\Models\Invoice;
use App\Services\ProformaService;
use Illuminate\Support\Facades\Log;

/**
 * A paid proforma is not a tax document: issue the corresponding VAT invoice,
 * then re-fire InvoicePaid for it so the KSeF hook (and any other module)
 * sees the VAT invoice.
 */
add_hook('InvoicePaid', function (array $params): void {
    $invoice = $params['invoice'] ?? null;

    if (! $invoice instanceof Invoice) {
        return;
    }

    if (($invoice->type ?? 'vat') !== 'proforma') {
        return;
    }

    try {
        $vat = app(ProformaService::class)->issueVatInvoice($invoice);

        if ($vat) {
            run_hook('InvoicePaid', ['invoice' => $vat, 'transactionId' => $params['transactionId'] ?? null]);
        }
    } catch (\Throwable $e) {
        Log::error('Proforma: could not issue VAT invoice for #'.$invoice->id.': '.$e->getMessage());
    }
});
