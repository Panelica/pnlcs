<?php

namespace App\Console\Commands;

use App\Services\AddonManager;
use Illuminate\Console\Command;

class AddonsUpgradeCommand extends Command
{
    protected $signature = 'pnlcs:addons-upgrade';

    protected $description = "Run upgrade() for active addons whose files carry a newer version";

    /**
     * A step of the update sequence, right after `migrate --force`
     * (docs/install/updating.md): an addon release that needs a table or a
     * settings change gets it as part of the update, not whenever someone
     * next opens a page.
     *
     * Each addon is upgraded under its own lock; a run that finds one held
     * skips that addon. Exits non-zero when an upgrade failed, so an update
     * script stops there; the failed addon keeps its old version and is tried
     * again on the next run.
     */
    public function handle(AddonManager $addons): int
    {
        $results = $addons->runPendingUpgrades();

        if (! $results) {
            $this->info('No addon upgrades pending.');

            return Command::SUCCESS;
        }

        $failed = false;
        foreach ($results as $name => $result) {
            if ($result['skipped']) {
                $this->warn("{$name}: skipped, {$result['message']}.");
            } elseif ($result['success']) {
                $this->info("{$name}: upgraded from {$result['from']} to {$result['to']}.");
            } else {
                $failed = true;
                $this->error("{$name}: upgrade from {$result['from']} to {$result['to']} failed: {$result['message']}");
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
