<?php

namespace App\Listeners;

use App\Models\Setting;
use Illuminate\Console\Events\ScheduledTaskFinished;

/**
 * When the scheduler last ran.
 *
 * The dashboard reported it by searching the activity log for the word "cron",
 * which nothing ever wrote, so a perfectly healthy installation was told its
 * automation had never run. A heartbeat is one row, rather than a log entry
 * per task per minute.
 */
class RecordCronHeartbeat
{
    public function handle(ScheduledTaskFinished $event): void
    {
        Setting::set('LastCronRun', now()->toDateTimeString(), 'system');
    }
}
