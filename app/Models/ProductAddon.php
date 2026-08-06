<?php

namespace App\Models;

use App\Services\BillingCycleHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Something sold alongside a hosting package with its own price and its own
 * billing cycle: a dedicated IP, extra backup space, a control panel licence.
 *
 * Unlike a configurable option, an addon can be bought after the fact and
 * cancelled on its own while the service keeps running.
 */
class ProductAddon extends Model
{
    use HasFactory;

    public const PRICING_TYPE = 'addon';

    protected $table = 'product_addons';

    protected $fillable = ['name', 'description', 'packages', 'hidden', 'retired', 'sort_order', 'tax'];

    protected function casts(): array
    {
        return [
            'hidden' => 'boolean',
            'retired' => 'boolean',
            'tax' => 'boolean',
        ];
    }

    public function pricing()
    {
        return $this->hasMany(Pricing::class, 'rel_id')->where('type', self::PRICING_TYPE);
    }

    /** Addons a customer is allowed to see and order. */
    public function scopeAvailable($query)
    {
        return $query->where('hidden', false)->where('retired', false);
    }

    /**
     * Which products this addon is offered with. Stored the way WHMCS stores
     * it: a comma-separated list of product ids, empty meaning "any product".
     *
     * @return array<int, int>
     */
    public function packageIds(): array
    {
        $raw = trim((string) $this->packages);

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    public function appliesTo(Product $product): bool
    {
        $ids = $this->packageIds();

        return $ids === [] || in_array((int) $product->id, $ids, true);
    }

    /** Price for one billing cycle, 0 when the operator never priced it. */
    /**
     * Whether this addon is sold on the given cycle at all.
     *
     * r145-withdrawn: -1 marks a cycle the addon is not offered on. priceFor
     * reads that as zero, which does not withdraw the addon from the cycle -
     * it puts it on sale there for nothing. The same was true of configurable
     * options until they were made to refuse it; this is the addon beside them.
     */
    public function offeredOn(string $cycle): bool
    {
        return ($this->rawPriceFor($cycle) ?? 0.0) >= 0;
    }

    /** The stored figure for a cycle, or null when there is none. */
    private function rawPriceFor(string $cycle): ?float
    {
        $column = BillingCycleHelper::pricingColumn($cycle);

        if (! $column) {
            return null;
        }

        $currencyId = Currency::getDefault()?->id;

        $row = $this->relationLoaded('pricing')
            ? $this->pricing->firstWhere('currency_id', $currencyId) ?? $this->pricing->first()
            : $this->pricing()->where('currency_id', $currencyId)->first() ?? $this->pricing()->first();

        if (! $row || $row->{$column} === null) {
            return null;
        }

        return round((float) $row->{$column}, 2);
    }

    public function priceFor(string $cycle): float
    {
        $column = BillingCycleHelper::pricingColumn($cycle);

        if (! $column) {
            return 0.0;
        }

        $currencyId = Currency::getDefault()?->id;

        $row = $this->relationLoaded('pricing')
            ? $this->pricing->firstWhere('currency_id', $currencyId) ?? $this->pricing->first()
            : $this->pricing()->where('currency_id', $currencyId)->first() ?? $this->pricing()->first();

        $price = round((float) ($row->{$column} ?? 0), 2);

        // -1 marks a cycle the addon is not offered on, not a discount.
        return $price > 0 ? $price : 0.0;
    }
}
