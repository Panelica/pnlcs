<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KbArticle extends Model {
    use HasFactory;

    protected $fillable = ["category_id", "title", "article", "views", "useful", "votes", "private", "sort_order"];
    protected function casts(): array { return ["private" => "boolean"]; }
    public function category() { return $this->belongsTo(KbCategory::class, "category_id"); }
}
