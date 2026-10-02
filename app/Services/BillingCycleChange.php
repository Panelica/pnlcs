<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ConfigOptionSub;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceConfigOption;

/**
 * A customer moving a service to another billing cycle (monthly to annual,
 * for example).
 *
 * The change applies from the next renewal: the period already paid for is
 * left alone, the next due date stays where it is, and the invoice raised
 * then is for the new cycle at its price. Nothing is prorated, so nothing is
 * refunded or charged now. The new recurring amount is the product's price
 * for the cycle plus each chosen configurable option at its price for that
 * cycle - the same parts the amount was made of when the service was ordered.
 */
class BillingCycleChange
{
    /** Stored spelling of each cycle, as orders write it. */
    public const CYCLES = [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'semiannually' => 'Semi-Annually',
        'annually' => 'Annually',
        'biennially' => 'Biennially',
        'triennially' => 'Triennially',
    ];

    /**
     * The cycles this service can move to, with the new recurring amount.
     *
     * @return array<string, float> stored cycle => amount
     */
    public function options(Service $service): array
    {
        $service->loadMissing('product');
        $product = $service->product;
        if (! $product || strtolower((string) $product->pay_type) !== 'recurring') {
            return [];
        }

        $current = self::key((string) $service->billing_cycle);
        $out = [];
        foreach (self::CYCLES as $key => $stored) {
            if ($key === $current) {
                continue;
            }
            $price = $product->priceFor($stored);
            if ($price === null || $price <= 0) {
                continue;
            }
            $out[$stored] = round($price + $this->optionsTotal($service, $stored), 2);
        }

        return $out;
    }

    /** @return array{ok: bool, message: string} */
    public function change(Service $service, string $cycle): array
    {
        if (strtolower((string) $service->status) !== 'active') {
            return ['ok' => false, 'message' => __('client.services.cycle_not_available')];
        }

        $options = $this->options($service);
        if (! array_key_exists($cycle, $options)) {
            return ['ok' => false, 'message' => __('client.services.cycle_not_available')];
        }

        // A renewal invoice already raised for the old cycle would be paid at
        // the old price for the old length, and the next one at the new. The
        // customer pays (or we cancel) that one first.
        if ($this->openRenewalInvoice($service)) {
            return ['ok' => false, 'message' => __('client.services.cycle_invoice_open')];
        }

        foreach (ServiceConfigOption::where('service_id', $service->id)->get() as $chosen) {
            $sub = $chosen->option_id ? ConfigOptionSub::find($chosen->option_id) : null;
            if ($sub) {
                $chosen->update(['unit_price' => $sub->priceFor($cycle)]);
            }
        }

        $previous = (string) $service->billing_cycle;
        $service->update(['billing_cycle' => $cycle, 'amount' => $options[$cycle]]);

        ActivityLog::log("Billing cycle of service #{$service->id} changed from {$previous} to {$cycle} by the customer", auth()->user()?->email, $service->client_id);

        return ['ok' => true, 'message' => __('client.services.cycle_changed', ['cycle' => __('common.billing.'.self::langKey($cycle)), 'date' => $service->next_due_date?->format(date_fmt()) ?? '-'])];
    }

    /** monthly, quarterly, semiannually, ... from any stored spelling. */
    public static function key(string $cycle): string
    {
        $c = strtolower(str_replace(['-', ' ', '_'], '', trim($cycle)));

        return $c;
    }

    /** The common.billing.* label key for a cycle. */
    public static function langKey(string $cycle): string
    {
        return self::key($cycle) === 'semiannually' ? 'semi_annually' : self::key($cycle);
    }

    private function optionsTotal(Service $service, string $cycle): float
    {
        $total = 0.0;
        foreach (ServiceConfigOption::where('service_id', $service->id)->get() as $chosen) {
            $sub = $chosen->option_id ? ConfigOptionSub::find($chosen->option_id) : null;
            $unit = $sub ? $sub->priceFor($cycle) : (float) $chosen->unit_price;
            $total += $unit * max(1, (int) $chosen->qty);
        }

        return $total;
    }

    private function openRenewalInvoice(Service $service): bool
    {
        return Invoice::where('client_id', $service->client_id)->outstanding()
            ->whereHas('items', fn ($q) => $q->where('type', 'Hosting')->where('rel_id', $service->id))
            ->exists();
    }
}
