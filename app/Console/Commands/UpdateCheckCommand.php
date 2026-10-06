<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\NotificationService;
use App\Services\Updates\Installation;
use App\Services\Updates\ReleaseIndex;
use App\Services\Updates\UpdateState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Looks for a newer release once a day. Only looks: nothing is downloaded or
 * applied without an administrator asking for it.
 */
class UpdateCheckCommand extends Command
{
    protected $signature = 'pnlcs:update-check';

    protected $description = 'Check whether a newer PNLCS release is published on the chosen channel';

    public function handle(ReleaseIndex $index, Installation $installation, UpdateState $state): int
    {
        $channel = Setting::get('update_channel', ReleaseIndex::STABLE);

        try {
            $latest = $index->latest($channel, $installation->version());
        } catch (Throwable $e) {
            Log::warning('PNLCS update check failed', ['error' => $e->getMessage()]);
            $state->write('latest.json', ['checked_at' => now()->toIso8601String(), 'channel' => $channel, 'error' => $e->getMessage()]);
            $this->warn('The release list could not be read: '.$e->getMessage());

            return self::FAILURE;
        }

        $state->write('latest.json', [
            'checked_at' => now()->toIso8601String(),
            'channel' => $channel,
            'installed' => (string) $installation->version(),
            'latest' => $latest?->toArray(),
        ]);

        if ($latest === null) {
            $this->info('PNLCS '.$installation->version().' is up to date.');

            return self::SUCCESS;
        }

        $this->info("PNLCS {$latest->version} is available.");

        // Once per release, not every day.
        $notified = $state->read('notified.json') ?? [];
        if (! in_array((string) $latest->version, $notified, true)) {
            app(NotificationService::class)->dispatch('update.available', [
                'event_type' => 'update.available',
                'subject' => "PNLCS {$latest->version} is available",
                'message' => "PNLCS {$latest->version} is available (installed: {$installation->version()}). Review and apply it on Setup -> Updates.",
            ]);
            $notified[] = (string) $latest->version;
            $state->write('notified.json', array_slice($notified, -20));
        }

        return self::SUCCESS;
    }
}
