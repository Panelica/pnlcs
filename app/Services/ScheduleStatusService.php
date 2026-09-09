<?php

namespace App\Services;

use App\Models\ScheduledTaskRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

/**
 * What the scheduler is actually set up to do, and whether it is doing it.
 *
 * The automation screen used to list eight tasks typed into the template by
 * hand, every one of them reading "Never / Not configured" no matter what the
 * server was doing. Ours runs twenty-five tasks a day and the screen still
 * said the automation had never run.
 *
 * This reads the real schedule and joins it to what actually happened.
 */
class ScheduleStatusService
{
    /**
     * Every scheduled task, newest trouble first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tasks(): array
    {
        $runs = ScheduledTaskRun::all()->keyBy('command');
        $rows = [];

        foreach (app(Schedule::class)->events() as $event) {
            $summary = $event->getSummaryForDisplay();
            $key = ScheduledTaskRun::key($summary);
            $run = $runs->get($key);

            $rows[] = [
                'command'     => $key,
                'label'       => $this->label($key),
                'expression'  => $event->expression,
                'frequency'   => $this->frequency($event->expression),
                'next_due'    => $this->nextDue($event->expression),
                'last_run_at' => $run?->last_run_at,
                'runtime_ms'  => $run?->runtime_ms,
                'exit_code'   => $run?->exit_code,
                'failures'    => (int) ($run->failures ?? 0),
                'state'       => $this->state($event->expression, $run),
            ];
        }

        // Anything broken or overdue rises to the top; the rest keep the order
        // they run in. An operator opening the panel wants the problem first.
        $rank = ['failed' => 0, 'overdue' => 1, 'pending' => 2, 'ok' => 3];
        usort($rows, fn ($a, $b) => [$rank[$a['state']], $a['label']] <=> [$rank[$b['state']], $b['label']]);

        return $rows;
    }

    /** @return array<string, int> */
    public function summary(): array
    {
        $counts = ['ok' => 0, 'overdue' => 0, 'failed' => 0, 'pending' => 0];

        foreach ($this->tasks() as $row) {
            $counts[$row['state']]++;
        }

        return $counts;
    }

    /**
     * Whether the task is behaving.
     *
     * "overdue" means it should have run by now and did not — which is what an
     * operator actually wants flagged, rather than a raw last-run timestamp
     * they have to compare against a cron expression in their head.
     */
    private function state(string $expression, ?ScheduledTaskRun $run): string
    {
        if ($run === null || $run->last_run_at === null) {
            return 'pending';
        }

        if ($run->last_failed_at !== null && $run->last_run_at->lessThanOrEqualTo($run->last_failed_at)) {
            return 'failed';
        }

        $expected = $this->expectedIntervalMinutes($expression);

        // Twice the interval plus five minutes: one missed run is a blip, two
        // in a row is a scheduler that has stopped.
        return $run->last_run_at->diffInMinutes(now()) > ($expected * 2 + 5)
            ? 'overdue'
            : 'ok';
    }

    private function expectedIntervalMinutes(string $expression): int
    {
        $parts = preg_split('/\s+/', trim($expression));
        $minute = $parts[0] ?? '*';
        $hour   = $parts[1] ?? '*';
        $dom    = $parts[2] ?? '*';

        if ($dom !== '*') {
            return 60 * 24 * 31;
        }
        if ($hour !== '*') {
            return 60 * 24;
        }
        if (str_starts_with($minute, '*/')) {
            return max(1, (int) substr($minute, 2));
        }
        if ($minute === '*') {
            return 1;
        }

        return 60;
    }

    private function nextDue(string $expression): ?Carbon
    {
        try {
            return Carbon::instance(
                (new \Cron\CronExpression($expression))->getNextRunDate(now())
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function frequency(string $expression): string
    {
        $parts = preg_split('/\s+/', trim($expression));
        [$minute, $hour, $dom] = [$parts[0] ?? '*', $parts[1] ?? '*', $parts[2] ?? '*'];

        if ($dom !== '*') {
            return __('admin.automation.monthly');
        }
        if ($hour !== '*' && ! str_contains($hour, '*')) {
            return __('admin.automation.daily') . ' ' . sprintf('%02d:%02d', (int) $hour, (int) $minute);
        }
        if (str_starts_with($minute, '*/')) {
            return trans_choice('admin.automation.every_minutes', (int) substr($minute, 2), ['count' => (int) substr($minute, 2)]);
        }
        if ($minute === '*') {
            return __('admin.automation.every_minute');
        }

        return __('admin.automation.hourly');
    }

    /**
     * A readable name for a command.
     *
     * Falls back to the command itself rather than inventing one: a task added
     * later should show up as "pnlcs:new-thing" and still be findable, not
     * silently disappear because nobody added a translation.
     */
    private function label(string $command): string
    {
        // The names live under a single dotted key ('automation.tasks'), so
        // Laravel cannot walk into them with __('...tasks.name') — it splits on
        // the dots and finds no 'automation' array. Fetch the map, then index.
        $names = __('admin.automation.tasks');

        if (! is_array($names)) {
            return $command;
        }

        $key = str_replace([':', '-'], ['_', '_'], $command);

        return $names[$key] ?? $command;
    }
}
