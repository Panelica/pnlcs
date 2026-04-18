<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DynamicTranslation extends Model
{
    protected $fillable = [
        'language', 'group', 'key', 'value',
        'is_auto_translated', 'is_reviewed',
    ];

    protected function casts(): array
    {
        return [
            'is_auto_translated' => 'boolean',
            'is_reviewed' => 'boolean',
        ];
    }

    public function scopeForLocale($query, string $locale)
    {
        return $query->where('language', $locale);
    }

    public function scopeForGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeUntranslated($query)
    {
        return $query->whereNull('value')->orWhere('value', '');
    }

    public function scopeAutoTranslated($query)
    {
        return $query->where('is_auto_translated', true);
    }

    public function scopeReviewed($query)
    {
        return $query->where('is_reviewed', true);
    }
}
