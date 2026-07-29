<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['type', 'group_id', 'name', 'slug', 'description', 'hidden', 'show_domain_options', 'is_featured', 'retired', 'pay_type', 'auto_setup', 'server_type', 'server_group_id', 'stock_control', 'stock_qty', 'welcome_email_template', 'sort_order', 'config_options', 'tax', 'ssl_module', 'overage_enabled', 'overage_disk_rate', 'overage_bw_rate'];

    protected function casts(): array
    {
        return ['hidden' => 'boolean', 'is_featured' => 'boolean', 'retired' => 'boolean', 'stock_control' => 'boolean', 'tax' => 'boolean', 'overage_enabled' => 'boolean', 'overage_disk_rate' => 'decimal:4', 'overage_bw_rate' => 'decimal:4', 'config_options' => 'array'];
    }

    public function group()
    {
        return $this->belongsTo(ProductGroup::class, 'group_id');
    }

    /** Whether the shelf is empty. A product without stock control never is. */
    public function outOfStock(): bool
    {
        return (bool) $this->stock_control && (int) $this->stock_qty <= 0;
    }

    public function serverGroup()
    {
        return $this->belongsTo(ServerGroup::class, 'server_group_id');
    }

    public function pricing()
    {
        return $this->hasMany(Pricing::class, 'rel_id')->where('type', 'product');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'product_id');
    }

    /** Configurable option groups linked to this product. */
    public function configOptionGroups()
    {
        return $this->belongsToMany(ConfigOptionGroup::class, 'config_option_links', 'product_id', 'group_id');
    }

    public function scopeActive($q)
    {
        return $q->where('hidden', false)->where('retired', false);
    }
}
