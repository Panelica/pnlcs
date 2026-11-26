<?php

namespace App\Support;

/**
 * Tasks that run only inside a recurring clock window.
 *
 * Laravel stores between('03:00', '06:00') as an opaque filter closure, so the
 * automation screen cannot read the window back off the schedule event. Keep
 * the one definition here and use it in both routes/console.php and
 * ScheduleStatusService, so a changed window cannot leave the status screen
 * telling a different story from the scheduler.
 */
final class ScheduledWindows
{
    /** Every fifteen minutes, 03:00–06:00. */
    public const DOMAIN_SYNC = [
        'start' => '03:00',
        'end' => '06:00',
        'every' => 15,
    ];

    /** @return array{start:string,end:string,every:int}|null */
    public static function for(string $command): ?array
    {
        return $command === 'pnlcs:domain-sync' ? self::DOMAIN_SYNC : null;
    }
}
