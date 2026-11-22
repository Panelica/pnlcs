<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
     * Put an addon on a service as a draft. No invoice is raised yet: the
     * operator can correct the price, cycle and issue date, then accept it.
     */
    public function draftForService(Service $service, ProductAddon $addon, ?string $cycle = null, ?Carbon $issueDate = null): ServiceAddon
    {
        $service->loadMissing('product', 'client');

        if (! $service->product || ! $addon->appliesTo($service->product) || $addon->hidden || $addon->retired) {
            throw ValidationException::withMessages(['addon_id' => __('client.cart.addon_invalid')]);
        }

        // Addons hang off a running service: adding one to an unpaid (pending),
        // suspended or ended account sells something that cannot work yet.
        $serviceStatus = strtolower((string) $service->status);
        if ($serviceStatus !== 'active') {
            throw ValidationException::withMessages([
                'addon_id' => in_array($serviceStatus, ['terminated', 'cancelled', 'fraud'], true)
                    ? __('client.services.addon_service_ended')
                    : __('client.services.addon_service_not_active'),
            ]);
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
        $date = $issueDate ?: now();

        return DB::transaction(function () use ($service, $addon, $cycle, $price, $date) {
            // The addon gets an order like a product does. No invoice yet: the
            // order is what "accept" later turns into one.
            $order = app(OrderService::class)->createAddonOrder(
                $service->client,
                $price,
                $date,
                'Addon: '.$addon->name.' — '.($service->domain ?: '#'.$service->id)
            );

            return ServiceAddon::create([
                'order_id' => $order->id,
                'service_id' => $service->id,
                'addon_id' => $addon->id,
                'client_id' => $service->client_id,
                'server_id' => $service->server_id,
                'qty' => 1,
                'amount' => $price,
                'billing_cycle' => $cycle,
                'next_due_date' => $date->toDateString(),
                'status' => 'draft',
            ]);
        });
    }

    /**
     * Sell an addon to a service that is already running, without the draft
     * step (the client's own purchase): the invoice goes out immediately.
     *
     * @return array{addon: ServiceAddon, invoice: Invoice}
     *
     * @throws ValidationException
     */
    public function purchaseForService(Service $service, ProductAddon $addon, ?string $cycle = null, ?Carbon $issueDate = null): array
    {
        $serviceAddon = $this->draftForService($service, $addon, $cycle, $issueDate);
        $invoice = $this->accept($serviceAddon);

        return ['addon' => $serviceAddon->fresh(), 'invoice' => $invoice];
    }

    /**
     * Accept a draft addon: issue its invoice on the planned date (default
     * today) and leave the addon pending until that invoice is paid.
     */
    public function accept(ServiceAddon $serviceAddon): Invoice
    {
        if (! $serviceAddon->isDraft()) {
            throw ValidationException::withMessages([
                'addon' => __('admin.messages.service_addon_not_draft'),
            ]);
        }

        $serviceAddon->loadMissing('client', 'service', 'addon', 'order');
        $issue = $serviceAddon->next_due_date ?: now();
        $order = $serviceAddon->order;

        if (! $order) {
            $order = app(OrderService::class)->createAddonOrder(
                $serviceAddon->client,
                (float) $serviceAddon->amount,
                $issue,
                'Addon: '.$serviceAddon->label().' — '.($serviceAddon->service?->domain ?: '#'.$serviceAddon->service_id)
            );
            $serviceAddon->update(['order_id' => $order->id]);
        }

        return DB::transaction(function () use ($serviceAddon, $order, $issue) {
            $invoice = app(InvoiceService::class)->createInvoice($serviceAddon->client, [
                $this->lineItem($serviceAddon->fresh()),
            ], [
                // Draft first: the order must know the invoice before the
                // InvoicePaid event (customer balance) can fire, otherwise the
                // order listener cannot find it and the addon stays pending.
                'status' => InvoiceStatus::Draft->value,
                'date' => $issue->toDateString(),
                'due_date' => $issue->copy()->addDays((int) Setting::get('InvoiceDueDays', 14))->toDateString(),
                'notes' => 'Addon order '.$order->order_num,
            ]);

            $order->update([
                'invoice_id' => $invoice->id,
                'amount' => (float) $serviceAddon->amount,
                'date' => $issue->toDateString(),
            ]);

            $serviceAddon->update([
                'status' => 'pending',
                'next_due_date' => $issue->toDateString(),
            ]);

            return app(InvoiceService::class)->publishDraft($invoice);
        });
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
     * Active and suspended addons keep their price for the next invoice; the
     * invoices already issued are not rewritten. A pending addon is still on
     * its open purchase invoice, so its lines are updated with it: otherwise
     * the customer would pay one figure and the addon would bill another next
     * time. Paid/cancelled documents are never touched.
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

        if ($serviceAddon->isPending() && $serviceAddon->hasBlockingInvoiceLines()) {
            throw ValidationException::withMessages([
                'addon' => __('admin.messages.service_addon_pending_locked'),
            ]);
        }

        $amount = array_key_exists('amount', $data) ? (float) $data['amount'] : (float) $serviceAddon->amount;
        $cycle = array_key_exists('billing_cycle', $data) ? (string) $data['billing_cycle'] : (string) $serviceAddon->billing_cycle;
        $priceChanged = (float) $serviceAddon->amount !== $amount || (string) $serviceAddon->billing_cycle !== $cycle;

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

        if ($updates === []) {
            return;
        }

        DB::transaction(function () use ($serviceAddon, $updates, $priceChanged, $amount) {
            $serviceAddon->update($updates);

            if ($serviceAddon->order
                && in_array(strtolower((string) $serviceAddon->status), ['draft', 'pending'], true)
                && ! $serviceAddon->order->services()->exists()
                && ! $serviceAddon->order->domains()->exists()) {
                $orderFields = [
                    'amount' => (float) $serviceAddon->amount,
                    'notes' => 'Addon: '.$serviceAddon->label().' — '.($serviceAddon->service?->domain ?: '#'.$serviceAddon->service_id),
                ];

                if ($serviceAddon->isDraft()) {
                    $orderFields['date'] = ($serviceAddon->next_due_date ?: now())->toDateString();
                }

                $serviceAddon->order->update($orderFields);
            }

            if (! $serviceAddon->isPending() || ! $priceChanged) {
                return;
            }

            // The purchase invoice is still open: keep its addon lines in step
            // with the price/cycle the operator just set.
            foreach ($serviceAddon->invoiceLines()->with('invoice')->get() as $line) {
                $status = strtolower((string) ($line->invoice?->status));

                if (! in_array($status, ['unpaid', 'overdue', 'draft'], true)) {
                    continue;
                }

                $line->update([
                    'amount' => $amount,
                    'description' => $this->describe($serviceAddon->fresh()),
                ]);

                if ($line->invoice) {
                    app(InvoiceService::class)->recalculateTotals($line->invoice->fresh());
                }
            }
        });
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
        if ($serviceAddon->hasBlockingInvoiceLines()) {
            throw ValidationException::withMessages([
                'addon' => __('admin.messages.service_addon_has_invoices'),
            ]);
        }

        $order = $serviceAddon->order;
        $standaloneOrder = $order !== null
            && ! $order->services()->exists()
            && ! $order->domains()->exists();

        if (! $serviceAddon->hasInvoiceLines()) {
            DB::transaction(function () use ($serviceAddon, $order, $standaloneOrder) {
                $serviceAddon->delete();

                // A draft order that never produced an invoice goes with it.
                if ($standaloneOrder) {
                    $order->delete();
                }
            });

            return;
        }

        // The lines are on open (unpaid/overdue/draft) invoices. Removing the
        // addon removes those lines; an invoice that was only this addon is
        // cancelled so an empty document is not left behind.
        DB::transaction(function () use ($serviceAddon, $order, $standaloneOrder) {
            $lines = $serviceAddon->invoiceLines()->with('invoice')->get();
            $invoiceIds = $lines->pluck('invoice_id')->unique();

            foreach ($lines as $line) {
                $line->delete();
            }

            foreach ($invoiceIds as $invoiceId) {
                $invoice = Invoice::find($invoiceId);
                if (! $invoice) {
                    continue;
                }

                app(InvoiceService::class)->recalculateTotals($invoice);

                if ($invoice->items()->count() === 0) {
                    $invoice->update(['status' => InvoiceStatus::Cancelled->value]);
                }
            }

            if ($standaloneOrder) {
                $order->update(['status' => OrderStatus::Cancelled->value]);
            }

            $serviceAddon->delete();
        });
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
