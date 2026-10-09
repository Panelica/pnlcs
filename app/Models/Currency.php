<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model {
    use HasFactory;
    protected $fillable = ["code", "prefix", "suffix", "format", "rate", "is_default"];
    protected function casts(): array { return ["rate" => "decimal:5", "is_default" => "boolean", "format" => "integer"]; }

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

    public static function getDefault(): ?self { return static::where("is_default", true)->first(); }

    public function formatAmount(float $amount): string {
        return $this->prefix . $this->number($amount) . $this->suffix;
    }
}
