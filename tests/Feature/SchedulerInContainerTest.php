<?php

use App\Services\ScheduleStatusService;

/*
 * The automation screen asks whether the container's scheduler is running by
 * looking for /etc/supervisord.conf. On a host with open_basedir in force that
 * path is outside the allowed list, so is_file() raises a warning, Laravel
 * turns the warning into an ErrorException and the whole screen died with
 * "open_basedir restriction in effect" (#346). The check is wrapped in a
 * try/catch now.
 *
 * That failure is not reproduced here, and deliberately not faked: it needs a
 * warning raised while Laravel's handler is the one in force, and narrowing
 * open_basedir inside the test process would poison every later test in it.
 * A stream wrapper that warns does throw under the booted application but not
 * under the test harness, so a test built on it passes with the fix and
 * without it - worse than no test. What is pinned here is the behaviour the
 * screen depends on either way.
 */
it('still finds the scheduler in a readable supervisor config', function () {
    $path = tempnam(sys_get_temp_dir(), 'sup');
    file_put_contents($path, "[program:scheduler]\ncommand=php artisan schedule:run\n");

    try {
        expect((new ScheduleStatusService($path))->schedulerRunsInContainer())->toBeTrue();
        file_put_contents($path, "[program:other]\ncommand=sleep 1\n");
        expect((new ScheduleStatusService($path))->schedulerRunsInContainer())->toBeFalse();
    } finally {
        @unlink($path);
    }
});
