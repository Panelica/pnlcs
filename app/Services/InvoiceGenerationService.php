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
    /**
     * The products an invoice is for, so a promotion limited to some of them
     * can tell whether this is one.
     *
     * @return array<int, int>
     */
    private function invoiceProductIds(Invoice $invoice): array
    {
        $serviceIds = $invoice->items
            ->whereIn('type', ['Hosting', 'Service', 'hosting', 'service'])
            ->pluck('rel_id')
            ->filter()
            ->all();

        if ($serviceIds === []) {
            return [];
        }

        return Service::whereIn('id', $serviceIds)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The recurring promotion a renewal should carry, if any.
     *
     * The code lives on the order the service was sold under. It is only
     * carried forward while the promotion says it recurs and while it still
     * has cycles left - counted from the renewals that already carried it,
     * which applyPromotion records in the invoice notes.
     *
     * @param  array<int, Service>  $services
     */
    private function recurringPromoFor(array $services): ?string
    {
        foreach ($services as $service) {
            $service->loadMissing('order');

            $code = trim((string) ($service->order->promo_code ?? ''));

            if ($code === '') {
                continue;
            }

            $promo = Promotion::whereRaw('LOWER(code) = ?', [strtolower($code)])->first();

            if (! $promo || ! $promo->recurring) {
                continue;
            }

            $cycles = (int) ($promo->cycles ?? 0);

            if ($cycles > 0 && $this->timesPromoApplied($service, $code) >= $cycles) {
                continue;
            }

            return $promo->code;
        }

        return null;
    }

    /**
     * How many invoices for this service already carried the promotion.
     */
    private function timesPromoApplied(Service $service, string $code): int
    {
        return Invoice::whereHas('items', fn ($q) => $q
            ->where('type', 'Hosting')
            ->where('rel_id', $service->id))
            ->where('notes', 'like', '%Promo applied: '.$code.'%')
            ->count();
    }

    public function applyPromotion(Invoice $invoice, string $promoCode): bool
    {
        $promo = Promotion::where('code', $promoCode)->first();

        if (! $promo) {
            return false;
        }

        $invoice->loadMissing('items', 'client');

        // The rules that go with the code - one per customer, new customers
        // only, existing customers only, particular products - were checked in
        // the cart and nowhere else. The order endpoint hands a code straight
        // to this method, so a once-per-customer code could be spent again and
        // again by the same customer.
        if (! $promo->isValidFor($invoice->client, $this->invoiceProductIds($invoice))) {
            return false;
        }

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

            // r121-discount: a line of its own, the way the group discount is
            // done. It used to go into the invoice's credit column, which is
            // where account balance the customer actually paid in lives - two
            // different things in one field. Cancelling an invoice hands the
            // balance back and could not tell them apart, so a cancelled order
            // handed the customer the discount as real money. It also read as
            // "Credit applied" on the invoice rather than as the code they used.
            //
            // Taxed where the work is taxed, so the taxable amount falls by the
            // discount: taxing the full price and taking the discount off the
            // total charges tax on money the customer never pays.
            $hasTaxable = (float) $invoice->items->where('taxed', true)->sum('amount') > 0;

            $this->invoiceService->addLineItem($invoice, [
                'type' => 'Discount',
                'rel_id' => 0,
                'description' => "Promo code {$promo->code}",
                'amount' => -$discount,
                'taxed' => $hasTaxable,
            ]);

            $invoice->update([
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

        // A promotion the operator marked as recurring keeps its promise. The
        // switch and the number of cycles have always been saved and never
        // read, so a customer sold "20% off, recurring for three cycles" got it
        // once and paid full price at every renewal after that.
        $recurringPromo = $this->recurringPromoFor($services);

        if ($recurringPromo !== null) {
            $this->applyPromotion($invoice->fresh(), $recurringPromo);
            $invoice = $invoice->fresh();
        }

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
    /**
     * The allowance to bill against, in megabytes.
     *
     * What the panel reported, and failing that what the product was sold
     * with. Only the cPanel module records both figures; Panelica records the
     * disk quota alone and Plesk neither, so without this an overage line
     * could never be raised on those panels however much was used.
     */
    private function limitFor(Service $service, string $kind): int
    {
        $recorded = (int) ($kind === 'disk' ? $service->disk_limit : $service->bw_limit);

        if ($recorded > 0) {
            return $recorded;
        }

        $config = is_string($service->product?->config_options)
            ? json_decode($service->product->config_options, true)
            : ($service->product?->config_options ?? []);

        if (! is_array($config)) {
            return 0;
        }

        return (int) ($config[$kind === 'disk' ? 'res_disk_mb' : 'res_bandwidth_mb'] ?? 0);
    }

    public function calculateOverageItems(Service $service): array
    {
        $product = $service->product;
        if (! $product || ! $product->overage_enabled) {
            return [];
        }

        $items = [];

        // Disk overage
        $diskUsage = (int) ($service->disk_usage ?? 0);
        $diskLimit = $this->limitFor($service, 'disk');
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
        $bwLimit = $this->limitFor($service, 'bw');
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
