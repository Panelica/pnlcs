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
    public function translations() { return $this->hasMany(KbCategoryTranslation::class); }

    /**
     * A copy to show in the given language (the current one by default): the
     * translated name and description when there are any, its own otherwise.
     * Loaded articles are carried over in the same language.
     */
    public function inLocale(?string $locale = null): static
    {
        $locale ??= app()->getLocale();
        $copy = clone $this;

        $t = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('language', $locale)
            : $this->translations()->where('language', $locale)->first();
        if ($t) {
            $copy->name = $t->name;
            $copy->description = $t->description;
        }

        if ($this->relationLoaded('articles')) {
            $copy->setRelation('articles', $this->articles->map(function (KbArticle $a) use ($locale, $copy) {
                $a = $a->inLocale($locale);
                $a->setRelation('category', $copy);

                return $a;
            }));
        }

        return $copy;
    }
}
