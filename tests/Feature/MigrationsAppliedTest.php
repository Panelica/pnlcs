<?php

use Illuminate\Support\Facades\Artisan;

/**
 * The database the suite runs against has to match the migrations.
 *
 * This suite does not migrate. It points at a standing pnlcs_test database and
 * wraps each test in a transaction, so a migration added today is invisible
 * until somebody remembers to apply it by hand - and until they do, every test
 * that touches the new column quietly runs against yesterday's schema. That is
 * how the SSL expiry work first came out green on a table that did not have
 * the columns it was writing to.
 *
 * If this fails, run: DB_DATABASE=pnlcs_test php artisan migrate
 */
test('no migration is waiting to be applied to the test database', function () {
    Artisan::call('migrate:status');

    $pending = collect(explode("\n", Artisan::output()))
        ->filter(fn ($line) => str_contains($line, 'Pending'))
        ->map(fn ($line) => trim(preg_replace('/\.{2,}/', ' ', $line)))
        ->values()
        ->all();

    expect($pending)->toBe([]);
});
