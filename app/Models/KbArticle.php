<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class KbArticle extends Model {
    protected $fillable = ["category_id", "title", "article", "views", "useful", "votes", "private", "sort_order"];
    protected function casts(): array { return ["private" => "boolean"]; }
    public function category() { return $this->belongsTo(KbCategory::class, "category_id"); }
}
