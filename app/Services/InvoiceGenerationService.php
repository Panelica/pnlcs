<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\BillableItem;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\ServiceAddon;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceGenerationService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Scan services where next_due_date is within the generation window,
     * group by client, and create one invoice per client for all due items.
     *
     * @return array{generated: int, skipped: int, errors: int, invoice_ids: int[]}
     */
    public function generateDueInvoices(): array
    {
        $daysAhead = (int) config('billing.invoice_days_before_due', 14);
        $cutoff = now()->addDays($daysAhead)->endOfDay();

        // Only active services that are due for renewal and not already
        // invoiced. The dedup guard used to be missing entirely despite what
        // this comment claimed, so calling this twice billed the customer
        // twice.
        $services = Service::with('client', 'product')
            ->where('status', 'active')
            // The customer turned renewal off; billing them anyway is what got
            // the account suspended for an invoice they never wanted.
            ->where('auto_renew', true)
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', $cutoff)
            ->where('amount', '>', 0)
            ->whereHas('client')
            ->whereDoesntHave('client.invoices', fn ($q) => $q
                ->whereNotIn('status', InvoiceStatus::settled())
                ->whereHas('items', fn ($i) => $i->where('type', 'Hosting')->whereColumn('rel_id', 'services.id')))
            ->get();

        // Addons renew on their own dates, so they are collected separately and
        // then billed on the same invoice as the client's due services.
        $addons = app(AddonService::class)->dueQuery($cutoff)->get();

        $domains = Domain::with('client')
            // Grace counts as billable: the registry still renews at the
            // ordinary price and the customer can still keep the domain.
            ->whereIn('status', ['active', 'grace'])
            // A domain has no auto-renew column: the customer's switch flips
            // payment_method to none, which is the signal to leave it alone.
            // A null is not a no: SQL would drop those rows from the comparison
            // and quietly stop billing them.
            ->where(fn ($q) => $q->whereNull('payment_method')->orWhere('payment_method', '!=', 'none'))
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', $cutoff)
            ->where('recurring_amount', '>', 0)
            ->whereHas('client')
            ->whereDoesntHave('client.invoices', fn ($q) => $q
                ->whereNotIn('status', InvoiceStatus::settled())
                ->whereHas('items', fn ($i) => $i->where('type', 'Domain')->whereColumn('rel_id', 'domains.id')))
            ->get();

        // One-off charges an operator has added to an account. They have their
        // own due date and, until now, no way of reaching an invoice at all.
        $charges = BillableItem::with('client')
            ->whereNull('invoice_id')
            ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '<=', $cutoff))
            ->whereHas('client')
            ->get();

        // Recurring ones are a feature nobody has built: billing them once and
        // marking them done would be wrong, so they are left alone and said
        // out loud rather than dropped in silence.
        [$recurring, $charges] = $charges->partition(fn ($c) => (bool) $c->recur);

        if ($recurring->isNotEmpty()) {
            Log::warning('InvoiceGenerationService: recurring billable items are not billed', [
                'count' => $recurring->count(),
                'ids' => $recurring->pluck('id')->all(),
            ]);
        }

        $grouped = $services->groupBy('client_id');
        $groupedAddons = $addons->groupBy('client_id');
        $groupedDomains = $domains->groupBy('client_id');
        $groupedCharges = $charges->groupBy('client_id');
        $summary = ['generated' => 0, 'skipped' => 0, 'errors' => 0, 'invoice_ids' => []];

        $clientIds = $grouped->keys()
            ->merge($groupedAddons->keys())
            ->merge($groupedDomains->keys())
            ->merge($groupedCharges->keys())
            ->unique();

        foreach ($clientIds as $clientId) {
            $clientServices = $grouped->get($clientId, collect());
            $clientAddons = $groupedAddons->get($clientId, collect());
            $clientDomains = $groupedDomains->get($clientId, collect());
            $clientCharges = $groupedCharges->get($clientId, collect());
            $client = $clientServices->first()?->client
                ?? $clientAddons->first()?->client
                ?? $clientDomains->first()?->client
                ?? $clientCharges->first()?->client;

            if (! $client) {
                $summary['skipped']++;

                continue;
            }

            try {
                $invoice = $this->generateForServices(
                    $client,
                    $clientServices->all(),
                    $clientAddons->all(),
                    $clientDomains->all(),
                    $clientCharges->all()
                );

                if ($invoice) {
                    $summary['generated']++;
                    $summary['invoice_ids'][] = $invoice->id;
                } else {
                    $summary['skipped']++;
                }
            } catch (\Throwable $e) {
                Log::error('Invoice generation failed for client '.$clientId.': '.$e->getMessage());
                $summary['errors']++;
            }
        }

        return $summary;
    }

    /**
     * Generate an invoice for a specific service.
     */
    public function generateForService(Service $service): ?Invoice
    {
        $service->loadMissing('client', 'product');

        if (! $service->client) {
            return null;
        }

        $addons = ServiceAddon::with('addon', 'service')
            ->where('service_id', $service->id)
            ->billable()
            ->whereNotNull('next_due_date')
            ->get()
            ->all();

        return $this->generateForServices($service->client, [$service], $addons);
    }

    /**
     * Apply a promotion code to an invoice.
     * Validates the code, applies the discount, and increments usage counter.
     *
     * @return bool True if promotion was applied successfully.
     */
    public function applyPromotion(Invoice $invoice, string $promoCode): bool
    {
        $promo = Promotion::where('code', $promoCode)->first();

        if (! $promo || ! $promo->isValid()) {
            return false;
        }

        $invoice->loadMissing('items');

        return DB::transaction(function () use ($invoice, $promo) {
            $subtotal = (float) $invoice->subtotal;

            if ($subtotal <= 0) {
                return false;
            }

            // Calculate discount amount
            $discount = match ($promo->type) {
                'percentage' => round($subtotal * ($promo->value / 100), 2),
                'fixed' => min((float) $promo->value, $subtotal),
                default => 0.0,
            };

            if ($discount <= 0) {
                return false;
            }

            // Apply as credit on the invoice. The discount comes off the
            // taxable amount as well: taxing the full price and then knocking
            // the discount off the total charges tax on money the customer
            // never pays, and it disagreed with the figure the cart quoted.
            $newCredit = (float) $invoice->credit + $discount;

            $taxable = max(0, (float) $invoice->items->where('taxed', true)->sum('amount') - $discount);
            $taxRate = (float) $invoice->tax_rate;
            $taxRate2 = (float) $invoice->tax_rate2;
            $newTax = $taxRate > 0 ? round($taxable * ($taxRate / 100), 2) : 0.0;
            $newTax2 = $taxRate2 > 0 ? round($taxable * ($taxRate2 / 100), 2) : 0.0;

            $newTotal = max(0, $subtotal + $newTax + $newTax2 - $newCredit);

            $invoice->update([
                'credit' => $newCredit,
                'tax' => $newTax,
                'tax2' => $newTax2,
                'total' => $newTotal,
                'notes' => trim(($invoice->notes ?? '')."\nPromo applied: {$promo->code} (-".money_fmt($discount).')'),
            ]);

            // Increment promo usage counter
            $promo->increment('uses');

            return true;
        });
    }

    /**
     * Mark overdue invoices (unpaid and past due date).
     *
     * @return int Number of invoices marked as Overdue.
     */
    public function markOverdueInvoices(): int
    {
        return Invoice::where('status', 'unpaid')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->update(['status' => 'overdue']);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build line items from a list of services and create a single invoice.
     *
     * @param  Service[]  $services
     */
    /**
     * @param  array<int, BillableItem>  $charges
     */
    private function generateForServices(Client $client, array $services, array $addons = [], array $domains = [], array $charges = []): ?Invoice
    {
        if (empty($services) && empty($addons) && empty($domains) && empty($charges)) {
            return null;
        }

        $items = [];

        foreach ($services as $service) {
            $service->loadMissing('product');
            $productName = $service->product->name ?? 'Hosting Service';
            $billingCycle = $service->billing_cycle ?? 'Monthly';
            $dueDate = $service->next_due_date instanceof Carbon
                ? $service->next_due_date
                : Carbon::parse($service->next_due_date);

            $items[] = [
                'type' => 'Hosting',
                'rel_id' => $service->id,
                'description' => "{$productName} ({$billingCycle}) — {$service->domain} — Due: {$dueDate->format(date_fmt())}",
                'amount' => (float) $service->amount,
                'taxed' => $service->product->tax ?? true,
                'due_date' => $dueDate->toDateString(),
            ];

            // Overage billing: disk
            $overageItems = $this->calculateOverageItems($service);
            foreach ($overageItems as $overageItem) {
                $items[] = $overageItem;
            }
        }

        $addonService = app(AddonService::class);

        foreach ($addons as $addon) {
            $items[] = $addonService->lineItem($addon);
        }

        foreach ($charges as $charge) {
            $items[] = [
                'type' => 'BillableItem',
                'rel_id' => $charge->id,
                'description' => $charge->description,
                'amount' => (float) $charge->amount,
                'taxed' => true,
            ];
        }

        foreach ($domains as $domain) {
            $items[] = [
                'type' => 'Domain',
                'rel_id' => $domain->id,
                'description' => 'Domain Renewal: '.$domain->domain.' ('.((int) ($domain->registration_period ?? 1)).'y)',
                'amount' => (float) $domain->recurring_amount,
                'taxed' => true,
                'due_date' => $domain->next_due_date?->toDateString(),
            ];
        }

        $options = [
            'due_date' => now()->addDays((int) config('billing.invoice_due_days', 14))->toDateString(),
            'notes' => 'Auto-generated renewal invoice.',
        ];

        $invoice = $this->invoiceService->createInvoice($client, $items, $options);

        // Which invoice each one-off charge went on. Without this the same
        // charge would be added to every invoice the client is ever sent.
        if ($charges !== []) {
            BillableItem::whereIn('id', array_map(fn ($c) => $c->id, $charges))
                ->update(['invoice_id' => $invoice->id]);
        }

        return $invoice;
    }

    /**
     * Calculate overage line items for a service based on disk/bandwidth usage.
     * Only applies if the product has overage_enabled = true.
     *
     * @return array Line items for overage charges
     */
    public function calculateOverageItems(Service $service): array
    {
        $product = $service->product;
        if (! $product || ! $product->overage_enabled) {
            return [];
        }

        $items = [];

        // Disk overage
        $diskUsage = (int) ($service->disk_usage ?? 0);
        $diskLimit = (int) ($service->disk_limit ?? 0);
        $diskRate = (float) ($product->overage_disk_rate ?? 0);

        if ($diskLimit > 0 && $diskUsage > $diskLimit && $diskRate > 0) {
            $overageMb = $diskUsage - $diskLimit;
            $amount = round($overageMb * $diskRate, 2);

            if ($amount > 0) {
                $items[] = [
                    'type' => 'Overage',
                    'rel_id' => $service->id,
                    'description' => "Disk Overage: {$overageMb} MB over {$diskLimit} MB limit @ ".currency_symbol()."{$diskRate}/MB — {$service->domain}",
                    'amount' => $amount,
                    'taxed' => $product->tax ?? true,
                ];
            }
        }

        // Bandwidth overage
        $bwUsage = (int) ($service->bw_usage ?? 0);
        $bwLimit = (int) ($service->bw_limit ?? 0);
        $bwRate = (float) ($product->overage_bw_rate ?? 0);

        if ($bwLimit > 0 && $bwUsage > $bwLimit && $bwRate > 0) {
            $overageMb = $bwUsage - $bwLimit;
            $amount = round($overageMb * $bwRate, 2);

            if ($amount > 0) {
                $items[] = [
                    'type' => 'Overage',
                    'rel_id' => $service->id,
                    'description' => "Bandwidth Overage: {$overageMb} MB over {$bwLimit} MB limit @ ".currency_symbol()."{$bwRate}/MB — {$service->domain}",
                    'amount' => $amount,
                    'taxed' => $product->tax ?? true,
                ];
            }
        }

        return $items;
    }
}
