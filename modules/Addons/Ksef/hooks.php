<?php

use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Modules\Ksef\KsefService;

/**
 * Hand every paid invoice to KSeF. Runs inside the addon's hook file, which
 * is only loaded while the addon is active, so a disabled addon never queues.
 */
add_hook('InvoicePaid', function (array $params): void {
    $invoice = $params['invoice'] ?? null;

    if (! $invoice instanceof Invoice) {
        return;
    }

    try {
        app(KsefService::class)->queueForInvoice($invoice);
    } catch (\Throwable $e) {
        Log::error('KSeF: could not queue invoice #'.$invoice->id.': '.$e->getMessage());
    }
});
