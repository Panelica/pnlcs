<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a product costs for a term of months no billing cycle is named after.
 *
 * The six named cycles live in their own columns on `pricing`; this is for the
 * rest - seven months, say - so a term Central sells can be sold here too.
 */
class ProductTerm extends Model
{
    protected $fillable = ['product_id', 'currency_id', 'months', 'price', 'setup'];

    protected $casts = ['months' => 'integer', 'price' => 'float', 'setup' => 'float'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
