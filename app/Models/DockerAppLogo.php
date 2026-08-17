<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Our own image for a catalogue app, keyed by the panel's template slug.
 *
 * @see database/migrations/2026_08_17_170000_create_docker_app_logos_table.php
 */
class DockerAppLogo extends Model
{
    protected $fillable = ['slug', 'path', 'source'];

    /** Public URL of the stored image. */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * slug => public URL, for pages that render the catalogue.
     *
     * One query for the whole page: the catalogue is ~100 apps and asking per
     * card would be a hundred round trips to draw one grid.
     *
     * @return array<string, string>
     */
    public static function urlMap(): array
    {
        return static::query()->get(['slug', 'path'])
            ->mapWithKeys(fn (self $l) => [$l->slug => $l->url()])
            ->all();
    }
}
