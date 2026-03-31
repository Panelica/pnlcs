<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KbCategory extends Model {
    use HasFactory;

    protected $fillable = ["parent_id", "name", "description", "hidden", "sort_order"];
    protected function casts(): array { return ["hidden" => "boolean"]; }
    public function articles() { return $this->hasMany(KbArticle::class, "category_id"); }
    public function children() { return $this->hasMany(KbCategory::class, "parent_id"); }
}
