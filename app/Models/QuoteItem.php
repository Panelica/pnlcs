<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuoteItem extends Model {
    use HasFactory;

    protected $table = "quote_items";
    protected $fillable = ["quote_id", "description", "quantity", "unit_price", "discount", "taxable", "sort_order"];

    public function quote() { return $this->belongsTo(Quote::class); }
}
