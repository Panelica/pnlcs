<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateRunner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Finishes an update that stopped part way - the process was killed, the
 * server lost power - by putting the files and the database back as they were
 * before it started. Safe to run more than once.
 */
class UpdateRollbackCommand extends Command
{
    protected $signature = 'pnlcs:update-rollback';

    protected $description = 'Roll back an update that did not finish';

    public function handle(UpdateRunner $runner): int
    {
        $runner->onOutput(fn (string $line) => $this->line("  {$line}"));

        try {
            $result = $runner->rollbackUnfinished();
        } catch (Throwable $e) {
            $this->error('Rolling back failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($result['rolled_back'] ? "Run {$result['run']} rolled back. The site runs the previous version." : $result['message']);

        return self::SUCCESS;
    }
}
