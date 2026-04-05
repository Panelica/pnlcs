<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model {
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;
    protected $fillable = ["type", "group_id", "name", "slug", "description", "hidden", "show_domain_options", "is_featured", "retired", "pay_type", "auto_setup", "server_type", "server_group_id", "stock_control", "stock_qty", "welcome_email_template", "sort_order", "config_options", "tax", "ssl_module"];
    protected function casts(): array { return ["hidden" => "boolean", "is_featured" => "boolean", "retired" => "boolean", "stock_control" => "boolean", "tax" => "boolean", "config_options" => "array"]; }

    public function group() { return $this->belongsTo(ProductGroup::class, "group_id"); }
    public function serverGroup() { return $this->belongsTo(ServerGroup::class, "server_group_id"); }
    public function pricing() { return $this->hasMany(Pricing::class, "rel_id")->where("type", "product"); }
    public function services() { return $this->hasMany(Service::class, "product_id"); }

    public function scopeActive($q) { return $q->where("hidden", false)->where("retired", false); }
}
