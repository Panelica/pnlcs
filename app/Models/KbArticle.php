<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KbArticle extends Model {
    use HasFactory;

    protected $fillable = ["category_id", "title", "article", "views", "useful", "votes", "private", "sort_order"];
    protected function casts(): array { return ["private" => "boolean"]; }
    public function category() { return $this->belongsTo(KbCategory::class, "category_id"); }
    public function translations() { return $this->hasMany(KbArticleTranslation::class); }

    /**
     * A copy to show in the given language (the current one by default): its
     * translation's title and text when it has one, its own otherwise, and its
     * category the same way. The copy is for reading; nothing on it is saved.
     */
    public function inLocale(?string $locale = null): static
    {
        $locale ??= app()->getLocale();
        $copy = clone $this;

        $t = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('language', $locale)
            : $this->translations()->where('language', $locale)->first();
        if ($t) {
            $copy->title = $t->title;
            $copy->article = $t->article;
        }

        if ($this->relationLoaded('category') && $this->category) {
            $copy->setRelation('category', $this->category->inLocale($locale));
        }

        return $copy;
    }
}
