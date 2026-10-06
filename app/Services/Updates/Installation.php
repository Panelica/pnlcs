<?php

namespace App\Services\Updates;

use App\Services\Updates\FileSets\FileSet;
use App\Services\Updates\FileSets\GitFileSet;
use App\Services\Updates\FileSets\PackageFileSet;
use RuntimeException;

/**
 * What is installed, and the exact files it was installed with.
 *
 * - Installed from a release package (or updated by this updater): the
 *   package's manifest sits at the root, and the package itself is kept in
 *   storage (or downloaded again and verified).
 * - Installed with `git clone`, as every installation was before releases:
 *   the commit checked out is the version the files are compared against. The
 *   first packaged update turns it into the first kind; .git is left where it
 *   is, because older Docker images re-clone a code volume that has none.
 */
class Installation
{
    public const PACKAGE = 'package';

    public const GIT = 'git';

    public const UNKNOWN = 'unknown';

    public function __construct(
        private readonly PackageStore $store,
        private readonly ReleaseIndex $index,
        private readonly ?string $root = null,
    ) {}

    public function root(): string
    {
        return rtrim($this->root ?? base_path(), '/');
    }

    public function mode(): string
    {
        if (is_file($this->root().'/'.PackageFileSet::MANIFEST)) {
            return self::PACKAGE;
        }

        return is_dir($this->root().'/.git') ? self::GIT : self::UNKNOWN;
    }

    /** @return array<string, mixed>|null */
    public function manifest(): ?array
    {
        $file = $this->root().'/'.PackageFileSet::MANIFEST;
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? $data : null;
    }

    public function version(): ?Version
    {
        $manifest = $this->manifest();

        return $manifest ? Version::parse((string) ($manifest['version'] ?? '')) : Version::installed($this->root());
    }

    /** The files of the installed version, unpacked into $workDir when they come from a package. */
    public function baseline(string $workDir): FileSet
    {
        return match ($this->mode()) {
            self::PACKAGE => $this->packageBaseline($workDir),
            self::GIT => new GitFileSet($this->root()),
            default => throw new RuntimeException('This installation was neither installed from a release package nor cloned with git, so there is no record of which files it was installed with.'),
        };
    }

    private function packageBaseline(string $workDir): FileSet
    {
        $version = (string) $this->version();
        $file = $this->store->local($version);

        if ($file === null) {
            $release = $this->index->find($version);
            if ($release === null) {
                throw new RuntimeException("The installed release {$version} is no longer published, and no copy of it is kept here.");
            }
            $file = $this->store->fetch($release)['file'];
        }

        return $this->store->extract($file, "{$workDir}/base");
    }
}
