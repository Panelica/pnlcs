<?php

namespace App\Services\Updates\FileSets;

use RuntimeException;

/**
 * An extracted release package. Its manifest (.pnlcs-release.json, written by
 * tools/release/build-package.sh) lists every file with its fingerprint; the
 * package itself is signed, so the manifest is what the release contains.
 */
final class PackageFileSet implements FileSet
{
    public const MANIFEST = '.pnlcs-release.json';

    /** @var array<string, mixed> */
    private array $manifest;

    public function __construct(private readonly string $root)
    {
        $file = $root.'/'.self::MANIFEST;
        $manifest = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (! is_array($manifest) || ! is_array($manifest['files'] ?? null) || ! isset($manifest['version'])) {
            throw new RuntimeException("No release manifest in {$root}.");
        }

        $this->manifest = $manifest;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function version(): string
    {
        return (string) $this->manifest['version'];
    }

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        return $this->manifest;
    }

    public function entries(): array
    {
        return $this->manifest['files'];
    }

    public function read(string $path): ?string
    {
        if (! isset($this->manifest['files'][$path])) {
            return null;
        }

        $absolute = $this->root.'/'.$path;

        return is_link($absolute) ? (string) readlink($absolute) : (string) file_get_contents($absolute);
    }

    /**
     * Paths whose extracted content does not match the manifest. A package is
     * verified as a whole by its signature; this catches an extraction that
     * went wrong (a full disk, a tar that stopped half way).
     *
     * @return array<int, string>
     */
    public function mismatches(): array
    {
        $bad = [];

        foreach ($this->manifest['files'] as $path => $fingerprint) {
            if (Fingerprint::ofPath($this->root.'/'.$path) !== $fingerprint) {
                $bad[] = $path;
            }
        }

        return $bad;
    }
}
