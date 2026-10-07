<?php

namespace App\Services\Updates;

/**
 * The parts of an installation an update never writes to (RELEASING.md, "the
 * user space contract").
 *
 * Only the paths that belong to the installation whatever a release contains
 * are listed here. A theme, module or hook file of the operator's own is
 * protected by the planner instead: it only ever changes or removes a file
 * the installed version shipped, and stops when a new version would put a
 * file inside a directory the installed version did not ship.
 */
final class UserSpace
{
    /** Directories rebuilt whole by every update: their content is generated, not edited. */
    public const GENERATED = ['vendor', 'public/build'];

    private const PROTECTED_PREFIXES = [
        'storage/',
        'bootstrap/cache/',
        'public/storage',
        'node_modules/',
        '.git/',
    ];

    public static function isProtected(string $path): bool
    {
        $path = ltrim($path, '/');

        // .env and every variant of it the installation made (.env.backup,
        // .env.production). The examples are documentation and ship.
        if ($path === '.env' || (str_starts_with($path, '.env.') && ! str_ends_with($path, '.example'))) {
            return true;
        }

        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, rtrim($prefix, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    public static function isGenerated(string $path): bool
    {
        $path = ltrim($path, '/');

        foreach (self::GENERATED as $dir) {
            if ($path === $dir || str_starts_with($path, $dir.'/')) {
                return true;
            }
        }

        return false;
    }
}
