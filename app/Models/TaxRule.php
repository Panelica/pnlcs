<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxRule extends Model {
    use HasFactory;

    protected $fillable = ["name", "state", "country", "tax_rate", "is_default"];
    protected function casts(): array { return ["tax_rate" => "decimal:5", "is_default" => "boolean"]; }

    /**
     * The global default rate (empty country), falling back to any default.
     */
    /**
     * Whether the shop charges tax at all (TaxEnabled; on unless switched off).
     * A shop that sells at one advertised price, or is not registered for VAT,
     * had to delete every rule to stop tax being added (#305).
     */
    public static function enabled(): bool
    {
        return (string) Setting::get('TaxEnabled', '1') !== '0';
    }

    /**
     * Whether the prices entered already include tax (TaxInclusivePrices). Then
     * the advertised price is what the customer pays, and the tax is the part
     * of it the rate accounts for: price × rate / (100 + rate).
     */
    public static function pricesIncludeTax(): bool
    {
        return (string) Setting::get('TaxInclusivePrices', '0') === '1';
    }

    /**
     * The tax on an amount at a rate, unrounded: added on top, or the part of
     * a price that includes it. Summed over lines and rounded once, as the
     * totals always were.
     */
    public static function taxShare(float $amount, float $rate, ?bool $inclusive = null): float
    {
        if ($rate <= 0) {
            return 0.0;
        }

        return ($inclusive ?? self::pricesIncludeTax()) ? $amount * $rate / (100 + $rate) : $amount * $rate / 100;
    }

    /** taxShare(), rounded. */
    public static function taxOn(float $amount, float $rate, ?bool $inclusive = null): float
    {
        return round(self::taxShare($amount, $rate, $inclusive), 2);
    }

    public static function defaultRate(): float
    {
        if (! self::enabled()) {
            return 0.0;
        }

        $rule = static::where('is_default', true)
            ->orderByRaw("CASE WHEN country = '' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        return $rule ? (float) $rule->tax_rate : 0.0;
    }
}
