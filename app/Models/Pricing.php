<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pricing extends Model {
    use HasFactory;

    protected $table = 'pricing';
    protected $fillable = ['type', 'currency_id', 'rel_id', 'monthly_setup', 'quarterly_setup', 'semiannually_setup', 'annually_setup', 'biennially_setup', 'triennially_setup', 'monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'];

    public function currency() { return $this->belongsTo(Currency::class); }
}
