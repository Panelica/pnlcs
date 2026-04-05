<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleItem extends Model
{
    protected $table = "bundle_items";

    protected $fillable = ["bundle_id", "item_type", "item_id", "qty"];

    public function bundle()
    {
        return $this->belongsTo(ProductBundle::class, "bundle_id");
    }

    public function product()
    {
        return $this->belongsTo(Product::class, "item_id");
    }
}
