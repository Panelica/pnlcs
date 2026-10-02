<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Services\MassPaymentService;
use Illuminate\Support\Facades\Log;

/** A paid payment invoice settles the invoices it lists (MassPaymentService). */
class SettleMassPaymentListener
{
    public function handleInvoicePaid(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        if (! $invoice->items()->where('type', MassPaymentService::ITEM_TYPE)->exists()) {
            return;
        }

        try {
            app(MassPaymentService::class)->settle($invoice);
        } catch (\Throwable $e) {
            // The money is on the account as credit either way; nothing is lost.
            Log::error('Mass payment settlement failed for invoice #'.$invoice->id.': '.$e->getMessage());
        }
    }
}
