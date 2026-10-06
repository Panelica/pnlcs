<?php

namespace App\Services\Updates;

use App\Services\ThemeManager;
use App\Services\Updates\FileSets\FileSet;
use App\Services\Updates\FileSets\PackageFileSet;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Everything checked before an update touches anything: whether this server
 * can run the new version, whether the files can be written, whether the
 * operator's modules and theme say they work with it, and the file plan with
 * its conflicts. A report with a blocking issue means the update does not
 * start.
 *
 * Issues are codes with parameters, worded by the language files
 * (admin.updates.issue.*), so the admin area and the command line say the same.
 */
class Preflight
{
    /** @var array<int, array{code: string, params: array<string, mixed>}> */
    private array $blocking = [];

    /** @var array<int, array{code: string, params: array<string, mixed>}> */
    private array $warnings = [];

    /**
     * @param  array<string, mixed>  $statement  the verified release statement
     * @param  array<string, string>  $resolutions
     * @param  array<int, string>  $ignoreRequires  modules/themes the operator chose to update past
     * @return array<string, mixed>
     */
    public function run(
        Installation $installation,
        FileSet $base,
        PackageFileSet $new,
        array $statement,
        UpdatePlanner $planner,
        array $resolutions = [],
        bool $allowMajor = false,
        array $ignoreRequires = [],
    ): array {
        $this->blocking = [];
        $this->warnings = [];
        $root = $installation->root();
        $from = $installation->version();
        $to = Version::parse((string) $statement['version']);

        if ($from !== null && ! $to->greaterThan($from)) {
            $this->block('not_newer', ['from' => (string) $from, 'to' => (string) $to]);
        }
        if ($from !== null && $to->major > $from->major && ! $allowMajor) {
            $this->block('major_version', ['from' => (string) $from, 'to' => (string) $to]);
        }
        if ($installation->mode() === Installation::GIT) {
            $this->warn('git_install', ['commit' => substr((string) trim((string) @shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD 2>/dev/null')), 0, 12)]);
        }

        $this->requirements($statement['requires'] ?? [], (int) ($statement['size'] ?? 0), $root);
        $this->extensions($root, $to, $ignoreRequires);

        $plan = $planner->plan($base, $new, $root, $resolutions);

        if ($plan->hasConflicts()) {
            $this->block('conflicts', ['count' => count($plan->conflicts)]);
        }

        $this->writable($root, $plan);
        $this->themeOverrides($root, $base, $new);

        return [
            'ok' => $this->blocking === [],
            'from' => $from ? (string) $from : null,
            'to' => (string) $to,
            'mode' => $installation->mode(),
            'blocking' => $this->blocking,
            'warnings' => $this->warnings,
            'plan' => $plan->summary(),
            'conflicts' => $plan->conflicts,
            'checked_at' => now()->toIso8601String(),
            '_plan' => $plan,
        ];
    }

    /** @param array<string, mixed> $requires */
    private function requirements(array $requires, int $packageBytes, string $root): void
    {
        if (isset($requires['php']) && version_compare(PHP_VERSION, (string) $requires['php'], '<')) {
            $this->block('php_version', ['required' => $requires['php'], 'current' => PHP_VERSION]);
        }

        foreach ($requires['extensions'] ?? [] as $extension) {
            if (! extension_loaded($extension)) {
                $this->block('php_extension', ['name' => $extension]);
            }
        }

        // Inside the Docker image a release can need a newer image (a PHP
        // extension, a tool). Images before 1.5 do not say their version.
        if (isset($requires['runtime_image']) && $this->inContainer()) {
            $current = getenv('PNLCS_RUNTIME_VERSION') ?: '1.4';
            if (version_compare($current, (string) $requires['runtime_image'], '<')) {
                $this->block('runtime_image', ['required' => $requires['runtime_image'], 'current' => $current]);
            }
        }

        $finder = new ExecutableFinder;
        if ($finder->find('tar') === null) {
            $this->block('tool_missing', ['tool' => 'tar']);
        }
        if ($finder->find('git') === null) {
            $this->warn('no_git_merge');
        }

        if (function_exists('posix_geteuid')) {
            $owner = @fileowner($root.'/artisan');
            if ($owner !== false && posix_geteuid() !== $owner) {
                $ownerName = function_exists('posix_getpwuid') ? (posix_getpwuid($owner)['name'] ?? (string) $owner) : (string) $owner;
                $this->block('run_as_owner', ['owner' => $ownerName]);
            }
        }

        $packageMb = (int) ceil($packageBytes / 1048576);
        $databaseMb = $this->databaseMegabytes();
        // Unpacked new and base versions, the old vendor kept for rollback, and
        // the database snapshot: about four times the package, plus the data.
        $neededMb = max(300, $packageMb * 12 + $databaseMb);
        $freeMb = (int) floor((@disk_free_space($root) ?: 0) / 1048576);
        if ($freeMb < $neededMb) {
            $this->block('disk_space', ['needed_mb' => $neededMb, 'free_mb' => $freeMb]);
        }
    }

    private function extensions(string $root, Version $to, array $ignore): void
    {
        $manifests = [];
        foreach (glob($root.'/modules/*/*/pnlcs.json') ?: [] as $file) {
            $manifests[] = ['module', basename(dirname($file)), $file];
        }
        foreach (glob($root.'/themes/*/theme.json') ?: [] as $file) {
            $manifests[] = ['theme', basename(dirname($file)), $file];
        }

        foreach ($manifests as [$kind, $name, $file]) {
            $data = json_decode((string) @file_get_contents($file), true);
            $constraint = is_array($data) ? ($data['requires']['pnlcs'] ?? null) : null;

            if (is_string($constraint) && ! $to->satisfies($constraint) && ! in_array($name, $ignore, true)) {
                $this->block("{$kind}_requires", ['name' => $name, 'constraint' => $constraint, 'to' => (string) $to]);
            }
        }
    }

    private function writable(string $root, UpdatePlan $plan): void
    {
        $denied = [];
        $paths = array_merge(array_column($plan->actions, 'path'), $plan->replaceDirectories);

        foreach ($paths as $path) {
            $absolute = "{$root}/{$path}";
            $dir = dirname($absolute);
            while (! is_dir($dir) && $dir !== $root) {
                $dir = dirname($dir);
            }

            if (! is_writable($dir) || (file_exists($absolute) && ! is_dir($absolute) && ! is_writable($absolute))) {
                $denied[] = $path;
            }
        }

        if ($denied !== []) {
            $this->block('not_writable', ['paths' => array_slice($denied, 0, 20), 'count' => count($denied)]);
        }
    }

    /**
     * Views the active theme replaces whose original changed in this update:
     * the theme keeps showing its copy, without the change (and, if the view
     * now expects other data, it can break - the health check after the
     * update catches that and rolls back).
     */
    private function themeOverrides(string $root, FileSet $base, FileSet $new): void
    {
        try {
            $slug = app(ThemeManager::class)->getActiveSlug();
        } catch (\Throwable) {
            return;
        }

        $views = $root.'/themes/'.$slug.'/views';
        if ($slug === '' || ! is_dir($views)) {
            return;
        }

        $baseEntries = $base->entries();
        $newEntries = $new->entries();
        $changed = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($views, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $core = 'resources/views/'.substr($file->getPathname(), strlen($views) + 1);
            if (($baseEntries[$core] ?? null) !== ($newEntries[$core] ?? null)) {
                $changed[] = $core;
            }
        }

        if ($changed !== []) {
            sort($changed);
            $this->warn('theme_overrides_changed', ['theme' => $slug, 'views' => $changed]);
        }
    }

    private function databaseMegabytes(): int
    {
        try {
            $bytes = DB::selectOne('SELECT SUM(data_length + index_length) AS b FROM information_schema.tables WHERE table_schema = DATABASE()')->b ?? 0;

            return (int) ceil(((int) $bytes) / 1048576);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function inContainer(): bool
    {
        return getenv('PNLCS_DIR') !== false || is_file('/.dockerenv');
    }

    private function block(string $code, array $params = []): void
    {
        $this->blocking[] = ['code' => $code, 'params' => $params];
    }

    private function warn(string $code, array $params = []): void
    {
        $this->warnings[] = ['code' => $code, 'params' => $params];
    }
}
