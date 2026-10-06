<?php

namespace App\Services\Updates\FileSets;

final class Fingerprint
{
    /** The fingerprint of a path on disk, or null when nothing is there. */
    public static function ofPath(string $absolute): ?string
    {
        if (is_link($absolute)) {
            return 'link:'.readlink($absolute);
        }

        if (is_file($absolute)) {
            return hash_file('sha256', $absolute) ?: null;
        }

        return null;
    }

    public static function ofContent(string $content): string
    {
        return hash('sha256', $content);
    }

    public static function isLink(string $fingerprint): bool
    {
        return str_starts_with($fingerprint, 'link:');
    }
}
