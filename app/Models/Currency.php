<?php

namespace App\Models;

use App\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'prefix', 'suffix', 'format', 'rate', 'is_default'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:5', 'is_default' => 'boolean', 'format' => 'integer'];
    }

    /**
     * How an amount in this currency is written: the decimal mark, then what
     * groups the thousands. 1 is what every amount looked like before the
     * column was read, so a shop that never chose keeps it. A lira or a euro
     * price reads 1.234,56 in Turkey and most of Europe; 1,234.56 there is a
     * number the customer has to translate. Space-grouped amounts use a
     * no-break space so a price never breaks across two lines.
     */
    public const FORMATS = [
        1 => ['.', ','],
        2 => [',', '.'],
        3 => [',', "\u{00A0}"],
        4 => ['.', ''],
    ];

    /** The number alone, two decimals, in this currency's format. */
    public function number(float|int|string|null $amount): string
    {
        [$decimal, $thousands] = self::FORMATS[(int) $this->format] ?? self::FORMATS[1];

        return number_format((float) $amount, 2, $decimal, $thousands);
    }

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * How many units of the shop (default) currency one unit of $fromCode is
     * worth. Rates are stored against the default currency, whose own rate is
     * 1; a currency the site has no row for is asked of the live rate
     * providers instead. Null when nothing can produce a rate.
     */
    public static function rateToShop(string $fromCode): ?float
    {
        $shop = static::getDefault();
        if (! $shop) {
            return null;
        }

        $from = strtoupper(trim($fromCode));
        if ($from === '' || $from === strtoupper((string) $shop->code)) {
            return 1.0;
        }

        $currency = static::where('code', $from)->first();
        if ($currency && (float) $currency->rate > 0) {
            $base = (float) $shop->rate > 0 ? (float) $shop->rate : 1.0;

            return $base / (float) $currency->rate;
        }

        return app(ExchangeRateService::class)->rate($from, (string) $shop->code);
    }

    /** A foreign-currency amount in the shop's currency, or null when no rate exists. */
    public static function toShop(float $amount, string $fromCode): ?float
    {
        $rate = static::rateToShop($fromCode);

        return $rate === null ? null : $amount * $rate;
    }

    public function formatAmount(float $amount): string
    {
        return $this->prefix.$this->number($amount).$this->suffix;
    }
}
