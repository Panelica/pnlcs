<?php

use App\Models\Admin;
use App\Services\ScheduleStatusService;

// The PNLCS Docker image runs the scheduler itself under supervisor. The
// automation screen told such operators to add a host cron line carrying the
// container's path, which no host can run (a customer did exactly that and
// saw nothing change). It also said "every 5 minutes" for a line that runs
// every minute.

function automationHintConfig(string $body): string
{
    $path = tempnam(sys_get_temp_dir(), 'supervisord');
    file_put_contents($path, $body);

    return $path;
}

it('recognises the Docker image scheduler from its supervisor config', function () {
    $path = automationHintConfig("[program:scheduler]\ncommand=/bin/sh -c \"while true; do php /var/www/pnlcs/artisan schedule:run --no-interaction; sleep 60; done\"\n");

    expect((new ScheduleStatusService($path))->schedulerRunsInContainer())->toBeTrue();
    expect((new ScheduleStatusService($path . '.missing'))->schedulerRunsInContainer())->toBeFalse();

    file_put_contents($path, "[program:php-fpm]\ncommand=/usr/local/sbin/php-fpm --nodaemonize\n");
    expect((new ScheduleStatusService($path))->schedulerRunsInContainer())->toBeFalse();

    @unlink($path);
});

it('shows the cron line only when nothing else runs the scheduler', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    $path = automationHintConfig("[program:php-fpm]\n");
    $this->app->instance(ScheduleStatusService::class, new ScheduleStatusService($path));

    $html = $this->get(route('admin.config.automation'))->assertOk()->getContent();
    expect($html)->toContain('php artisan schedule:run')
        ->toContain(e(__('admin.automation.cron_explanation')))
        ->not->toContain(e(__('admin.automation.container_scheduler')));

    file_put_contents($path, "command=php /var/www/pnlcs/artisan schedule:run --no-interaction\n");

    $html = $this->get(route('admin.config.automation'))->assertOk()->getContent();
    expect($html)->toContain(e(__('admin.automation.container_scheduler')))
        ->not->toContain('* * * * * cd');

    @unlink($path);
});

it('describes the cron line as running every minute in every language', function () {
    foreach (['en', 'tr', 'de', 'pl', 'zh'] as $locale) {
        $text = trans('admin.automation.cron_explanation', [], $locale);

        expect($text)->not->toContain('5')
            ->and(trans('admin.automation.container_scheduler', [], $locale))
            ->not->toBe('admin.automation.container_scheduler');
    }
});
