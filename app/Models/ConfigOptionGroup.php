<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigOptionGroup extends Model
{
    protected $fillable = ["name", "description"];

    public function options()
    {
        return $this->hasMany(ConfigOption::class, "group_id")->orderBy("sort_order");
    }

    public function productLinks()
    {
        return $this->hasMany(ConfigOptionLink::class, "group_id");
    }
}
