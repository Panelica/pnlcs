<?php

namespace App\Services\Updates\FileSets;

/**
 * The files of one version of PNLCS: every path with its fingerprint, and a
 * way to read any of them. A fingerprint is the sha256 of a file's content,
 * or "link:<target>" for a symbolic link, so that two versions of a path can
 * be compared without reading them.
 */
interface FileSet
{
    /** @return array<string, string> path => fingerprint */
    public function entries(): array;

    /** The content of a file, or the target of a link; null when the path is not in the set. */
    public function read(string $path): ?string;
}
