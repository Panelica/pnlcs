<?php

namespace App\Models;

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

    /** Recurring price for a billing cycle, in the default currency. */
    public function priceFor(string $cycle): float
    {
        $row = $this->relationLoaded('pricing')
            ? $this->pricing->first()
            : $this->pricing()->first();

        if (! $row) {
            return 0.0;
        }

        $cycle = strtolower($cycle);

        return isset($row->{$cycle}) ? round((float) $row->{$cycle}, 2) : 0.0;
    }
}
