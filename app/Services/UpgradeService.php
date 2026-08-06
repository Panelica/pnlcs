<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceConfigOption;
use App\Models\Upgrade;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Prorated product upgrade/downgrade. Mirrors WHMCS: the customer keeps the
 * same billing date; only the difference between the new and current recurring
 * price, prorated for the days left in the current cycle, is charged (upgrade)
 * or waived (downgrade — no automatic credit, matching the common default).
 */
class UpgradeService
{
    public function __construct(private ProvisioningService $provisioning) {}

    /**
     * @return array{available: bool, new_recurring?: float, current_recurring?: float,
     *               remaining_days?: int, cycle_days?: int, prorated_diff?: float}
     */
    /**
     * The configurable-option money that survives a move to another product,
     * and the rows that do not because the new product does not offer them.
     *
     * @return array{total: float, drop: array<int, int>}
     */
    private function optionsAfterChange(Service $service, Product $newProduct): array
    {
        $offered = app(ConfigOptionService::class)
            ->groupsFor($newProduct)
            ->flatMap(fn ($group) => $group->options->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $total = 0.0;
        $drop = [];

        foreach (ServiceConfigOption::where('service_id', $service->id)->get() as $chosen) {
            if (! in_array((int) $chosen->config_id, $offered, true)) {
                $drop[] = (int) $chosen->id;

                continue;
            }

            $total += (float) $chosen->unit_price * max(1, (int) $chosen->qty);
        }

        return ['total' => round($total, 2), 'drop' => $drop];
    }

    /**
     * Move a service onto another package, the way the client area does it.
     *
     * A positive prorated difference is invoiced and applied when that invoice
     * is paid; a downgrade or a like-for-like change is applied at once. The
     * API used to write product_id and nothing else, which left the customer
     * on a bigger plan at the old price with a server that had not been told.
     *
     * @return array{success: bool, message: ?string, upgrade: ?Upgrade, invoice: ?Invoice, applied: bool}
     */
    public function requestProductChange(Service $service, Product $newProduct): array
    {
        $refuse = fn (string $message) => [
            'success' => false, 'message' => $message,
            'upgrade' => null, 'invoice' => null, 'applied' => false,
        ];

        if (in_array(strtolower((string) $service->status), ['terminated', 'cancelled', 'fraud'], true)) {
            return $refuse(__('client.services.not_live_for_action'));
        }

        if ($newProduct->hidden || $newProduct->retired) {
            return $refuse(__('client.cart.product_unavailable'));
        }

        if ((int) $newProduct->id === (int) $service->product_id) {
            return $refuse(__('messages.error.already_on_this_product'));
        }

        // r119-pending: one move at a time. Nothing used to check, so a second
        // click - or a reload of the confirmation - raised a second upgrade and
        // a second prorated invoice for the same move. Both were payable, and
        // paying the second charged again for something already bought.
        $waiting = Upgrade::where('type', 'product')
            ->where('rel_id', $service->id)
            ->where('status', 'pending')
            ->get();

        foreach ($waiting as $pending) {
            // r119-stale: a move whose invoice was cancelled is not waiting for
            // anything, and must not lock the customer out of changing package
            // for good. Only an invoice they can still pay counts.
            if ($this->payableInvoiceFor($pending)) {
                return $refuse(__('messages.error.upgrade_already_pending'));
            }

            $pending->update(['status' => 'cancelled']);
        }

        $calc = $this->calculateProration($service, $newProduct);

        if (! ($calc['available'] ?? false)) {
            return $refuse(__('messages.error.upgrade_not_available_for_cycle'));
        }

        $service->loadMissing('product', 'client');
        $currentName = $service->product->name ?? 'Current plan';

        $upgrade = Upgrade::create([
            'client_id' => $service->client_id,
            'type' => 'product',
            'rel_id' => $service->id,
            'original_value' => $service->product_id,
            'new_value' => $newProduct->id,
            'amount' => $calc['prorated_diff'],
            'status' => 'pending',
        ]);

        if ($calc['prorated_diff'] > 0.009) {
            $invoice = app(InvoiceService::class)->createInvoice($service->client, [[
                'type' => 'Upgrade',
                'rel_id' => $upgrade->id,
                'description' => "Upgrade: {$currentName} -> {$newProduct->name} ({$service->billing_cycle}) - prorated for {$calc['remaining_days']} days - {$service->domain}",
                'amount' => $calc['prorated_diff'],
                'taxed' => $newProduct->tax ?? true,
            ]], ['notes' => 'Prorated product upgrade charge.']);

            return [
                'success' => true, 'message' => null,
                'upgrade' => $upgrade, 'invoice' => $invoice, 'applied' => false,
            ];
        }

        // A downgrade or a like-for-like change costs nothing, so it happens now.
        $this->apply($upgrade);

        return [
            'success' => true, 'message' => null,
            'upgrade' => $upgrade, 'invoice' => null, 'applied' => true,
        ];
    }

    /**
     * Whether the customer still has an invoice they can pay for this move.
     */
    private function payableInvoiceFor(Upgrade $upgrade): bool
    {
        return InvoiceItem::where('type', 'Upgrade')
            ->where('rel_id', $upgrade->id)
            ->whereHas('invoice', fn ($q) => $q->whereIn('status', ['draft', 'unpaid', 'overdue']))
            ->exists();
    }

    public function calculateProration(Service $service, Product $newProduct): array
    {
        $cycle = $service->billing_cycle ?: 'Monthly';
        $basePrice = $newProduct->priceFor($cycle);

        if ($basePrice === null) {
            return ['available' => false];
        }

        // What the service will actually renew at: the new package plus the
        // options it still offers. The current amount already includes options,
        // so comparing it against a bare base price understates the difference.
        $newRecurring = round($basePrice + $this->optionsAfterChange($service, $newProduct)['total'], 2);
        $currentRecurring = (float) $service->amount;
        $cycleDays = BillingCycleHelper::cycleDays($cycle);

        $due = $service->next_due_date
            ? Carbon::parse($service->next_due_date)->startOfDay()
            : now()->startOfDay();
        $remainingDays = max(0, (int) now()->startOfDay()->diffInDays($due, false));

        $factor = $cycleDays > 0 ? min(1.0, $remainingDays / $cycleDays) : 1.0;
        $proratedDiff = round(($newRecurring - $currentRecurring) * $factor, 2);

        return [
            'available' => true,
            'new_recurring' => $newRecurring,
            'current_recurring' => $currentRecurring,
            'remaining_days' => $remainingDays,
            'cycle_days' => $cycleDays,
            'prorated_diff' => $proratedDiff,
        ];
    }

    /**
     * Apply a pending upgrade: change the module package, repoint the service to
     * the new product and set its new recurring amount (billing date unchanged),
     * then mark the upgrade completed. Idempotent on already-completed upgrades.
     *
     * @return array{success: bool, module?: bool, message?: string}
     */
    public function apply(Upgrade $upgrade): array
    {
        if ($upgrade->status === 'completed') {
            return ['success' => true, 'message' => 'Already applied.'];
        }

        $service = Service::find($upgrade->rel_id);
        $newProduct = Product::with('pricing')->find($upgrade->new_value);

        if (! $service || ! $newProduct) {
            Log::error('UpgradeService::apply — service or product missing', [
                'upgrade' => $upgrade->id, 'service' => $upgrade->rel_id, 'product' => $upgrade->new_value,
            ]);

            return ['success' => false, 'message' => 'Service or product not found.'];
        }

        // Best-effort module package change; billing state is authoritative even
        // if the control panel call fails (the customer has paid).
        $moduleOk = false;
        try {
            $result = $this->provisioning->changePackage($service, $newProduct);
            $moduleOk = (bool) ($result['success'] ?? false);
            if (! $moduleOk) {
                Log::warning('UpgradeService::apply — changePackage did not succeed', [
                    'upgrade' => $upgrade->id, 'result' => $result,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('UpgradeService::apply — changePackage threw: '.$e->getMessage(), ['upgrade' => $upgrade->id]);
        }

        $newRecurring = $newProduct->priceFor($service->billing_cycle ?: 'Monthly')
            ?? (float) $service->amount;

        // Options the new product does not offer are dropped rather than left
        // attached to a package that cannot provide them.
        $options = $this->optionsAfterChange($service, $newProduct);

        if ($options['drop'] !== []) {
            ServiceConfigOption::whereIn('id', $options['drop'])->delete();
        }

        $service->update([
            'product_id' => $newProduct->id,
            'amount' => round($newRecurring + $options['total'], 2),
        ]);

        $upgrade->update(['status' => 'completed']);

        return ['success' => true, 'module' => $moduleOk];
    }
}
