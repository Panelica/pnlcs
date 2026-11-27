<?php

use App\Models\ScheduledTaskRun;
use App\Services\ScheduleStatusService;
use Carbon\Carbon;

/*
 * Domain sync runs every fifteen minutes, but only between 03:00 and 06:00.
 * The automation screen used to read the cron expression alone and called it
 * overdue from 06:20 until the next morning.
 */

function domainSyncRow(?string $lastRun, ?string $failed = null): void
{
    ScheduledTaskRun::create([
        'command' => 'pnlcs:domain-sync',
        'last_run_at' => $lastRun,
        'runtime_ms' => 1000,
        'exit_code' => 0,
        'last_failed_at' => $failed,
        'failures' => $failed ? 1 : 0,
    ]);
}

/** @return array<string,mixed> */
function domainSyncTask(): array
{
    return collect(app(ScheduleStatusService::class)->tasks())
        ->firstWhere('command', 'pnlcs:domain-sync');
}

beforeEach(fn () => ScheduledTaskRun::query()->delete());
afterEach(fn () => Carbon::setTestNow());

it('is ok after a successful window, with the next window tomorrow', function () {
    Carbon::setTestNow('2026-11-26 15:49:00');
    domainSyncRow('2026-11-26 05:45:06');

    $task = domainSyncTask();

    expect($task['state'])->toBe('ok')
        ->and($task['next_due']->format('Y-m-d H:i'))->toBe('2026-11-27 03:00');
});

it('is still ok just after the window closes', function () {
    Carbon::setTestNow('2026-11-26 06:10:00');
    domainSyncRow('2026-11-26 05:45:06');

    expect(domainSyncTask()['state'])->toBe('ok');
});

it('is overdue when the whole window was missed', function () {
    Carbon::setTestNow('2026-11-26 06:10:00');
    domainSyncRow('2026-11-25 05:45:06');

    expect(domainSyncTask()['state'])->toBe('overdue');
});

it('waits for the first slot before calling the window late', function () {
    Carbon::setTestNow('2026-11-26 03:10:00');
    domainSyncRow('2026-11-25 05:45:06');

    $task = domainSyncTask();

    expect($task['state'])->toBe('ok')
        ->and($task['next_due']->format('Y-m-d H:i'))->toBe('2026-11-26 03:15');
});

it('calls the window late when the first slot is missed', function () {
    Carbon::setTestNow('2026-11-26 03:25:00');
    domainSyncRow('2026-11-25 05:45:06');

    expect(domainSyncTask()['state'])->toBe('overdue');
});

it('calls a long gap inside the window late', function () {
    Carbon::setTestNow('2026-11-26 04:20:00');
    domainSyncRow('2026-11-26 03:30:06');

    expect(domainSyncTask()['state'])->toBe('overdue');
});

it('reports the window in the frequency label', function () {
    Carbon::setTestNow('2026-11-26 15:49:00');
    domainSyncRow('2026-11-26 05:45:06');

    expect(domainSyncTask()['frequency'])->toContain('03:00–06:00');
});

it('shows pending when the task has never run', function () {
    Carbon::setTestNow('2026-11-26 15:49:00');

    expect(domainSyncTask()['state'])->toBe('pending');
});
