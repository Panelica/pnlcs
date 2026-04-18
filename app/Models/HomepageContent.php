<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageContent extends Model
{
    protected $table = 'homepage_content';

    protected $fillable = ['section_slug', 'content_key', 'content_value', 'content_type'];

    public function section()
    {
        return $this->belongsTo(HomepageSection::class, 'slug', 'section_slug');
    }

    public static function getValue(string $sectionSlug, string $key, string $default = ''): string
    {
        $item = static::where('section_slug', $sectionSlug)
            ->where('content_key', $key)
            ->first();

        return $item ? ($item->content_value ?? $default) : $default;
    }
}
