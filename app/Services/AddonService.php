<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Product addons: extras sold next to a hosting package, each with its own
 * price and its own renewal date.
 *
 * The admin side could define addons and the customer's service page had a
 * table ready to list them, but nothing in between existed: no way to buy one,
 * no invoice line, and the renewal generator never looked at them. This service
 * is the missing middle, and it deliberately mirrors ConfigOptionService so the
 * two configurable extras behave the same way.
 */
class AddonService
{
    /**
     * Addons offered with a product, priced for the given cycle.
     *
     * @return Collection<int, ProductAddon>
     */
    public function availableFor(Product $product): Collection
    {
        return ProductAddon::available()
            ->with('pricing')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ProductAddon $addon) => $addon->appliesTo($product))
            ->values();
    }

    /**
     * Validate the chosen addon ids against what the product actually offers.
     * An addon from another product, or one that has been hidden or retired, is
     * rejected rather than quietly sold at whatever price it happens to carry.
     *
     * @param  array<int, int|string>  $addonIds
     * @return array<int, array{addon: ProductAddon, price: float}>
     *
     * @throws ValidationException
     */
    public function normalise(Product $product, array $addonIds, string $cycle): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $addonIds))));

        if ($ids === []) {
            return [];
        }

        $offered = $this->availableFor($product)->keyBy('id');
        $chosen = [];

        foreach ($ids as $id) {
            $addon = $offered->get($id);

            if (! $addon) {
                throw ValidationException::withMessages([
                    'addons' => __('client.cart.addon_invalid'),
                ]);
            }

            // r145-refuse: an addon the operator withdrew from this term is
            // not free on it. Selling it for nothing is not withdrawing it.
            if (! $addon->offeredOn($cycle)) {
                throw ValidationException::withMessages([
                    'addons' => __('client.cart.addon_not_on_cycle', ['addon' => $addon->name]),
                ]);
            }

            $chosen[] = ['addon' => $addon, 'price' => $addon->priceFor($cycle)];
        }

        return $chosen;
    }

    /** What the chosen addons add to the order, on top of the service itself. */
    public function priceOf(array $normalised): float
    {
        return round(array_sum(array_map(fn (array $row) => (float) $row['price'], $normalised)), 2);
    }

    /**
     * Cart-storable form: plain arrays only, since the cart is JSON.
     *
     * @return array<int, array{addon_id: int, name: string, price: float}>
     */
    public function toCartPayload(array $normalised): array
    {
        return array_map(fn (array $row) => [
            'addon_id' => (int) $row['addon']->id,
            'name' => (string) $row['addon']->name,
            'price' => (float) $row['price'],
        ], $normalised);
    }

    /**
     * Create the addon records for a service that has just been ordered.
     *
     * They start pending for the same reason the service does: nothing has been
     * paid yet. Activation happens when the invoice is settled.
     *
     * @param  array<int, array{addon_id: int, name: string, price: float}>  $cartPayload
     * @return array<int, ServiceAddon>
     */
    public function attachToService(Service $service, array $cartPayload, ?Order $order = null): array
    {
        $created = [];

        foreach ($cartPayload as $row) {
            if (empty($row['addon_id'])) {
                continue;
            }

            $created[] = ServiceAddon::create([
                'order_id' => $order?->id,
                'service_id' => $service->id,
                'addon_id' => (int) $row['addon_id'],
                'client_id' => $service->client_id,
                'server_id' => $service->server_id,
                'qty' => 1,
                'amount' => (float) ($row['price'] ?? 0),
                'billing_cycle' => $service->billing_cycle ?: 'Monthly',
                'next_due_date' => $service->next_due_date,
                'status' => 'pending',
            ]);
        }

        return $created;
    }

    /**
     * Sell an addon to a service that is already running, which is the case
     * addons exist for. The addon waits on its own invoice.
     *
     * @return array{addon: ServiceAddon, invoice: Invoice}
     *
     * @throws ValidationException
     */
    public function purchaseForService(Service $service, ProductAddon $addon, ?string $cycle = null): array
    {
        $service->loadMissing('product', 'client');

        if (! $service->product || ! $addon->appliesTo($service->product) || $addon->hidden || $addon->retired) {
            throw ValidationException::withMessages(['addon_id' => __('client.cart.addon_invalid')]);
        }

        // Selling an extra for an account that has ended raises an invoice for
        // something the customer can never use.
        if (in_array(strtolower((string) $service->status), ['terminated', 'cancelled', 'fraud'], true)) {
            throw ValidationException::withMessages(['addon_id' => __('client.services.addon_service_ended')]);
        }

        $cycle = $cycle ?: ($service->billing_cycle ?: 'Monthly');

        // The same rule as the basket: a term the addon was withdrawn from is
        // not a term it is given away on.
        if (! $addon->offeredOn($cycle)) {
            throw ValidationException::withMessages([
                'addon_id' => __('client.cart.addon_not_on_cycle', ['addon' => $addon->name]),
            ]);
        }

        $price = $addon->priceFor($cycle);

        $serviceAddon = ServiceAddon::create([
            'service_id' => $service->id,
            'addon_id' => $addon->id,
            'client_id' => $service->client_id,
            'server_id' => $service->server_id,
            'qty' => 1,
            'amount' => $price,
            'billing_cycle' => $cycle,
            // Set when the invoice is paid, so an unpaid addon is never billed
            // a second time by the renewal generator.
            'next_due_date' => null,
            'status' => 'pending',
        ]);

        $invoice = app(InvoiceService::class)->createInvoice($service->client, [
            [
                'type' => 'Addon',
                'rel_id' => $serviceAddon->id,
                'description' => $this->describe($serviceAddon, $addon->name),
                'amount' => $price,
                'taxed' => (bool) ($addon->tax ?? true),
            ],
        ], ['notes' => 'Addon purchase.']);

        return ['addon' => $serviceAddon, 'invoice' => $invoice];
    }

    /**
     * Addons that are due by the cutoff and are not already sitting on an
     * unpaid invoice. Both invoice generators use this, so "what is due" is
     * decided in one place.
     */
    public function dueQuery(Carbon $cutoff)
    {
        return ServiceAddon::with('client', 'service', 'addon')
            ->billable()
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', $cutoff)
            ->whereHas('client')
            // A suspended service is still owed for — suspension is usually
            // non-payment and paying is what lifts it. A terminated or
            // cancelled one is not, and neither is one still pending: an addon
            // is marked active when the order is accepted even if the service
            // failed to provision or is waiting on manual acceptance, so
            // billing it charged the customer for an extra on an account that
            // was never set up. The service query beside this one has always
            // billed active services only.
            ->whereHas('service', fn ($q) => $q
                ->whereNotIn('status', ['pending', 'terminated', 'cancelled', 'fraud'])
                // An addon renews with the service it belongs to.
                ->where('auto_renew', true)
                // And it stops with it: an extra on a service the customer has
                // asked to end is part of what they asked to end.
                ->whereDoesntHave('cancellationRequest', fn ($c) => $c->whereNull('processed_at')))
            ->whereDoesntHave('client.invoices', fn ($q) => $q
                ->whereNotIn('status', InvoiceStatus::settled())
                ->whereHas('items', fn ($i) => $i->where('type', 'Addon')->whereColumn('rel_id', 'service_addons.id')));
    }

    /**
     * The invoice line for one addon, in the shape InvoiceService expects.
     *
     * @return array<string, mixed>
     */
    public function lineItem(ServiceAddon $serviceAddon): array
    {
        return [
            'type' => 'Addon',
            'rel_id' => $serviceAddon->id,
            'description' => $this->describe($serviceAddon),
            'amount' => (float) $serviceAddon->amount,
            'taxed' => (bool) ($serviceAddon->addon->tax ?? true),
            'due_date' => $serviceAddon->next_due_date?->toDateString(),
        ];
    }

    /** Stop billing an addon without touching the service it belongs to. */
    public function cancel(ServiceAddon $serviceAddon): void
    {
        $serviceAddon->update(['status' => 'cancelled', 'next_due_date' => null]);
    }

    /**
     * Change the billed price, cycle, next due date or notes of an addon.
     *
     * The stored amount is what the next invoice line uses; past invoices keep
     * their own copy. A pending addon is waiting on an invoice that was already
     * written, so changing its price or cycle would make the invoice and the
     * addon disagree - cancel it and order again, or pay first.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDetails(ServiceAddon $serviceAddon, array $data): void
    {
        if ($serviceAddon->isEnded()) {
            throw ValidationException::withMessages([
                'addon' => __('admin.messages.service_addon_ended'),
            ]);
        }

        $amount = array_key_exists('amount', $data) ? (float) $data['amount'] : (float) $serviceAddon->amount;
        $cycle = array_key_exists('billing_cycle', $data) ? (string) $data['billing_cycle'] : (string) $serviceAddon->billing_cycle;

        if ($serviceAddon->isPending()
            && ((float) $serviceAddon->amount !== $amount || (string) $serviceAddon->billing_cycle !== $cycle)) {
            throw ValidationException::withMessages([
                'addon' => __('admin.messages.service_addon_pending_locked'),
            ]);
        }

        $updates = [];
        if (array_key_exists('amount', $data)) {
            $updates['amount'] = $amount;
        }
        if (array_key_exists('billing_cycle', $data)) {
            $updates['billing_cycle'] = $cycle;
        }
        if (array_key_exists('next_due_date', $data)) {
            $updates['next_due_date'] = $data['next_due_date'] ?: null;
        }
        if (array_key_exists('notes', $data)) {
            $updates['notes'] = $data['notes'];
        }

        if ($updates !== []) {
            $serviceAddon->update($updates);
        }
    }

    /** Stop billing an addon without touching the service; the record stays. */
    public function suspend(ServiceAddon $serviceAddon): void
    {
        if ($serviceAddon->isSuspended()) {
            return;
        }

        if (! $serviceAddon->isActive()) {
            throw ValidationException::withMessages([
                'addon' => __('admin.messages.service_addon_cannot_suspend'),
            ]);
        }

        // next_due_date is kept: resume() picks it up again, so a suspension
        // does not silently push the renewal date forward.
        $serviceAddon->update(['status' => 'suspended']);
    }

    /** Start billing a suspended addon again. */
    public function resume(ServiceAddon $serviceAddon): void
    {
        if (! $serviceAddon->isSuspended()) {
            throw ValidationException::withMessages([
                'addon' => __('admin.messages.service_addon_not_suspended'),
            ]);
        }

        $next = $serviceAddon->next_due_date
            ?: BillingCycleHelper::advance(now(), (string) ($serviceAddon->billing_cycle ?: 'Monthly'));

        $serviceAddon->update(['status' => 'active', 'next_due_date' => $next]);
    }

    /**
     * Remove an addon that never reached an invoice. An addon with invoice
     * lines is cancelled instead: the historical document must keep pointing
     * at a row that still exists.
     */
    public function delete(ServiceAddon $serviceAddon): void
    {
        if ($serviceAddon->hasInvoiceLines()) {
            throw ValidationException::withMessages([
                'addon' => __('admin.messages.service_addon_has_invoices'),
            ]);
        }

        $serviceAddon->delete();
    }

    /** Invoice line wording, kept in one place so renewals and sales match. */
    public function describe(ServiceAddon $serviceAddon, ?string $name = null): string
    {
        $name = $name ?: $serviceAddon->label();
        $domain = $serviceAddon->service?->domain;
        $cycle = ucfirst(strtolower((string) ($serviceAddon->billing_cycle ?: 'Monthly')));

        return trim("Addon: {$name} ({$cycle})".($domain ? " — {$domain}" : ''));
    }
}
