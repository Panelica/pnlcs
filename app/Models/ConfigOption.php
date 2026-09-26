<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigOption extends Model
{
    protected $fillable = ['group_id', 'option_name', 'option_type', 'qty_minimum', 'qty_maximum', 'sort_order', 'hidden'];

    protected function casts(): array
    {
        return ['hidden' => 'boolean'];
    }

    public function group()
    {
        return $this->belongsTo(ConfigOptionGroup::class, 'group_id');
    }

    public function subs()
    {
        return $this->hasMany(ConfigOptionSub::class, 'config_id')->orderBy('sort_order');
    }

    /** Types where the customer picks one of the listed values. */
    public function isChoice(): bool
    {
        return in_array($this->option_type, ['dropdown', 'radio'], true);
    }

    public function isQuantity(): bool
    {
        return $this->option_type === 'quantity';
    }

    public function isCheckbox(): bool
    {
        return $this->option_type === 'checkbox';
    }

    /**
     * The name shown to customers. A module reads a "key|Label" name by its
     * key (memory|RAM, 2048|2 GB), the way WHMCS does; customers see only the
     * label after the bar.
     */
    public function displayName(): string
    {
        $parts = explode('|', (string) $this->option_name, 2);

        return isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : (string) $this->option_name;
    }
}
