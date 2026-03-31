<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model {
    use HasFactory;
    protected $fillable = ["name", "slug", "headline", "tagline", "order_form_template", "hidden", "sort_order"];
    protected function casts(): array { return ["hidden" => "boolean"]; }

    public function products() { return $this->hasMany(Product::class, "group_id"); }
}
