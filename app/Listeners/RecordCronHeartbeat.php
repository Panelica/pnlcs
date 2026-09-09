<?php

namespace App\Listeners;

use App\Models\ScheduledTaskRun;
use App\Models\Setting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;

/**
 * When the scheduler last ran, and how each task went.
 *
 * The dashboard used to report this by searching the activity log for the word
 * "cron", which nothing ever wrote, so a perfectly healthy installation was
 * told its automation had never run. The heartbeat fixed that for the whole
 * scheduler; this now also records it per task, because "the scheduler ran"
 * and "invoice generation ran" are different questions and only the second one
 * tells an operator whether invoices went out this morning.
 */
class RecordCronHeartbeat
{
    public function handleFinished(ScheduledTaskFinished $event): void
    {
        Setting::set('LastCronRun', now()->toDateTimeString(), 'system');

        $this->record($event->task->getSummaryForDisplay(), [
            'last_run_at' => now(),
            'runtime_ms' => (int) round(($event->runtime ?? 0) * 1000),
            'exit_code' => $event->task->exitCode,
        ]);
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $key = ScheduledTaskRun::key($event->task->getSummaryForDisplay());
        $row = ScheduledTaskRun::firstOrNew(['command' => $key]);

        $row->last_run_at = now();
        $row->last_failed_at = now();
        $row->exit_code = $event->task->exitCode ?? 1;
        $row->failures = (int) $row->failures + 1;
        $row->save();
    }

    /** @param array<string, mixed> $values */
    private function record(?string $summary, array $values): void
    {
        $key = ScheduledTaskRun::key($summary);

        if ($key === '') {
            return;
        }

        $row = ScheduledTaskRun::firstOrNew(['command' => $key]);
        $row->fill($values);

        // A non-zero exit is a failure even when Laravel does not raise the
        // failed event — the db-backup command spent two nights exiting 1 with
        // nothing watching.
        if (($values['exit_code'] ?? 0) !== 0) {
            $row->last_failed_at = now();
            $row->failures = (int) $row->failures + 1;
        }

        $row->save();
    }
}
