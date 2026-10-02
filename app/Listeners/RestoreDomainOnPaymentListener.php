<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Services\DomainRestoreService;
use Illuminate\Support\Facades\Log;

/** A paid restore invoice brings its domain back (DomainRestoreService). */
class RestoreDomainOnPaymentListener
{
    public function handleInvoicePaid(InvoicePaid $event): void
    {
        if (! $event->invoice->items()->where('type', DomainRestoreService::ITEM_TYPE)->exists()) {
            return;
        }

        try {
            app(DomainRestoreService::class)->restorePaid($event->invoice);
        } catch (\Throwable $e) {
            Log::error('Domain restore after payment failed for invoice #'.$event->invoice->id.': '.$e->getMessage());
        }
    }
}
