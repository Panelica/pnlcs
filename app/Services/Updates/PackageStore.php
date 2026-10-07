<?php

namespace App\Services\Updates;

use App\Services\Updates\FileSets\PackageFileSet;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads, verifies and unpacks release packages, and keeps the installed
 * one: the next update compares the operator's files against it.
 */
class PackageStore
{
    public function __construct(
        private readonly ReleaseVerifier $verifier,
        private readonly ?string $path = null,
    ) {}

    public function directory(): string
    {
        $dir = ($this->path ?? (string) config('updates.path')).'/packages';
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /**
     * The verified package of a release, downloaded unless a verified copy is
     * already here.
     *
     * @return array{file: string, statement: array<string, mixed>}
     */
    public function fetch(Release $release): array
    {
        $dir = $this->directory();
        $statementRaw = $this->download((string) $release->asset($release->statementName()));
        $signature = $this->download((string) $release->asset($release->statementName().'.sig'));
        $statement = $this->verifier->statement($statementRaw, $signature, $release);

        $file = "{$dir}/{$release->packageName()}";
        if (is_file($file)) {
            try {
                $this->verifier->package($file, $statement);

                return ['file' => $file, 'statement' => $statement];
            } catch (RuntimeException) {
                @unlink($file);
            }
        }

        $part = "{$file}.part";
        @unlink($part);
        $url = (string) $release->asset($release->packageName());

        if (str_starts_with($url, 'file://')) {
            if (! @copy(substr($url, 7), $part)) {
                throw new RuntimeException("Cannot read {$url}.");
            }
        } else {
            try {
                Http::connectTimeout(30)->timeout(1800)->sink($part)->get($url)->throw();
            } catch (\Throwable $e) {
                @unlink($part);

                throw new RuntimeException('Downloading the package failed: '.ReleaseIndex::describeFailure($e, $url), 0, $e);
            }
        }

        try {
            $this->verifier->package($part, $statement);
        } catch (RuntimeException $e) {
            @unlink($part);

            throw $e;
        }

        rename($part, $file);
        file_put_contents("{$file}.release.json", $statementRaw);

        return ['file' => $file, 'statement' => $statement];
    }

    /** A package kept from an earlier update, verified against its own statement. */
    public function local(string $version): ?string
    {
        $file = $this->directory()."/pnlcs-{$version}.tar.gz";
        $statement = is_file("{$file}.release.json") ? json_decode((string) file_get_contents("{$file}.release.json"), true) : null;

        if (! is_file($file) || ! is_array($statement)) {
            return null;
        }

        try {
            $this->verifier->package($file, $statement);
        } catch (RuntimeException) {
            return null;
        }

        return $file;
    }

    /** Unpacks a package into $destination and checks every file against its manifest. */
    public function extract(string $file, string $destination): PackageFileSet
    {
        if (! is_dir($destination)) {
            mkdir($destination, 0750, true);
        }

        $run = Shell::run(['tar', '-xzf', $file, '-C', $destination, '--no-same-owner'], null, null, 900);
        if ($run['code'] !== 0) {
            throw new RuntimeException('Unpacking failed: '.trim($run['err']));
        }

        $set = new PackageFileSet("{$destination}/pnlcs");
        $bad = $set->mismatches();
        if ($bad !== []) {
            throw new RuntimeException('Unpacked files do not match the manifest: '.implode(', ', array_slice($bad, 0, 5)));
        }

        return $set;
    }

    /** Keeps only the packages of the given versions. */
    public function prune(array $keepVersions): void
    {
        $keep = array_map(fn ($v) => "pnlcs-{$v}.tar.gz", $keepVersions);

        foreach (glob($this->directory().'/pnlcs-*.tar.gz') ?: [] as $file) {
            if (! in_array(basename($file), $keep, true)) {
                @unlink($file);
                @unlink("{$file}.release.json");
            }
        }
    }

    private function download(string $url): string
    {
        if (str_starts_with($url, 'file://')) {
            $content = @file_get_contents(substr($url, 7));
            if ($content === false) {
                throw new RuntimeException("Cannot read {$url}.");
            }

            return $content;
        }

        try {
            return Http::connectTimeout(30)->timeout(120)->get($url)->throw()->body();
        } catch (\Throwable $e) {
            throw new RuntimeException(ReleaseIndex::describeFailure($e, $url), 0, $e);
        }
    }
}
