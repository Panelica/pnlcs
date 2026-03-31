<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model {
    protected $fillable = ["level", "name", "state", "country", "tax_rate"];
    protected function casts(): array { return ["tax_rate" => "decimal:5"]; }
}
