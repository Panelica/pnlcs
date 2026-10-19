<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Announcement extends Model {
    use HasFactory;

    protected $fillable = ["title", "category", "announcement", "published"];
    protected function casts(): array { return ["published" => "boolean"]; }

    /** A category typed with stray spaces or left empty is stored as none. */
    public function setCategoryAttribute($value): void
    {
        $value = trim((string) $value);
        $this->attributes['category'] = $value === '' ? null : $value;
    }

    public function translations() { return $this->hasMany(AnnouncementTranslation::class); }

    /**
     * A copy to show in the given language (the current one by default): its
     * translation's title and text when it has one, its own otherwise. The
     * copy is for reading; nothing on it is saved.
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
            $copy->announcement = $t->announcement;
        }

        return $copy;
    }

    /** The categories published announcements use, for a filter. */
    public static function publishedCategories(): array
    {
        return static::where('published', true)->whereNotNull('category')
            ->distinct()->orderBy('category')->pluck('category')->all();
    }
}
