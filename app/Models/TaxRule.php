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
    public static function defaultRate(): float
    {
        $rule = static::where('is_default', true)
            ->orderByRaw("CASE WHEN country = '' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        return $rule ? (float) $rule->tax_rate : 0.0;
    }
}
