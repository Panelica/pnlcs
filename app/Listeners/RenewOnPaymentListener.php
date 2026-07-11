<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Service;
use App\Services\BillingCycleHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Advance billing dates when a RENEWAL invoice is paid.
 *
 * A renewal invoice is one that carries Hosting/Domain line items but is NOT
 * tied to an Order (new-order invoices are handled by AutoAcceptOrderListener,
 * which provisions and already sets the initial due date). Without this, a paid
 * renewal would leave next_due_date in the past and the generator would re-issue
 * the same invoice on its next run.
 */
class RenewOnPaymentListener
{
    public function handleInvoicePaid(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        // New-order invoices are provisioned elsewhere; don't advance them here.
        if (Order::where('invoice_id', $invoice->id)->exists()) {
            return;
        }

        foreach ($invoice->items as $item) {
            try {
                if ($item->type === 'Hosting') {
                    $this->renewService((int) $item->rel_id);
                } elseif ($item->type === 'Domain') {
                    $this->renewDomain((int) $item->rel_id);
                }
            } catch (\Throwable $e) {
                Log::error("RenewOnPayment failed for invoice #{$invoice->id} item {$item->id}: " . $e->getMessage());
            }
        }
    }

    private function renewService(int $serviceId): void
    {
        $service = Service::find($serviceId);
        if (!$service) {
            return;
        }

        $cycle = $service->billing_cycle ?: 'Monthly';
        $base  = $service->next_due_date ? Carbon::parse($service->next_due_date) : now();
        $service->update([
            'next_due_date' => BillingCycleHelper::advance($base, $cycle)->toDateString(),
        ]);

        Log::info("RenewOnPayment: service #{$service->id} advanced to {$service->next_due_date}");
    }

    private function renewDomain(int $domainId): void
    {
        $domain = Domain::find($domainId);
        if (!$domain) {
            return;
        }

        $years   = max(1, (int) $domain->registration_period);
        $dueBase = $domain->next_due_date ? Carbon::parse($domain->next_due_date) : now();
        $expBase = $domain->expiry_date ? Carbon::parse($domain->expiry_date) : $dueBase;

        $domain->update([
            'next_due_date' => $dueBase->copy()->addYears($years)->toDateString(),
            'expiry_date'   => $expBase->copy()->addYears($years)->toDateString(),
        ]);

        Log::info("RenewOnPayment: domain #{$domain->id} ({$domain->domain}) renewed {$years}y to {$domain->expiry_date}");
    }
}
