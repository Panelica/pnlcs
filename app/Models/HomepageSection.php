<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    protected $fillable = ['slug', 'title', 'sort_order', 'is_enabled', 'config'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public function content()
    {
        return $this->hasMany(HomepageContent::class, 'section_slug', 'slug');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
