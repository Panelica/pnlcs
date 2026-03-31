<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model {
    use HasFactory;
    protected $fillable = ["code", "prefix", "suffix", "format", "rate", "is_default"];
    protected function casts(): array { return ["rate" => "decimal:5", "is_default" => "boolean"]; }

    public static function getDefault(): ?self { return static::where("is_default", true)->first(); }

    public function formatAmount(float $amount): string {
        return $this->prefix . number_format($amount, 2) . $this->suffix;
    }
}
