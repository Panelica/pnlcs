<?php

namespace App\Models;

use App\Services\BillingCycleHelper;
use Illuminate\Database\Eloquent\Model;

class ConfigOptionSub extends Model
{
    protected $fillable = ['config_id', 'option_name', 'sort_order', 'hidden'];

    protected function casts(): array
    {
        return ['hidden' => 'boolean'];
    }

    public function option()
    {
        return $this->belongsTo(ConfigOption::class, 'config_id');
    }

    /**
     * Sub-option prices live in the shared pricing table under
     * type = configoptions, the same shape products use.
     */
    public function pricing()
    {
        return $this->hasMany(Pricing::class, 'rel_id')->where('type', self::PRICING_TYPE);
    }

    public const PRICING_TYPE = 'configoptions';

    /** Recurring price for a billing cycle, in the currency being sold in. */
    public function priceFor(string $cycle): float
    {
        $currencyId = Currency::getDefault()?->id;

        $row = $this->relationLoaded('pricing')
            ? $this->pricing->firstWhere('currency_id', $currencyId) ?? $this->pricing->first()
            : $this->pricing()->where('currency_id', $currencyId)->first() ?? $this->pricing()->first();

        $column = BillingCycleHelper::pricingColumn($cycle);

        if (! $row || ! $column) {
            return 0.0;
        }

        $price = round((float) ($row->{$column} ?? 0), 2);

        // -1 marks a cycle the option is not offered on, not a discount.
        return $price > 0 ? $price : 0.0;
    }
}
