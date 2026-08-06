<?php

namespace App\Services;

use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionSub;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceConfigOption;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns a customer's configurable-option choices into money and, once the order
 * is placed, into rows against the service.
 *
 * The admin side could already define option groups, but nothing on the
 * customer side rendered them and the cart ignored their prices entirely, so an
 * option cost nothing no matter what the operator configured.
 */
class ConfigOptionService
{
    /**
     * The option groups a product offers, with everything needed to render and
     * price them.
     *
     * @return Collection<int, ConfigOptionGroup>
     */
    public function groupsFor(Product $product)
    {
        return $product->configOptionGroups()
            ->with(['options' => fn ($q) => $q->where('hidden', false)->orderBy('sort_order'),
                'options.subs' => fn ($q) => $q->where('hidden', false)->orderBy('sort_order'),
                'options.subs.pricing'])
            ->get();
    }

    /**
     * Validate the raw selection against the product's own options and return a
     * normalised list. Anything not offered by this product is rejected rather
     * than silently priced at zero.
     *
     * Raw shape: [configOptionId => subOptionId] for choice types,
     *            [configOptionId => qty] for quantity types.
     *
     * @return array<int, array{option: ConfigOption, sub_id: ?int, qty: int, unit_price: float}>
     *
     * @throws ValidationException
     */
    public function normalise(Product $product, array $raw, string $cycle): array
    {
        $selected = [];

        foreach ($this->groupsFor($product) as $group) {
            foreach ($group->options as $option) {
                $value = $raw[$option->id] ?? null;

                if ($option->isQuantity()) {
                    $qty = (int) ($value ?? 0);
                    $min = (int) ($option->qty_minimum ?? 0);
                    $max = (int) ($option->qty_maximum ?? 0);

                    if ($qty === 0 && $min === 0) {
                        continue;
                    }
                    if ($qty < $min || ($max > 0 && $qty > $max)) {
                        throw ValidationException::withMessages([
                            "config_options.{$option->id}" => __('client.cart.option_quantity_invalid', [
                                'option' => $option->option_name, 'min' => $min, 'max' => $max ?: '∞',
                            ]),
                        ]);
                    }

                    $sub = $option->subs->first();
                    $this->refuseIfWithdrawn($option, $sub, $cycle);

                    $selected[] = [
                        'option' => $option,
                        'sub_id' => $sub?->id,
                        'qty' => $qty,
                        'unit_price' => $sub ? $sub->priceFor($cycle) : 0.0,
                    ];

                    continue;
                }

                if ($option->isCheckbox()) {
                    if (empty($value)) {
                        continue;
                    }
                    $sub = $option->subs->first();
                    $this->refuseIfWithdrawn($option, $sub, $cycle);

                    $selected[] = [
                        'option' => $option,
                        'sub_id' => $sub?->id,
                        'qty' => 1,
                        'unit_price' => $sub ? $sub->priceFor($cycle) : 0.0,
                    ];

                    continue;
                }

                // dropdown / radio: one of the offered sub-options.
                if ($value === null || $value === '') {
                    if ($option->subs->isEmpty()) {
                        continue;
                    }
                    throw ValidationException::withMessages([
                        "config_options.{$option->id}" => __('client.cart.option_required', ['option' => $option->option_name]),
                    ]);
                }

                $sub = $option->subs->firstWhere('id', (int) $value);
                if (! $sub) {
                    throw ValidationException::withMessages([
                        "config_options.{$option->id}" => __('client.cart.option_invalid', ['option' => $option->option_name]),
                    ]);
                }

                $this->refuseIfWithdrawn($option, $sub, $cycle);

                $selected[] = [
                    'option' => $option,
                    'sub_id' => $sub->id,
                    'qty' => 1,
                    'unit_price' => $sub->priceFor($cycle),
                ];
            }
        }

        return $selected;
    }

    /**
     * r125-refuse: an option the operator withdrew from this cycle.
     *
     * Selling it for nothing is not withdrawing it. The product itself has
     * always been careful about this - a cycle it is not sold on is skipped
     * rather than billed at zero - and its options were not.
     *
     * @throws ValidationException
     */
    private function refuseIfWithdrawn(ConfigOption $option, ?ConfigOptionSub $sub, string $cycle): void
    {
        if ($sub && ! $sub->offeredOn($cycle)) {
            throw ValidationException::withMessages([
                "config_options.{$option->id}" => __('client.cart.option_not_on_cycle', [
                    'option' => $option->option_name,
                ]),
            ]);
        }
    }

    /** What the chosen options add to the recurring price. */
    public function priceOf(array $normalised): float
    {
        $total = 0.0;
        foreach ($normalised as $row) {
            $total += (float) $row['unit_price'] * max(1, (int) $row['qty']);
        }

        return round($total, 2);
    }

    /**
     * Cart-storable form: plain arrays only, since the cart is JSON.
     *
     * @return array<int, array{option_id: int, sub_id: ?int, qty: int, unit_price: float, label: string}>
     */
    public function toCartPayload(array $normalised): array
    {
        return array_map(function (array $row) {
            /** @var ConfigOption $option */
            $option = $row['option'];
            $sub = $row['sub_id'] ? $option->subs->firstWhere('id', $row['sub_id']) : null;
            $label = $sub ? "{$option->option_name}: {$sub->option_name}" : $option->option_name;
            if ($row['qty'] > 1) {
                $label .= " x{$row['qty']}";
            }

            return [
                'option_id' => $option->id,
                'sub_id' => $row['sub_id'],
                'qty' => (int) $row['qty'],
                'unit_price' => (float) $row['unit_price'],
                'label' => $label,
            ];
        }, $normalised);
    }

    /** Persist the cart payload against a freshly created service. */
    public function attachToService(Service $service, array $cartPayload): void
    {
        foreach ($cartPayload as $row) {
            if (empty($row['option_id'])) {
                continue;
            }
            ServiceConfigOption::create([
                'service_id' => $service->id,
                // WHMCS column names from the original schema: config_id is the
                // option, option_id is the chosen sub-option.
                'config_id' => $row['option_id'],
                'option_id' => $row['sub_id'] ?? null,
                'qty' => max(1, (int) ($row['qty'] ?? 1)),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
            ]);
        }
    }

    /** One-line summary for invoice descriptions. */
    public function summarise(array $cartPayload): string
    {
        $labels = array_filter(array_column($cartPayload, 'label'));

        return $labels ? implode(', ', $labels) : '';
    }
}
