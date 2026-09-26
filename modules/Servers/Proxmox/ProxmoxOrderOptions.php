<?php

namespace Modules\Servers\Proxmox;

use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionLink;
use App\Models\ConfigOptionSub;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The order options a Proxmox product sells: operating system, memory, cores
 * and disk the customer picks at checkout.
 *
 * They are ordinary configurable options, named "key|Title" with choices
 * "value|Label", which ProxmoxPlan reads when it builds the server. This class
 * only saves the operator from typing that convention by hand: it builds the
 * group from a short form on the product page and reads it back for display.
 */
final class ProxmoxOrderOptions
{
    /** The keys the product page can build, in the order they are shown. */
    public const KEYS = ['os', 'memory', 'cores', 'disk'];

    /** Months each billing period covers, for pricing longer periods from the monthly price. */
    private const MONTHS = ['monthly' => 1, 'quarterly' => 3, 'semiannually' => 6, 'annually' => 12, 'biennially' => 24, 'triennially' => 36];

    /**
     * The module options already linked to the product.
     *
     * @return array<int, array{key: string, title: string, group: string, group_id: int, choices: array<int, array{value: string, label: string, monthly: float}>}>
     */
    public static function linked(Product $product): array
    {
        $out = [];
        $groups = $product->configOptionGroups()->with('options.subs.pricing')->get();
        foreach ($groups as $group) {
            foreach ($group->options as $option) {
                $key = strtolower(trim(explode('|', (string) $option->option_name, 2)[0]));
                if (! in_array($key, ProxmoxPlan::OPTION_KEYS, true)) {
                    continue;
                }
                $out[] = [
                    'key' => $key,
                    'title' => $option->displayName(),
                    'group' => (string) $group->name,
                    'group_id' => (int) $group->id,
                    'choices' => $option->subs->sortBy('sort_order')->map(fn (ConfigOptionSub $sub) => [
                        'value' => trim(explode('|', (string) $sub->option_name, 2)[0]),
                        'label' => $sub->displayName(),
                        'monthly' => $sub->priceFor('monthly'),
                    ])->values()->all(),
                ];
            }
        }

        return $out;
    }

    /**
     * Build the options from the product page's form and link them to the product.
     *
     * $spec: ['memory' => ['title' => 'Memory', 'choices' => [['value' => 2048, 'label' => '', 'price' => 5]]], ...]
     *
     * @throws ValidationException
     */
    public static function create(Product $product, array $spec): ConfigOptionGroup
    {
        $spec = array_intersect_key($spec, array_flip(self::KEYS));
        if ($spec === []) {
            throw ValidationException::withMessages(['options' => __('proxmox.product.opt_none_chosen')]);
        }

        foreach (self::linked($product) as $existing) {
            if (isset($spec[$existing['key']])) {
                throw ValidationException::withMessages(['options' => __('proxmox.product.opt_taken', [
                    'option' => $existing['title'], 'group' => $existing['group'],
                ])]);
            }
        }

        $plan = ProxmoxPlan::forService(new \App\Models\Service, self::productConfig($product));
        $images = collect($plan->reinstallChoices())->keyBy('id');
        // Template ids are numeric strings; as array keys PHP turns them into ints.
        $imageIds = $images->keys()->map(fn ($id) => (string) $id)->all();

        $rows = [];
        foreach (self::KEYS as $key) {
            if (! isset($spec[$key])) {
                continue;
            }
            $choices = [];
            foreach ((array) ($spec[$key]['choices'] ?? []) as $choice) {
                $value = trim((string) ($choice['value'] ?? ''));
                if (! self::validValue($key, $value, $imageIds)) {
                    throw ValidationException::withMessages(['options' => __('proxmox.product.opt_bad_value', [
                        'option' => self::defaultTitle($key), 'value' => $value,
                    ])]);
                }
                if (isset($choices[$value])) {
                    continue;
                }
                $label = trim((string) ($choice['label'] ?? ''));
                $choices[$value] = [
                    'label' => mb_substr($label !== '' ? $label : self::defaultLabel($key, $value, $images->get($value)['name'] ?? null), 0, 80),
                    'price' => round(max(0, min(100000, (float) ($choice['price'] ?? 0))), 2),
                ];
            }
            if ($choices === []) {
                throw ValidationException::withMessages(['options' => __('proxmox.product.opt_no_choices', ['option' => self::defaultTitle($key)])]);
            }
            $title = trim((string) ($spec[$key]['title'] ?? ''));
            $rows[$key] = ['title' => mb_substr($title !== '' ? $title : self::defaultTitle($key), 0, 80), 'choices' => $choices];
        }

        return DB::transaction(function () use ($product, $rows) {
            $group = ConfigOptionGroup::create([
                'name' => mb_substr(__('proxmox.product.opt_group_name', ['product' => $product->name]), 0, 190),
                'description' => __('proxmox.product.opt_group_description'),
            ]);
            // Prices are kept in the currency the shop sells in, as elsewhere.
            $currencyId = (Currency::getDefault() ?? Currency::query()->orderBy('id')->first())?->id;

            $sort = 0;
            foreach ($rows as $key => $row) {
                $option = ConfigOption::create([
                    'group_id' => $group->id,
                    'option_name' => "{$key}|{$row['title']}",
                    'option_type' => 'dropdown',
                    'sort_order' => $sort++,
                ]);
                $subSort = 0;
                foreach ($row['choices'] as $value => $choice) {
                    $sub = ConfigOptionSub::create([
                        'config_id' => $option->id,
                        'option_name' => "{$value}|{$choice['label']}",
                        'sort_order' => $subSort++,
                    ]);
                    if ($currencyId === null) {
                        continue;
                    }
                    Pricing::updateOrCreate(
                        ['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $sub->id, 'currency_id' => $currencyId],
                        array_map(fn (int $months) => round($choice['price'] * $months, 2), self::MONTHS),
                    );
                }
            }

            ConfigOptionLink::create(['group_id' => $group->id, 'product_id' => $product->id]);

            return $group;
        });
    }

    public static function defaultTitle(string $key): string
    {
        return __('proxmox.product.opt_'.$key);
    }

    /** A name for a choice the operator left unnamed: "4 GB", "2 cores", "80 GB". */
    public static function defaultLabel(string $key, string $value, ?string $imageName = null): string
    {
        return match ($key) {
            'os' => $imageName ?: ProxmoxPlan::imageName($value),
            'memory' => (int) $value % 1024 === 0
                ? ((int) $value / 1024).' GB'
                : ((int) $value >= 1024 ? rtrim(rtrim(number_format((int) $value / 1024, 2, '.', ''), '0'), '.').' GB' : (int) $value.' MB'),
            'cores' => trans_choice('proxmox.product.opt_cores_label', (int) $value, ['n' => (int) $value]),
            'disk' => (int) $value.' GB',
            default => $value,
        };
    }

    private static function validValue(string $key, string $value, array $images): bool
    {
        return match ($key) {
            'os' => in_array($value, $images, true),
            'memory' => ctype_digit($value) && (int) $value >= 64 && (int) $value <= 4194304,
            'cores' => ctype_digit($value) && (int) $value >= 1 && (int) $value <= 512,
            'disk' => ctype_digit($value) && (int) $value >= 1 && (int) $value <= 1048576,
            default => false,
        };
    }

    private static function productConfig(Product $product): array
    {
        $raw = $product->config_options;
        $cfg = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($cfg) ? $cfg : [];
    }
}
