<?php

namespace App\Services;

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

    public function calculateProration(Service $service, Product $newProduct): array
    {
        $cycle = $service->billing_cycle ?: 'Monthly';
        $column = BillingCycleHelper::pricingColumn($cycle);
        $pricing = $newProduct->relationLoaded('pricing')
            ? $newProduct->pricing->first()
            : $newProduct->pricing()->first();

        if (! $column || ! $pricing || $pricing->{$column} === null) {
            return ['available' => false];
        }

        // What the service will actually renew at: the new package plus the
        // options it still offers. The current amount already includes options,
        // so comparing it against a bare base price understates the difference.
        $newRecurring = round((float) $pricing->{$column} + $this->optionsAfterChange($service, $newProduct)['total'], 2);
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

        $column = BillingCycleHelper::pricingColumn($service->billing_cycle ?: 'Monthly');
        $pricing = $newProduct->pricing->first();
        $newRecurring = ($column && $pricing && $pricing->{$column} !== null)
            ? (float) $pricing->{$column}
            : (float) $service->amount;

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
