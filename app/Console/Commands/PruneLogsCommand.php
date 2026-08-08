<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Prune high-volume log/history tables so they do not grow unbounded.
 *
 * Each table has its own retention window (in days), overridable via a
 * matching setting key. Age is measured on created_at. Completed/failed
 * module-queue rows are pruned; pending ones are always kept.
 */
class PruneLogsCommand extends Command
{
    protected $signature = 'pnlcs:prune-logs {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Delete old rows from log/history tables based on retention settings';

    /**
     * table => [default retention days, setting key, extra where closure]
     *
     * @var array<string, array{0: int, 1: string, 2: ?callable}>
     */
    private function targets(): array
    {
        return [
            'emails'           => [180, 'retention_emails_days', null],
            'ticket_mail_logs' => [90,  'retention_ticket_mail_logs_days', null],
            'gateway_logs'     => [90,  'retention_gateway_logs_days', null],
            'activity_logs'    => [365, 'retention_activity_logs_days', null],
            'module_queue'     => [30,  'retention_module_queue_days', function ($q) {
                // Work still waiting to run is never pruned - and neither is a
                // failed entry. Those are the panel's record of an action that
                // can never succeed: hasGivenUp() reads them, and auto-suspend,
                // unsuspend-on-payment and the cancellation run all ask it
                // before calling a module again. Deleting them after a month
                // sent those jobs back to a panel that has no such account,
                // queued the work afresh, and raised the same
                // "will NOT be retried" alert every thirty days.
                $q->whereIn('status', ['completed', 'cancelled']);
            }],
        ];
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $totalDeleted = 0;

        foreach ($this->targets() as $table => [$defaultDays, $settingKey, $extra]) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $days = (int) Setting::get($settingKey, (string) $defaultDays);
            if ($days <= 0) {
                $this->line("{$table}: retention disabled (days=0), skipped.");
                continue; // 0 = keep forever
            }

            $cutoff = now()->subDays($days);

            $query = DB::table($table)->where('created_at', '<', $cutoff);
            if ($extra) {
                $extra($query);
            }

            if ($dryRun) {
                $count = (clone $query)->count();
                $this->line("{$table}: would delete {$count} row(s) older than {$days}d.");
                $totalDeleted += $count;
                continue;
            }

            // Delete in chunks to avoid a single huge transaction / long lock.
            $deleted = 0;
            do {
                $chunk = (clone $query)->limit(5000)->delete();
                $deleted += $chunk;
            } while ($chunk > 0);

            if ($deleted > 0) {
                $this->info("{$table}: deleted {$deleted} row(s) older than {$days}d.");
            } else {
                $this->line("{$table}: nothing to prune (retention {$days}d).");
            }
            $totalDeleted += $deleted;
        }

        if (!$dryRun && $totalDeleted > 0) {
            Log::info("Log pruning complete", ['deleted' => $totalDeleted]);
        }

        $this->info(($dryRun ? 'Dry run: ' : 'Pruned ') . "{$totalDeleted} row(s) total.");
        return self::SUCCESS;
    }
}
