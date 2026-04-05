<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBundle extends Model
{
    protected $table = "product_bundles";

    protected $fillable = ["name", "description", "discount_type", "discount_value", "is_active"];

    protected function casts(): array
    {
        return [
            "discount_value" => "decimal:2",
            "is_active" => "boolean",
        ];
    }

    public function items()
    {
        return $this->hasMany(BundleItem::class, "bundle_id");
    }
}
