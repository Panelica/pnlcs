<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\BillingCycleHelper;
use App\Services\DomainService;
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
                } elseif ($item->type === 'Addon') {
                    $this->renewAddon((int) $item->rel_id);
                }
            } catch (\Throwable $e) {
                Log::error("RenewOnPayment failed for invoice #{$invoice->id} item {$item->id}: ".$e->getMessage());
            }
        }
    }

    private function renewService(int $serviceId): void
    {
        $service = Service::find($serviceId);
        if (! $service) {
            return;
        }

        $cycle = $service->billing_cycle ?: 'Monthly';
        $base = $service->next_due_date ? Carbon::parse($service->next_due_date) : now();
        $service->update([
            'next_due_date' => BillingCycleHelper::advance($base, $cycle)->toDateString(),
        ]);

        Log::info("RenewOnPayment: service #{$service->id} advanced to {$service->next_due_date}");
    }

    /**
     * An addon invoice is either its first one, in which case paying it starts
     * the addon, or a renewal, in which case it moves the date on.
     */
    private function renewAddon(int $serviceAddonId): void
    {
        $addon = ServiceAddon::find($serviceAddonId);
        if (! $addon) {
            return;
        }

        $cycle = $addon->billing_cycle ?: 'Monthly';

        if (strtolower((string) $addon->status) === 'pending' || ! $addon->next_due_date) {
            $addon->update([
                'status' => 'active',
                'next_due_date' => BillingCycleHelper::advance(now(), $cycle)->toDateString(),
            ]);

            Log::info("RenewOnPayment: addon #{$addon->id} activated, due {$addon->next_due_date}");

            return;
        }

        $addon->update([
            'next_due_date' => BillingCycleHelper::advance(Carbon::parse($addon->next_due_date), $cycle)->toDateString(),
        ]);

        Log::info("RenewOnPayment: addon #{$addon->id} advanced to {$addon->next_due_date}");
    }

    private function renewDomain(int $domainId): void
    {
        $domain = Domain::find($domainId);
        if (! $domain) {
            return;
        }

        $years = max(1, (int) $domain->registration_period);

        // Delegate to DomainService, which performs the real registrar renewal
        // API call and advances the dates (falling back to a local advance when
        // no registrar module is configured or the API fails).
        app(DomainService::class)->renewDomain($domain, $years);

        Log::info("RenewOnPayment: domain #{$domain->id} ({$domain->domain}) renewed {$years}y");
    }
}
