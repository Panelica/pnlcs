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

    /** The categories published announcements use, for a filter. */
    public static function publishedCategories(): array
    {
        return static::where('published', true)->whereNotNull('category')
            ->distinct()->orderBy('category')->pluck('category')->all();
    }
}
