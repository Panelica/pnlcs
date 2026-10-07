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
