<?php

use App\Services\Updates\Installation;
use App\Services\Updates\PackageStore;
use App\Services\Updates\ReleaseIndex;
use App\Services\Updates\UpdateRunner;
use App\Services\Updates\UpdateState;

/**
 * Self-healing (pnlcs:update-rollback --abandoned, run by the scheduler every
 * minute even in maintenance): it acts only when the update's own process is
 * gone, and never on a site the operator put in maintenance.
 */
function healSetup(array $run, ?array $down): array
{
    $root = storage_path('framework/testing/heal-'.bin2hex(random_bytes(6)));
    mkdir("{$root}/storage/framework", 0777, true);
    if ($down !== null) {
        file_put_contents("{$root}/storage/framework/down", json_encode($down));
    }
    config(['updates.path' => "{$root}/storage/app/pnlcs-update"]);
    app()->forgetInstance(UpdateState::class);
    app()->bind(Installation::class, fn ($app) => new Installation($app->make(PackageStore::class), $app->make(ReleaseIndex::class), $root));
    app()->forgetInstance(UpdateRunner::class);
    $state = app(UpdateState::class);
    $state->write('current-run.json', $run);

    return [$root, $state, app(UpdateRunner::class)];
}

afterEach(fn () => app()->offsetUnset(Installation::class));

test('a finished update whose process died before lifting maintenance is brought back up', function () {
    [$root, , $runner] = healSetup(['id' => 'r1', 'from' => '1.0.0', 'to' => '1.0.1', 'phase' => 'done', 'maintenance_secret' => 'abc', 'was_down' => false], ['secret' => 'abc']);

    expect($runner->healAbandoned())->toBe(['healed' => true, 'run' => 'r1', 'action' => 'lifted'])
        ->and(is_file("{$root}/storage/framework/down"))->toBeFalse();
});

test('maintenance the operator put the site in is never lifted', function () {
    [$root, , $runner] = healSetup(['id' => 'r1', 'phase' => 'done', 'maintenance_secret' => 'abc', 'was_down' => true], ['secret' => 'abc']);
    expect($runner->healAbandoned()['healed'])->toBeFalse()->and(is_file("{$root}/storage/framework/down"))->toBeTrue();

    // Nor a maintenance someone else started after the update (another secret).
    [$root2, , $runner2] = healSetup(['id' => 'r2', 'phase' => 'done', 'maintenance_secret' => 'abc', 'was_down' => false], ['secret' => 'other']);
    expect($runner2->healAbandoned()['healed'])->toBeFalse()->and(is_file("{$root2}/storage/framework/down"))->toBeTrue();
});

test('nothing is touched while the update is still running', function () {
    [$root, $state, $runner] = healSetup(['id' => 'r1', 'phase' => 'done', 'maintenance_secret' => 'abc', 'was_down' => false], ['secret' => 'abc']);
    $holder = new UpdateState($state->path());
    $holder->acquireLock();

    expect($runner->healAbandoned()['healed'])->toBeFalse()
        ->and(is_file("{$root}/storage/framework/down"))->toBeTrue();

    $holder->releaseLock();
});

test('a finished or refused run with no maintenance left needs nothing', function (string $phase) {
    [, , $runner] = healSetup(['id' => 'r1', 'phase' => $phase, 'was_down' => false], null);

    expect($runner->healAbandoned())->toBe(['healed' => false]);
})->with(['done', 'rolled_back', 'refused', 'failed_before_changes']);

/** A run whose files step had replaced app/Probe.php ("new"; "old" is in the backup). */
function failedRollbackSetup(array $extra): array
{
    $run = ['id' => 'r1', 'from' => '1.0.0', 'to' => '1.0.1', 'phase' => 'rollback_failed', 'rollback_from' => 'files', 'was_down' => false] + $extra;
    [$root, $state, $runner] = healSetup($run, null);
    $dir = $state->path('runs/r1');
    mkdir("{$dir}/backup/app", 0777, true);
    mkdir("{$root}/app", 0777, true);
    file_put_contents("{$root}/app/Probe.php", 'new');
    file_put_contents("{$dir}/backup/app/Probe.php", 'old');
    file_put_contents("{$dir}/journal.jsonl", json_encode(['op' => 'write', 'path' => 'app/Probe.php', 'existed' => true])."\n");
    file_put_contents("{$dir}/plan.json", json_encode(['root' => $root, 'run_dir' => $dir, 'new_root' => "{$dir}/new", 'actions' => []]));
    copy(resource_path('updater/apply.php'), "{$dir}/apply.php");

    return [$root, $state, $runner];
}

test('a rollback that failed is redone in full when it is tried again - the files too', function () {
    [$root, $state, $runner] = failedRollbackSetup(['rollback_attempts' => 1, 'rollback_failed_at' => now()->subHour()->toIso8601String()]);

    expect($runner->healAbandoned())->toMatchArray(['healed' => true, 'action' => 'rolled_back'])
        ->and(file_get_contents("{$root}/app/Probe.php"))->toBe('old')
        ->and($state->read('current-run.json')['phase'])->toBe('rolled_back');
});

test('a failed rollback is tried again after a pause, not every minute', function () {
    [$root, $state, $runner] = failedRollbackSetup(['rollback_attempts' => 2, 'rollback_failed_at' => now()->subMinutes(9)->toIso8601String()]);

    // The second attempt failed 9 minutes ago: the next one is due after 10.
    expect($runner->healAbandoned())->toBe(['healed' => false])
        ->and(file_get_contents("{$root}/app/Probe.php"))->toBe('new');

    $this->travel(2)->minutes();
    expect($runner->healAbandoned()['healed'])->toBeTrue()
        ->and(file_get_contents("{$root}/app/Probe.php"))->toBe('old');
});

test('the operator is told once when rolling back fails, not at every attempt', function () {
    [$root, $state, $runner] = failedRollbackSetup(['rollback_attempts' => 1, 'rollback_failed_at' => now()->subHour()->toIso8601String()]);
    // The applier cannot run: every attempt fails.
    file_put_contents($state->path('runs/r1/apply.php'), '<?php exit(1);');

    expect(fn () => $runner->rollbackUnfinished())->toThrow(RuntimeException::class);
    $this->travel(2)->hours();
    expect(fn () => $runner->rollbackUnfinished())->toThrow(RuntimeException::class);

    $run = $state->read('current-run.json');
    expect($run['phase'])->toBe('rollback_failed')
        ->and($run['rollback_attempts'])->toBe(3)
        ->and($state->history())->toHaveCount(0)
        ->and($state->read('status.json')['state'] ?? null)->toBe('rollback_failed');
});
