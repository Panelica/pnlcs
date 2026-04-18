<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'code', 'name', 'native_name', 'flag_code',
        'direction', 'is_active', 'is_default',
        'sort_order', 'translation_progress',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'translation_progress' => 'decimal:2',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    public static function getActiveLanguages(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)->orderBy('sort_order')->get();
    }
}
