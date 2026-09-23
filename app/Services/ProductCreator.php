<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Create a catalogue product - the product screen and the API's addproduct
 * both come through here, so a product made either way has the same slug,
 * the same plan setting and a pricing row in every currency.
 */
class ProductCreator
{
    public const CYCLES = ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'];

    /** The rules the product screen has always applied. */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'group_id' => 'required|exists:product_groups,id',
            'type' => 'required|in:hosting,reseller,vps,ssl,other',
            'description' => 'nullable|string',
            'pay_type' => 'required|in:free,onetime,recurring',
            'auto_setup' => 'nullable|in:order,payment,manual',
            'server_type' => ['nullable', Rule::in(array_keys(app(ModuleRegistry::class)->serverModuleNames()))],
            'server_group_id' => 'nullable|exists:server_groups,id',
            // A price is a number, or -1 for "not sold on this cycle". Anything
            // else used to go straight to the database and come back as an error page.
            'pricing' => 'nullable|array',
            'pricing.*' => 'nullable|array',
            'pricing.*.*' => 'nullable|numeric|min:-1',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated  fields that passed rules()
     * @param  array<int|string, array<string, mixed>>  $pricing  currency id => cycle => price
     */
    public function create(array $validated, array $pricing = [], ?string $packageName = null): Product
    {
        unset($validated['pricing']);
        // Left empty, the column's own default applies; null is not a setup mode.
        if (array_key_exists('auto_setup', $validated) && $validated['auto_setup'] === null) {
            unset($validated['auto_setup']);
        }
        $validated['slug'] = Str::slug($validated['name']);

        // The plan lives on the panel; the product records which one it sells.
        if ($packageName !== null && $packageName !== '') {
            $validated['config_options'] = ['package_name' => $packageName];
        }

        $product = Product::create($validated);

        // A row for every currency, -1 ("not sold") where no price was given.
        foreach (Currency::all() as $currency) {
            $row = ['type' => 'product', 'currency_id' => $currency->id, 'rel_id' => $product->id];
            foreach (self::CYCLES as $cycle) {
                // A price field that was sent keeps what was sent, empty
                // included, as the product screen always stored it.
                $row[$cycle] = is_array($pricing[$currency->id] ?? null) && array_key_exists($cycle, $pricing[$currency->id])
                    ? $pricing[$currency->id][$cycle]
                    : -1;
            }
            Pricing::create($row);
        }

        return $product;
    }
}
