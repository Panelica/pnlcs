<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigOptionSub extends Model
{
    protected $fillable = ["config_id", "option_name", "sort_order", "hidden"];

    protected function casts(): array
    {
        return ["hidden" => "boolean"];
    }

    public function option()
    {
        return $this->belongsTo(ConfigOption::class, "config_id");
    }
}
