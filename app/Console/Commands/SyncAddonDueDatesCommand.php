<?php

namespace App\Console\Commands;

use App\Models\ServiceAddon;
use Illuminate\Console\Command;

/**
 * Repairs addons whose next_due_date no longer matches their service's.
 *
 * The manual next-due form now propagates the new date, but rows written
 * before that still carry the old one. This command reports and fixes those
 * rows without touching invoices, amounts or first_invoice_date.
 */
class SyncAddonDueDatesCommand extends Command
{
    protected $signature = 'pnlcs:sync-addon-due-dates {--dry-run : Report changes without saving them}';

    protected $description = 'Set active, pending and draft addon due dates to their service date';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $scanned = 0;
        $changed = 0;

        ServiceAddon::query()
            ->with('service', 'client', 'addon')
            ->whereNotNull('service_id')
            ->whereNotIn('status', ['cancelled', 'terminated'])
            ->orderBy('id')
            ->chunkById(200, function ($addons) use ($dryRun, &$scanned, &$changed) {
                foreach ($addons as $addon) {
                    $service = $addon->service;

                    if (! $service) {
                        continue;
                    }

                    $scanned++;
                    $old = $addon->next_due_date?->toDateString();
                    $new = $service->next_due_date?->toDateString();

                    if ($old === $new) {
                        continue;
                    }

                    $changed++;
                    $this->line(sprintf(
                        '#%d %s — service #%d (%s): %s -> %s',
                        $addon->id,
                        $addon->label(),
                        $service->id,
                        $addon->client?->email ?: 'no client',
                        $old ?: '-',
                        $new ?: '-'
                    ));

                    if (! $dryRun) {
                        $addon->update(['next_due_date' => $new]);
                    }
                }
            });

        $this->info(($dryRun ? 'Would update ' : 'Updated ').$changed.' of '.$scanned.' addon(s).');

        return Command::SUCCESS;
    }
}
