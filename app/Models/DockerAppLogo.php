<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Our own image for a catalogue app, keyed by the panel's template slug.
 *
 * @see database/migrations/2026_08_17_170000_create_docker_app_logos_table.php
 */
class DockerAppLogo extends Model
{
    protected $fillable = ['slug', 'path', 'source'];

    /**
     * Where the browser should ask for this image.
     *
     * Deliberately a relative path rather than Storage::url(). That builds on
     * the configured app URL, which a container environment variable can
     * override - on our own install it resolves to the internal host and port,
     * so every image 404s from outside. A path relative to the site being
     * viewed is right wherever the panel is served from.
     */
    public function url(): string
    {
        return '/storage/'.ltrim($this->path, '/');
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
