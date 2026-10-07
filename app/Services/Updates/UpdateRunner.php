<?php

namespace App\Services\Updates;

use App\Services\NotificationService;
use App\Services\Updates\FileSets\FileSet;
use App\Services\Updates\FileSets\PackageFileSet;
use App\Support\SqlDump;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Prepares and applies an update (RELEASING.md, "the four promises").
 *
 * prepare(): download, verify, unpack, and check everything - changes nothing.
 * apply():   the same checks, then, only when nothing blocks:
 *   1. maintenance mode (the scheduler and queue stop with it),
 *   2. a snapshot of the database,
 *   3. the files, by resources/updater/apply.php, step by step into a journal,
 *   4. migrations, addon upgrades, caches - by the new code, in new processes,
 *   5. a health check of the new version,
 *   6. back up; or, if 3-5 failed, every file and the database put back as they
 *      were, then back up on the old version.
 *
 * The process running this is the old version. After step 3 it only runs code
 * it loaded before (preloaded below) and starts new processes for the rest.
 */
class UpdateRunner
{
    /** @var callable|null */
    private $output = null;

    private ?string $runDir = null;

    public function __construct(
        private readonly Installation $installation,
        private readonly ReleaseIndex $index,
        private readonly PackageStore $packages,
        private readonly UpdatePlanner $planner,
        private readonly Preflight $preflight,
        private readonly DatabaseSnapshot $database,
        private readonly UpdateState $state,
    ) {}

    public function onOutput(callable $output): self
    {
        $this->output = $output;

        return $this;
    }

    public function state(): UpdateState
    {
        return $this->state;
    }

    /** The run that started and did not finish, if any. */
    public function unfinished(): ?array
    {
        $current = $this->state->read('current-run.json');

        return $current && ! in_array($current['phase'] ?? '', ['done', 'rolled_back', 'refused', 'failed_before_changes'], true) ? $current : null;
    }

    /**
     * @param  array<string, string>  $resolutions
     * @param  array<int, string>  $ignoreRequires
     * @return array<string, mixed> the preflight report
     */
    public function prepare(Release $release, array $resolutions = [], bool $allowMajor = false, array $ignoreRequires = []): array
    {
        $this->state->acquireLock();

        try {
            $id = 'check-'.now()->format('Ymd-His');
            $work = $this->state->path("work/{$id}");
            $this->status('preparing', 'download', ['version' => (string) $release->version]);

            [$base, $new, $statement] = $this->unpack($release, $work);

            $this->status('preparing', 'check', ['version' => (string) $release->version]);
            $report = $this->preflight->run($this->installation, $base, $new, $statement, $this->planner,
                $resolutions + $this->state->resolutions((string) $release->version), $allowMajor, $ignoreRequires);

            $this->saveConflictFiles($report, "preflight/{$release->version}");
            $report = $this->publicReport($report, $release);
            $this->state->write('report.json', $report);
            $this->status('ready', 'checked', ['version' => (string) $release->version, 'ok' => $report['ok']]);

            return $report;
        } catch (Throwable $e) {
            $this->status('error', 'check', ['message' => $e->getMessage()]);

            throw $e;
        } finally {
            $this->removeDirectory($this->state->path('work'));
            $this->state->releaseLock();
        }
    }

    /**
     * @param  array<string, string>  $resolutions
     * @param  array<int, string>  $ignoreRequires
     * @return array<string, mixed>
     */
    public function apply(Release $release, string $by, array $resolutions = [], bool $allowMajor = false, array $ignoreRequires = []): array
    {
        $this->state->acquireLock();

        try {
            if ($unfinished = $this->unfinished()) {
                throw new RuntimeException("The update run {$unfinished['id']} did not finish. Run `php artisan pnlcs:update-rollback` first.");
            }

            return $this->run($release, $by, $resolutions, $allowMajor, $ignoreRequires);
        } finally {
            $this->state->releaseLock();
        }
    }

    /**
     * Rolls back a run that stopped part way (the process was killed, the
     * server lost power). Safe to call more than once.
     */
    public function rollbackUnfinished(): array
    {
        $this->state->acquireLock();

        try {
            $run = $this->unfinished();
            if ($run === null) {
                return ['rolled_back' => false, 'message' => 'No unfinished update.'];
            }
            $this->runDir = $this->state->path("runs/{$run['id']}");
            $this->preload();
            $this->rollback($run);

            return ['rolled_back' => true, 'run' => $run['id']];
        } finally {
            $this->state->releaseLock();
        }
    }

    private function run(Release $release, string $by, array $resolutions, bool $allowMajor, array $ignoreRequires): array
    {
        $from = (string) ($this->installation->version() ?? 'unknown');
        $to = (string) $release->version;
        $id = now()->format('Ymd-His').'-'.$to;
        $this->runDir = $this->state->path("runs/{$id}");
        mkdir($this->runDir, 0750, true);

        $run = ['id' => $id, 'from' => $from, 'to' => $to, 'by' => $by, 'phase' => 'preparing', 'started_at' => now()->toIso8601String(),
            'root' => $this->installation->root(), 'config_cached' => is_file($this->installation->root().'/bootstrap/cache/config.php')];
        $this->saveRun($run);
        $this->log("Updating {$from} -> {$to}");

        try {
            $this->status('applying', 'download', ['version' => $to, 'run' => $id]);
            [$base, $new, $statement] = $this->unpack($release, $this->runDir);

            $this->status('applying', 'check', ['version' => $to, 'run' => $id]);
            $report = $this->preflight->run($this->installation, $base, $new, $statement, $this->planner,
                $resolutions + $this->state->resolutions($to), $allowMajor, $ignoreRequires);
            /** @var UpdatePlan $plan */
            $plan = $report['_plan'];
            if (! $report['ok']) {
                $this->saveConflictFiles($report, "preflight/{$to}");
            }
            $report = $this->publicReport($report, $release);
            $this->state->write("runs/{$id}/report.json", $report);

            if (! $report['ok']) {
                $this->state->write('report.json', $report);
                $run['phase'] = 'refused';
                $this->saveRun($run);
                $this->removeUnpacked();
                $this->finish($run, 'refused', 'The update did not start: '.implode(', ', array_column($report['blocking'], 'code')));

                return ['result' => 'refused', 'report' => $report];
            }

            $applierPlan = $this->writeApplierPlan($plan, $new, $id);
            $this->setAside($plan, $id);
        } catch (Throwable $e) {
            $run['phase'] = 'failed_before_changes';
            $run['error'] = $e->getMessage();
            $this->saveRun($run);
            $this->removeUnpacked();
            $this->finish($run, 'failed', $e->getMessage());

            throw $e;
        }

        $this->preload();

        try {
            $this->status('applying', 'maintenance', ['version' => $to, 'run' => $id]);
            $run['maintenance_secret'] = bin2hex(random_bytes(16));
            $run['phase'] = 'maintenance';
            $this->saveRun($run);
            $this->artisan(['down', '--retry=60', '--secret='.$run['maintenance_secret']]);

            $this->status('applying', 'database', ['version' => $to, 'run' => $id]);
            $this->database->take("{$this->runDir}/database.sql.gz");
            $run['phase'] = 'snapshot';
            $this->saveRun($run);

            $this->status('applying', 'files', ['version' => $to, 'run' => $id]);
            copy(resource_path('updater/apply.php'), "{$this->runDir}/apply.php");
            $run['phase'] = 'files';
            $this->saveRun($run);
            $this->php(["{$this->runDir}/apply.php", 'apply', $applierPlan], 900);

            $this->status('applying', 'migrate', ['version' => $to, 'run' => $id]);
            $run['phase'] = 'migrating';
            $this->saveRun($run);
            $this->artisan(['migrate', '--force', '--no-interaction'], 1800);
            $this->artisan(['pnlcs:addons-upgrade', '--no-interaction'], 900);

            $this->status('applying', 'caches', ['version' => $to, 'run' => $id]);
            $run['phase'] = 'caches';
            $this->saveRun($run);
            $this->rebuildCaches($run['config_cached']);

            $this->status('applying', 'health', ['version' => $to, 'run' => $id]);
            $run['phase'] = 'health';
            $this->saveRun($run);
            $this->artisan(['pnlcs:update-health'], 300);
        } catch (Throwable $e) {
            $run['error'] = $e->getMessage();
            $this->log('Failed: '.$e->getMessage());
            $this->rollback($run);

            return ['result' => 'rolled_back', 'error' => $e->getMessage(), 'report' => $report];
        }

        $this->artisan(['up']);
        $this->quietly(['queue:restart']);
        // PHP-FPM may keep the old compiled code for a while; the next page
        // the admin opens resets its cache (UpdateController::status).
        @touch($this->state->path('opcache-reset-pending'));

        $run['phase'] = 'done';
        $run['finished_at'] = now()->toIso8601String();
        $this->saveRun($run);
        $this->state->forget('report.json');
        $this->packages->prune(array_filter([$from, $to], fn ($v) => Version::parse($v) !== null));
        $this->removeUnpacked();
        $this->pruneRuns();
        $this->finish($run, 'updated', "Updated {$from} to {$to}.", $report);

        return ['result' => 'updated', 'report' => $report];
    }

    /**
     * Puts files and database back. Called with the old version's classes in
     * memory and, after the applier's rollback, the old version on disk.
     */
    private function rollback(array $run): void
    {
        $this->status('rolling_back', 'files', ['run' => $run['id']]);
        $phase = $run['phase'];
        $root = $run['root'];

        try {
            if (in_array($phase, ['files', 'migrating', 'caches', 'health', 'rolling_back'], true)) {
                $run['phase'] = 'rolling_back';
                $run['rollback_from'] ??= $phase;
                $this->saveRun($run);
                $this->php(["{$this->runDir}/apply.php", 'rollback', "{$this->runDir}/plan.json"], 900);
            }

            // Migrations may have run (part way) from the moment they started.
            if (in_array($run['rollback_from'] ?? $phase, ['migrating', 'caches', 'health'], true)) {
                $this->status('rolling_back', 'database', ['run' => $run['id']]);
                $this->database->restore("{$this->runDir}/database.sql.gz");
            }

            $this->rebuildCaches((bool) ($run['config_cached'] ?? false));

            if (is_file($root.'/storage/framework/down')) {
                $this->artisan(['up']);
            }
        } catch (Throwable $e) {
            // The site stays in maintenance: a half-restored site must not be
            // served. Everything needed to finish by hand is in the run.
            $run['phase'] = 'rollback_failed';
            $run['rollback_error'] = $e->getMessage();
            $this->saveRun($run);
            $this->finish($run, 'rollback_failed', 'Rolling back failed: '.$e->getMessage().'. The site is in maintenance. Run `php artisan pnlcs:update-rollback` again; the run directory is '.$this->runDir);

            throw $e;
        }

        $run['phase'] = 'rolled_back';
        $run['finished_at'] = now()->toIso8601String();
        $this->saveRun($run);
        $this->removeUnpacked();
        $this->finish($run, 'rolled_back', 'The update failed and was rolled back: '.($run['error'] ?? 'stopped part way').'. The site runs '.$run['from'].' as before.');
    }

    /**
     * Downloads, verifies and unpacks the release, and gets the installed
     * version's files to compare against.
     *
     * @return array{0: FileSet, 1: PackageFileSet, 2: array<string, mixed>}
     */
    private function unpack(Release $release, string $work): array
    {
        $fetched = $this->packages->fetch($release);
        $new = $this->packages->extract($fetched['file'], "{$work}/new");
        $base = $this->installation->baseline($work);

        return [$base, $new, $fetched['statement']];
    }

    private function writeApplierPlan(UpdatePlan $plan, PackageFileSet $new, string $id): string
    {
        $contentDir = "{$this->runDir}/content";
        @mkdir($contentDir, 0750, true);

        $actions = [];
        foreach ($plan->actions as $i => $action) {
            if ($action['type'] === 'write' && isset($action['content'])) {
                $file = "{$contentDir}/{$i}";
                file_put_contents($file, $action['content']);
                $actions[] = ['type' => 'write', 'path' => $action['path'], 'source' => 'file', 'from' => $file];
            } else {
                $actions[] = array_intersect_key($action, array_flip(['type', 'path', 'source']));
            }
        }

        // The manifest last: until every file is in place, the installation
        // still says it is the old version.
        $actions[] = ['type' => 'write', 'path' => PackageFileSet::MANIFEST, 'source' => 'new'];

        $keep = [];
        foreach (array_keys($new->entries()) as $path) {
            for ($dir = dirname($path); $dir !== '.' && $dir !== ''; $dir = dirname($dir)) {
                $keep[$dir] = true;
            }
        }

        $file = "{$this->runDir}/plan.json";
        file_put_contents($file, json_encode([
            'root' => $this->installation->root(),
            'run_dir' => $this->runDir,
            'new_root' => $new->root(),
            'actions' => $actions,
            'replace_directories' => $plan->replaceDirectories,
            'keep_directories' => array_keys($keep),
            'summary' => $plan->summary(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $file;
    }

    /**
     * The operator's version of every file where they chose the new one: kept
     * for good, outside the run, so nothing they wrote is ever lost.
     */
    private function setAside(UpdatePlan $plan, string $id): void
    {
        foreach ($plan->resolved as $resolved) {
            $source = $this->installation->root().'/'.$resolved['path'];
            if ($resolved['resolution'] !== UpdatePlan::KEEP_MINE && is_file($source)) {
                $target = $this->state->path("set-aside/{$id}/{$resolved['path']}");
                @mkdir(dirname($target), 0750, true);
                copy($source, $target);
            }
        }
    }

    /** The merged text of each conflict (with markers), for the operator to download and edit. */
    private function saveConflictFiles(array $report, string $dir): void
    {
        $this->removeDirectory($this->state->path($dir));
        foreach ($report['conflicts'] ?? [] as $conflict) {
            if (isset($conflict['merged'])) {
                $file = $this->state->path("{$dir}/{$conflict['path']}");
                @mkdir(dirname($file), 0750, true);
                file_put_contents($file, $conflict['merged']);
            }
        }
    }

    /** The report as it is stored and shown: the merged texts live in their own files. */
    private function publicReport(array $report, Release $release): array
    {
        unset($report['_plan']);
        $report['conflicts'] = array_map(fn ($c) => ['path' => $c['path'], 'kind' => $c['kind'], 'has_merged' => isset($c['merged'])], $report['conflicts'] ?? []);
        $report['release'] = $release->toArray();

        return $report;
    }

    private function rebuildCaches(bool $configWasCached): void
    {
        $this->artisan(['optimize:clear'], 300);
        if ($configWasCached) {
            $this->artisan(['optimize'], 300);
        }
        // Compiling every view finds a broken one (a theme's copy included)
        // before a visitor does.
        $this->artisan(['view:cache'], 300);
    }

    /** Classes the rollback path needs, loaded while the old files are still on disk. */
    private function preload(): void
    {
        foreach ([SqlDump::class, DatabaseSnapshot::class, NotificationService::class, Process::class, ProcessFailedException::class, Schema::class] as $class) {
            class_exists($class);
        }
        Schema::getConnection();
    }

    private function artisan(array $args, int $timeout = 120): void
    {
        $this->php(array_merge([$this->installation->root().'/artisan'], $args), $timeout);
    }

    private function quietly(array $args): void
    {
        try {
            $this->artisan($args);
        } catch (Throwable $e) {
            $this->log('Ignored: '.$e->getMessage());
        }
    }

    private function php(array $args, int $timeout): void
    {
        $process = new Process(array_merge([PHP_BINARY], $args), $this->installation->root(), null, null, $timeout);
        $process->run(function ($type, $buffer) {
            $this->log(rtrim($buffer));
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException(basename((string) ($args[0] ?? 'php')).' '.($args[1] ?? '').' failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function saveRun(array $run): void
    {
        $this->state->write("runs/{$run['id']}/run.json", $run);
        $this->state->write('current-run.json', $run);

        // Fault injection for the update lab (tools/update-lab): the process
        // dies at this phase, the way a killed process or a power cut would,
        // leaving the run for `pnlcs:update-rollback`.
        if (getenv('PNLCS_UPDATE_LAB_KILL_AT') === $run['phase']) {
            exit(137);
        }
    }

    private function status(string $state, string $step, array $extra = []): void
    {
        $this->state->status($state, $step, $extra);
        $this->log("[{$state}] {$step}");
    }

    private function finish(array $run, string $result, string $message, ?array $report = null): void
    {
        $this->state->addHistory([
            'run' => $run['id'], 'from' => $run['from'], 'to' => $run['to'], 'by' => $run['by'] ?? null,
            'result' => $result, 'message' => $message, 'at' => now()->toIso8601String(),
            'merged' => $report['plan']['merged'] ?? [], 'kept' => $report['plan']['kept'] ?? [], 'resolved' => $report['plan']['resolved'] ?? [],
        ]);
        $this->status($result, 'finished', ['message' => $message, 'run' => $run['id'], 'version' => $run['to']]);

        try {
            Log::log($result === 'updated' ? 'info' : 'error', 'PNLCS update: '.$message, ['run' => $run['id']]);
            app(NotificationService::class)->dispatch($result === 'updated' ? 'update.completed' : 'update.failed', [
                'event_type' => $result === 'updated' ? 'update.completed' : 'update.failed',
                'subject' => $result === 'updated' ? "PNLCS updated to {$run['to']}" : "PNLCS update to {$run['to']}: {$result}",
                'message' => $message,
            ]);
        } catch (Throwable) {
            // A notification that cannot be sent must not change the outcome.
        }
    }

    private function log(string $line): void
    {
        if ($line === '') {
            return;
        }
        if ($this->runDir !== null && is_dir($this->runDir)) {
            file_put_contents("{$this->runDir}/update.log", '['.now()->format('H:i:s')."] {$line}\n", FILE_APPEND);
        }
        if ($this->output) {
            ($this->output)($line);
        }
    }

    private function pruneRuns(): void
    {
        $cutoff = now()->subDays((int) config('updates.keep_runs_days', 14))->getTimestamp();
        foreach (glob($this->state->path('runs').'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $run = json_decode((string) @file_get_contents("{$dir}/run.json"), true);
            if (is_array($run) && ($run['phase'] ?? '') === 'done' && filemtime($dir) < $cutoff && $dir !== $this->runDir) {
                $this->removeDirectory($dir);
            }
        }
    }

    /**
     * The unpacked releases of a run (about 200 MB). The journal, the backups
     * and the database snapshot stay: they are what a rollback needs.
     */
    private function removeUnpacked(): void
    {
        if ($this->runDir !== null) {
            $this->removeDirectory("{$this->runDir}/new");
            $this->removeDirectory("{$this->runDir}/base");
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir) || is_link($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            ($file->isDir() && ! $file->isLink()) ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
