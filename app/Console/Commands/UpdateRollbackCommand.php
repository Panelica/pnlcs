<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateRunner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Finishes an update that stopped part way - the process was killed, the
 * server lost power - by putting the files and the database back as they were
 * before it started. Safe to run more than once.
 *
 * --abandoned is the self-healing run: the scheduler calls it every minute,
 * even in maintenance, and the Docker image on start. It acts only when the
 * update's process is gone, and also brings back a site whose update finished
 * but whose process died before it lifted maintenance.
 */
class UpdateRollbackCommand extends Command
{
    protected $signature = 'pnlcs:update-rollback {--abandoned : Only heal an update whose process is gone (the scheduler runs this)}';

    protected $description = 'Roll back an update that did not finish';

    public function handle(UpdateRunner $runner): int
    {
        $runner->onOutput(fn (string $line) => $this->line("  {$line}"));

        try {
            if ($this->option('abandoned')) {
                $result = $runner->healAbandoned();
                if ($result['healed']) {
                    $this->info("Self-heal: run {$result['run']} {$result['action']}.");
                }

                return self::SUCCESS;
            }

            $result = $runner->rollbackUnfinished();
        } catch (Throwable $e) {
            $this->error('Rolling back failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($result['rolled_back'] ? "Run {$result['run']} rolled back. The site runs the previous version." : $result['message']);

        return self::SUCCESS;
    }
}
