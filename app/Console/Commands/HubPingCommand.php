<?php

namespace App\Console\Commands;

use App\Services\Hub\HubPing;
use Illuminate\Console\Command;

/**
 * The daily ping to hub.pnlcs.com (App\Services\Hub\HubPing). Scheduled every
 * hour; it sends once a day, at this install's own hour.
 */
class HubPingCommand extends Command
{
    protected $signature = 'pnlcs:hub-ping {--now : Send now instead of waiting for this install\'s hour}';

    protected $description = 'Send the daily ping to hub.pnlcs.com and read its notices';

    public function handle(HubPing $hub): int
    {
        if (! HubPing::allowed()) {
            $this->line('Not sent: only a production install sends the ping.');

            return self::SUCCESS;
        }

        if (! $this->option('now') && ! $hub->due()) {
            return self::SUCCESS;
        }

        $this->line($hub->send() ? 'Sent.' : 'The hub did not answer; see the log. Nothing else is affected.');

        return self::SUCCESS;
    }
}
