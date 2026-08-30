<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxRule extends Model {
    use HasFactory;

    protected $fillable = ["name", "state", "country", "tax_rate", "is_default"];
    protected function casts(): array { return ["tax_rate" => "decimal:5", "is_default" => "boolean"]; }

    /**
     * The rate that applies when no country/state-specific rule matches.
     */
    public static function defaultRate(): float
    {
        return (float) (static::where('is_default', true)->value('tax_rate') ?? 0);
    }
}
