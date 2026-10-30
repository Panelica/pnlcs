<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceProduct extends Model {
    use HasFactory;

    protected $fillable = ["name", "price", "unit", "tax_rate", "tax_label", "tax_rule_id"];
    protected function casts(): array { return ["price" => "decimal:2", "tax_rate" => "decimal:2", "tax_rule_id" => "integer"]; }

    public function taxRule() { return $this->belongsTo(TaxRule::class); }
}
