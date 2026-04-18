<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxRule extends Model {
    use HasFactory;

    protected $fillable = ["level", "name", "state", "country", "tax_rate"];
    protected function casts(): array { return ["tax_rate" => "decimal:5"]; }
}
