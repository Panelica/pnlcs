<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How we present and sell one app from the panel's catalogue.
 *
 * The panel owns what an app is - image name, ports, resource needs, which
 * plans may install it. This owns the commercial side: whether we offer it,
 * where it sits, what it says on the card and what it looks like. A row is
 * created the moment an operator touches any of that; apps with no row are
 * offered with the panel's own name and description and a letter tile.
 */
class DockerApp extends Model
{
    protected $table = 'docker_apps';

    protected $fillable = ['slug', 'path', 'source', 'is_sellable', 'is_featured', 'sort_order', 'tagline'];

    protected $casts = [
        'is_sellable' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Where the browser should ask for this image.
     *
     * Deliberately a relative path rather than Storage::url(). That builds on
     * the configured app URL, which a container environment variable can
     * override - on our own install it resolves to the internal host and port,
     * so every image 404s from outside. A path relative to the site being
     * viewed is right wherever the panel is served from.
     */
    public function url(): ?string
    {
        return $this->path ? '/storage/'.ltrim($this->path, '/') : null;
    }

    /**
     * Logos that ship with the product, keyed by slug.
     *
     * A fresh install should not open on a wall of letter tiles waiting for
     * somebody to press "fetch logos", so a set is committed to the repository
     * and served straight from public/. An operator's own upload still wins.
     *
     * @return array<string, string>
     */
    public static function bundledUrlMap(): array
    {
        static $bundled = null;
        if ($bundled !== null) {
            return $bundled;
        }

        $manifest = public_path('img/apps/manifest.json');
        if (! is_file($manifest)) {
            return $bundled = [];
        }
        $rows = json_decode((string) file_get_contents($manifest), true);

        return $bundled = is_array($rows)
            ? array_map(fn ($file) => '/img/apps/'.$file, $rows)
            : [];
    }

    /**
     * slug => public URL, for pages that render the catalogue.
     *
     * One query for the whole page: the catalogue is ~100 apps and asking per
     * card would be a hundred round trips to draw one grid. Operator uploads
     * are layered over the logos that ship with the product.
     *
     * @return array<string, string>
     */
    public static function urlMap(): array
    {
        $own = static::query()->whereNotNull('path')->get(['slug', 'path'])
            ->mapWithKeys(fn (self $a) => [$a->slug => $a->url()])
            ->all();

        return $own + static::bundledUrlMap();
    }

    /**
     * slug => row, for pages that need more than the image.
     *
     * @return array<string, self>
     */
    public static function bySlug(): array
    {
        return static::query()->get()->keyBy('slug')->all();
    }

    /**
     * Decorate the panel's catalogue with our own presentation and drop what we
     * do not sell.
     *
     * Apps with no row of their own are kept: a fresh install has none, and
     * hiding the whole catalogue until someone fills in ninety-eight rows would
     * be a worse default than showing it.
     *
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<int, array<string, mixed>>
     */
    public static function decorate(array $templates, bool $sellableOnly = false): array
    {
        $rows = static::bySlug();

        $out = [];
        foreach ($templates as $t) {
            $row = $rows[$t['slug']] ?? null;
            if ($sellableOnly && $row && ! $row->is_sellable) {
                continue;
            }
            $t['logo_url_local'] = $row?->url() ?: (static::bundledUrlMap()[$t['slug']] ?? null);
            $t['is_featured'] = (bool) $row?->is_featured;
            $t['sort_order'] = (int) ($row?->sort_order ?? 0);
            $t['tagline'] = (string) ($row?->tagline ?? '');
            $t['is_popular'] = (bool) ($t['is_popular'] ?? false);
            $t['deploy_count'] = (int) ($t['deploy_count'] ?? 0);
            $out[] = $t;
        }

        // Featured first, then the operator's order, then how often an app has
        // actually been installed, then the panel's popular flag, then the name.
        //
        // The install count matters: the panel calls more than half its
        // catalogue popular, so on that flag alone the shop window opened on
        // AdGuard, Alpine and Apache - alphabetically first among fifty-three
        // equals - rather than on what customers actually ask for.
        usort($out, function ($a, $b) {
            return [$b['is_featured'], -$a['sort_order'], $b['deploy_count'], $b['is_popular'], mb_strtolower($a['name'])]
               <=> [$a['is_featured'], -$b['sort_order'], $a['deploy_count'], $a['is_popular'], mb_strtolower($b['name'])];
        });

        return $out;
    }
}
