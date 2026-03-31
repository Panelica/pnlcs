<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model {
    protected $table = "quote_items";
    protected $fillable = ["quote_id", "description", "quantity", "unit_price", "discount", "taxable", "sort_order"];

    public function quote() { return $this->belongsTo(Quote::class); }
}
