<?php

namespace App\Services\Updates;

use App\Services\Updates\FileSets\FileSet;
use App\Services\Updates\FileSets\Fingerprint;
use App\Services\Updates\FileSets\PackageFileSet;

/**
 * Decides, file by file, what an update does: compares the version that is
 * installed (base), what is on disk now (the operator's files) and the new
 * version.
 *
 * - A file nobody changed takes the new version.
 * - A file only the operator changed stays as the operator left it.
 * - A file both changed is merged; when the changes touch the same lines it
 *   is a conflict, and the update does not start until the operator decides.
 * - A file the new version drops is removed only when the operator never
 *   changed it.
 * - The new version never puts a file where the operator has one of their own,
 *   nor inside a directory the installed version did not ship (the operator's
 *   theme, module or hook): that is a conflict too.
 *
 * Only files the installed version shipped are ever changed or removed, and
 * the user space (UserSpace) is left out entirely, whatever a package holds.
 */
class UpdatePlanner
{
    public function __construct(private readonly ThreeWayMerge $merge) {}

    /**
     * @param  string  $root  the installation
     * @param  array<string, string>  $resolutions  path => UpdatePlan::TAKE_NEW, UpdatePlan::KEEP_MINE, or the resolved content
     */
    public function plan(FileSet $base, FileSet $new, string $root, array $resolutions = []): UpdatePlan
    {
        $plan = new UpdatePlan;
        $root = rtrim($root, '/');

        $baseEntries = $this->shipped($base->entries());
        $newEntries = $this->shipped($new->entries());

        $baseDirectories = [];
        foreach (array_keys($baseEntries) as $path) {
            for ($dir = dirname($path); $dir !== '.' && $dir !== ''; $dir = dirname($dir)) {
                $baseDirectories[$dir] = true;
            }
        }

        $reportedDirectories = [];
        $paths = array_keys($baseEntries + $newEntries);
        sort($paths);

        foreach ($paths as $path) {
            $b = $baseEntries[$path] ?? null;
            $n = $newEntries[$path] ?? null;
            $absolute = $root.'/'.$path;
            $o = Fingerprint::ofPath($absolute);
            $resolution = $resolutions[$path] ?? null;

            // A directory sits where the new version wants a file: nothing can
            // be done to it without deleting the operator's directory.
            if ($o === null && $n !== null && is_dir($absolute) && ! is_link($absolute)) {
                $plan->conflict($path, UpdatePlan::FILE_IN_THE_WAY);

                continue;
            }

            if ($b === null) {
                if ($n === null) {
                    continue;
                }

                // Inside a directory of the operator's own, every file of it
                // is theirs: one conflict for the directory, not one per file.
                $owner = $this->operatorDirectory($root, $path, $baseDirectories);
                if ($owner !== null) {
                    if (! isset($reportedDirectories[$owner])) {
                        $reportedDirectories[$owner] = true;
                        $plan->conflict($owner, UpdatePlan::OPERATOR_DIRECTORY);
                    }

                    continue;
                }

                if ($o === $n) {
                    continue;
                }

                if ($o === null) {
                    $plan->write($path, 'new');

                    continue;
                }

                $this->resolveOrConflict($plan, $path, UpdatePlan::FILE_IN_THE_WAY, $resolution, fn () => $plan->write($path, 'new'));

                continue;
            }

            if ($n === null) {
                if ($o === null) {
                    continue;
                }
                if ($o === $b) {
                    $plan->delete($path);

                    continue;
                }

                $this->resolveOrConflict($plan, $path, UpdatePlan::REMOVED_IN_NEW, $resolution, fn () => $plan->delete($path));

                continue;
            }

            if ($o === $b) {
                if ($n !== $b) {
                    $plan->write($path, 'new');
                }

                continue;
            }

            if ($o === null) {
                // Deleted by the operator. Left deleted, unless the new version
                // changed the file: then the operator has to say which.
                if ($n !== $b) {
                    $this->resolveOrConflict($plan, $path, UpdatePlan::DELETED_BY_OPERATOR, $resolution, fn () => $plan->write($path, 'new'));
                }

                continue;
            }

            if ($o === $n) {
                continue;
            }

            if ($n === $b) {
                $plan->kept[] = $path;

                continue;
            }

            // Both changed it.
            if ($resolution !== null) {
                $this->applyResolution($plan, $path, $resolution, fn () => $plan->write($path, 'new'));

                continue;
            }

            if (Fingerprint::isLink($b) || Fingerprint::isLink($n) || is_link($absolute)) {
                $plan->conflict($path, UpdatePlan::BOTH_CHANGED);

                continue;
            }

            $result = $this->merge->merge((string) $base->read($path), (string) file_get_contents($absolute), (string) $new->read($path));

            if ($result['clean']) {
                $plan->write($path, 'merged', $result['content']);
                $plan->merged[] = $path;
            } else {
                $plan->conflict($path, UpdatePlan::BOTH_CHANGED, $result['content']);
            }
        }

        foreach (UserSpace::GENERATED as $dir) {
            foreach (array_keys($new->entries()) as $path) {
                if (str_starts_with($path, $dir.'/')) {
                    $plan->replaceDirectories[] = $dir;

                    break;
                }
            }
        }

        return $plan;
    }

    /**
     * Files a version ships that an update may manage: not the user space,
     * not the generated directories (replaced whole), not the manifest.
     *
     * @param  array<string, string>  $entries
     * @return array<string, string>
     */
    private function shipped(array $entries): array
    {
        return array_filter(
            $entries,
            fn (string $path) => $path !== PackageFileSet::MANIFEST && ! UserSpace::isProtected($path) && ! UserSpace::isGenerated($path),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * The operator's own directory a new file would land in: the deepest
     * directory on the way to it that exists on disk but that the installed
     * version did not ship (a theme, a module of theirs).
     *
     * @param  array<string, bool>  $baseDirectories
     */
    private function operatorDirectory(string $root, string $path, array $baseDirectories): ?string
    {
        for ($dir = dirname($path); $dir !== '.' && $dir !== ''; $dir = dirname($dir)) {
            if (is_dir($root.'/'.$dir)) {
                return isset($baseDirectories[$dir]) ? null : $dir;
            }
        }

        return null;
    }

    private function resolveOrConflict(UpdatePlan $plan, string $path, string $kind, ?string $resolution, callable $takeNew): void
    {
        if ($resolution === null) {
            $plan->conflict($path, $kind);

            return;
        }

        $this->applyResolution($plan, $path, $resolution, $takeNew);
    }

    private function applyResolution(UpdatePlan $plan, string $path, string $resolution, callable $takeNew): void
    {
        if ($resolution === UpdatePlan::TAKE_NEW) {
            $takeNew();
        } elseif ($resolution !== UpdatePlan::KEEP_MINE) {
            // Content the operator merged by hand.
            $plan->write($path, 'resolved', $resolution);
        }

        $plan->resolved[] = ['path' => $path, 'resolution' => in_array($resolution, [UpdatePlan::TAKE_NEW, UpdatePlan::KEEP_MINE], true) ? $resolution : 'edited'];
    }
}
