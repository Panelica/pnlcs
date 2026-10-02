<?php

namespace App\Services;

use App\Contracts\RestorableRegistrar;
use App\Contracts\SyncsDomainData;
use App\Models\ActivityLog;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\Invoice;
use App\Models\TodoItem;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Bringing a domain back from the registry's redemption period, at a price.
 *
 * domain_pricing.restore_price could be set per extension and nothing ever
 * billed it: a domain in redemption could not be recovered by the customer,
 * and one recovered by hand cost the operator the registry's restore fee on
 * top of a renewal billed at the ordinary price.
 *
 * The customer asks for the restore; the invoice carries the year's renewal
 * plus the extension's restore price as one "DomainRestore" line. Once it is
 * paid the registrar module restores the domain (RestorableRegistrar) and the
 * dates are read back from it; a module without restore, or a refusal, leaves
 * a to-do for an admin. Nothing is offered while the operator has set no
 * restore price for the extension.
 */
class DomainRestoreService
{
    public const ITEM_TYPE = 'DomainRestore';

    public function restorePrice(Domain $domain): ?float
    {
        $tld = '.'.implode('.', array_slice(explode('.', strtolower((string) $domain->domain)), 1));
        $price = DomainPricing::where('extension', $tld)->value('restore_price');

        return $price !== null && (float) $price > 0 ? round((float) $price, 2) : null;
    }

    public function offered(Domain $domain): bool
    {
        return strtolower((string) $domain->status) === 'redemption'
            && (float) $domain->recurring_amount > 0
            && $this->restorePrice($domain) !== null;
    }

    /** The open restore invoice for the domain, or a new one. */
    public function invoiceFor(Domain $domain): ?Invoice
    {
        if (! $this->offered($domain)) {
            return null;
        }

        $open = Invoice::where('client_id', $domain->client_id)->outstanding()
            ->whereHas('items', fn ($q) => $q->where('type', self::ITEM_TYPE)->where('rel_id', $domain->id))
            ->latest('id')->first();
        if ($open) {
            return $open;
        }

        $domain->loadMissing('client');
        $amount = round((float) $domain->recurring_amount + (float) $this->restorePrice($domain), 2);

        $invoice = app(InvoiceService::class)->createInvoice($domain->client, [[
            'type' => self::ITEM_TYPE,
            'rel_id' => $domain->id,
            'description' => __('client.domains.restore_line', ['domain' => $domain->domain]),
            'amount' => $amount,
        ]], ['due_date' => today()]);

        ActivityLog::log("Restore invoice #{$invoice->invoice_num} raised for {$domain->domain}", auth()->user()?->email, $domain->client_id);

        return $invoice;
    }

    /** Called once a restore invoice is paid. */
    public function restorePaid(Invoice $invoice): void
    {
        foreach ($invoice->items()->where('type', self::ITEM_TYPE)->get() as $line) {
            $domain = Domain::where('client_id', $invoice->client_id)->find($line->rel_id);
            if ($domain) {
                $this->restore($domain, $invoice);
            }
        }
    }

    private function restore(Domain $domain, Invoice $invoice): void
    {
        $module = filled($domain->registrar)
            ? app(ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar) : null;

        if ($module instanceof RestorableRegistrar) {
            try {
                $result = $module->restore($domain);
            } catch (\Throwable $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }

            if ($result['success'] ?? false) {
                $domain->update(['status' => 'active']);
                if ($module instanceof SyncsDomainData) {
                    try {
                        $module->syncDomain($domain->fresh());
                    } catch (\Throwable $e) {
                        Log::warning("Domain sync after restore failed for {$domain->domain}: {$e->getMessage()}");
                    }
                }
                ActivityLog::log("Domain {$domain->domain} restored at the registrar (invoice #{$invoice->invoice_num})", null, $domain->client_id);

                return;
            }

            Log::warning("Domain restore refused for {$domain->domain}: ".($result['message'] ?? ''));
        }

        // No module that restores, or a refusal: an admin does it by hand.
        TodoItem::create([
            'title' => __('admin.domains.restore_todo', ['domain' => $domain->domain]),
            'description' => __('admin.domains.restore_todo_desc', ['invoice' => $invoice->invoice_num, 'registrar' => $domain->registrar ?: '-']),
            'status' => 'pending',
            'due_date' => today(),
        ]);
        ActivityLog::log("Domain {$domain->domain} paid for restore; left to an admin (invoice #{$invoice->invoice_num})", null, $domain->client_id);
    }
}
