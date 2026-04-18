<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigOptionLink extends Model
{
    protected $fillable = ["group_id", "product_id"];

    public function group()
    {
        return $this->belongsTo(ConfigOptionGroup::class, "group_id");
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
