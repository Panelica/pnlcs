<?php

use App\Models\Setting;
use App\Services\ScheduleStatusService;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Ksef\KsefServiceProvider;

function ksefScheduleEvents(): array
{
    app()->forgetInstance(Schedule::class);
    (new KsefServiceProvider(app()))->boot();

    return app(Schedule::class)->events();
}

function hasKsefImportEvent(array $events): bool
{
    return collect($events)->contains(fn ($event): bool => str_contains((string) $event->command, 'pnlcs:ksef-import-invoices'));
}

beforeEach(function () {
    Setting::set('addon_ksef_active', '0', 'addons');
});

afterEach(function () {
    Setting::set('addon_ksef_active', '0', 'addons');
});

it('does not schedule the KSeF import when the addon is off', function () {
    expect(hasKsefImportEvent(ksefScheduleEvents()))->toBeFalse();
});

it('schedules the KSeF import when the addon is on', function () {
    Setting::set('addon_ksef_active', '1', 'addons');

    $events = ksefScheduleEvents();
    $event = collect($events)->first(fn ($event): bool => str_contains((string) $event->command, 'pnlcs:ksef-import-invoices'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 */4 * * *');
});

it('describes a four-hour cron as four hours, not hourly', function () {
    $service = app(ScheduleStatusService::class);
    $frequency = new ReflectionMethod($service, 'frequency');
    $interval = new ReflectionMethod($service, 'expectedIntervalMinutes');
    $frequency->setAccessible(true);
    $interval->setAccessible(true);

    expect($frequency->invoke($service, 'pnlcs:ksef-import-invoices', '0 */4 * * *'))
        ->toBe(trans_choice('admin.automation.every_hours', 4, ['count' => 4]))
        ->and($interval->invoke($service, '0 */4 * * *'))->toBe(240);
});
