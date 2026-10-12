<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A slug made from a name that no other row of the model has yet.
 *
 * Products and product groups took Str::slug(name) as it came. A second product
 * called the same as another got the same slug, and the order page finds a
 * product by its slug, so it opened the first one: the second could never be
 * ordered, and when the first was hidden its link was a 404. A second group
 * with the same name hit the unique index on product_groups.slug.
 */
final class UniqueSlug
{
    /**
     * "vps-s", then "vps-s-2", "vps-s-3"… A name with nothing a slug can keep
     * (only CJK letters, say) starts from $fallback instead of an empty string.
     *
     * @param  class-string<Model>  $model
     */
    public static function for(string $model, string $name, string $fallback, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = $fallback;
        }

        $slug = $base;
        for ($n = 2; self::taken($model, $slug, $ignoreId); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /** @param  class-string<Model>  $model */
    private static function taken(string $model, string $slug, ?int $ignoreId): bool
    {
        return $model::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }
}
