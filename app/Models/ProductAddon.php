<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAddon extends Model
{
    protected $table = "product_addons";

    protected $fillable = ["name", "description", "packages", "hidden", "retired", "sort_order", "tax"];

    protected function casts(): array
    {
        return [
            "hidden" => "boolean",
            "retired" => "boolean",
            "tax" => "boolean",
        ];
    }

    public function pricing()
    {
        return $this->hasMany(Pricing::class, "rel_id")->where("type", "addon");
    }
}
