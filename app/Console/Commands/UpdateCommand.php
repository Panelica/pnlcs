<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Updates\Installation;
use App\Services\Updates\ReleaseIndex;
use App\Services\Updates\UpdatePlan;
use App\Services\Updates\UpdateRunner;
use App\Services\Updates\UpdateState;
use Illuminate\Console\Command;
use Throwable;

/**
 * Updates PNLCS to a published release, the same way Setup -> Updates does.
 * What it promises and how: RELEASING.md and App\Services\Updates\UpdateRunner.
 */
class UpdateCommand extends Command
{
    protected $signature = 'pnlcs:update
        {version? : The release to update to (default: the newest on the channel)}
        {--channel= : stable or beta (default: the channel chosen on Setup -> Updates)}
        {--check : Only check: download, verify and compare, change nothing}
        {--resolve=* : A conflict decision, path=new or path=mine}
        {--allow-major : Allow an update to a new major version}
        {--ignore-requires=* : A module or theme to update past although it says it does not support the new version}
        {--yes : Do not ask for confirmation}
        {--from-request : Run what was requested on Setup -> Updates (the scheduler calls this every minute)}';

    protected $description = 'Update PNLCS to a published release, keeping every change made to this installation';

    public function handle(UpdateRunner $runner, ReleaseIndex $index, Installation $installation, UpdateState $state): int
    {
        app()->setLocale('en');

        $request = null;
        if ($this->option('from-request')) {
            // Claimed before it runs: a request that fails is not retried every
            // minute, and the scheduler and the process the admin area started
            // never both run it.
            $request = $state->claim('request.json');
            if ($request === null) {
                return self::SUCCESS;
            }
        }

        $runner->onOutput(fn (string $line) => $this->output->isVerbose() ? $this->line("  {$line}") : null);

        // Before anything else: an update that stopped part way leaves the new
        // files with the old database, and the installation then reports the
        // new version. Nothing may be checked or applied on top of that.
        if ($unfinished = $runner->unfinished()) {
            $this->error("The update to {$unfinished['to']} (run {$unfinished['id']}) stopped during \"{$unfinished['phase']}\" and did not finish.");
            $this->error('Put everything back first: php artisan pnlcs:update-rollback');
            $state->status('error', 'check', ['message' => "The update to {$unfinished['to']} did not finish. Run: php artisan pnlcs:update-rollback"]);

            return self::FAILURE;
        }

        try {
            $channel = $request['channel'] ?? $this->option('channel') ?: Setting::get('update_channel', ReleaseIndex::STABLE);
            $wanted = $request['version'] ?? $this->argument('version');
            $release = $wanted ? $index->find($wanted) : $index->latest($channel, $installation->version());

            if ($release === null) {
                $this->info($wanted ? "Release {$wanted} is not published." : 'PNLCS '.($installation->version() ?? '').' is up to date on the '.$channel.' channel.');
                $this->requestFailed($state, $request, "Release {$wanted} is not published.");

                return $wanted ? self::FAILURE : self::SUCCESS;
            }

            $resolutions = [];
            foreach ($this->option('resolve') as $decision) {
                [$path, $choice] = array_pad(explode('=', $decision, 2), 2, '');
                if (in_array($choice, [UpdatePlan::TAKE_NEW, UpdatePlan::KEEP_MINE], true)) {
                    $resolutions[$path] = $choice;
                }
            }
            $allowMajor = (bool) ($request['allow_major'] ?? $this->option('allow-major'));
            $ignore = $request['ignore_requires'] ?? $this->option('ignore-requires');

            $this->line("PNLCS {$installation->version()} -> {$release->version}".($release->preRelease ? ' (beta)' : ''));

            if (($request['action'] ?? null) === 'prepare' || $this->option('check')) {
                $report = $runner->prepare($release, $resolutions, $allowMajor, $ignore);
                $this->report($report);

                return $report['ok'] ? self::SUCCESS : 2;
            }

            if ($request === null && ! $this->option('yes') && ! $this->confirm('The site goes into maintenance for the update. Continue?', true)) {
                return self::FAILURE;
            }

            $by = $request['by'] ?? 'command line ('.(function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : '?').')';
            $result = $runner->apply($release, $by, $resolutions, $allowMajor, $ignore, $request['maintenance_secret'] ?? null);
            $this->report($result['report'] ?? []);

            return match ($result['result']) {
                'updated' => tap(self::SUCCESS, fn () => $this->info("Updated to {$release->version}.")),
                'refused' => tap(2, fn () => $this->error('The update did not start. Nothing was changed.')),
                default => tap(self::FAILURE, fn () => $this->error('The update failed and was rolled back: '.($result['error'] ?? '').'. The site runs the previous version.')),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->requestFailed($state, $request, $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * A request from the admin area that stopped before the updater took it
     * over (the release list unreachable, the release withdrawn): the page
     * says so instead of showing it queued for ever. A status the updater
     * wrote itself is left as it is.
     */
    private function requestFailed(UpdateState $state, ?array $request, string $message): void
    {
        if ($request !== null && ($state->read('status.json')['state'] ?? null) === 'queued') {
            $state->status('error', 'check', ['message' => $message, 'version' => $request['version'] ?? null]);
        }
    }

    private function report(array $report): void
    {
        if ($report === []) {
            return;
        }

        foreach ($report['blocking'] ?? [] as $issue) {
            $this->error('  ✗ '.self::describe($issue));
        }
        foreach ($report['warnings'] ?? [] as $issue) {
            $this->warn('  ! '.self::describe($issue));
        }
        foreach ($report['conflicts'] ?? [] as $conflict) {
            $this->line('  conflict  '.$conflict['path'].'  ('.__('admin.updates.conflict_kind.'.$conflict['kind']).')');
        }

        $plan = $report['plan'] ?? [];
        $this->line(sprintf('  %d file(s) to write, %d to remove; %d of your changes merged, %d kept as they are.',
            $plan['write'] ?? 0, $plan['delete'] ?? 0, count($plan['merged'] ?? []), count($plan['kept'] ?? [])));
        foreach ($plan['merged'] ?? [] as $path) {
            $this->line("  merged    {$path}");
        }
        foreach ($plan['kept'] ?? [] as $path) {
            $this->line("  kept      {$path}");
        }
    }

    /** @param array{code: string, params: array<string, mixed>} $issue */
    public static function describe(array $issue): string
    {
        $params = array_map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v, $issue['params'] ?? []);

        return __('admin.updates.issue.'.$issue['code'], $params);
    }
}
