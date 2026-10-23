<?php

use Illuminate\Support\Facades\DB;

/**
 * pnlcs:i18n-convert --dry-run left the files alone but still ran
 * TranslationSeeder, so a dry run wrote the translations table.
 */
it('writes nothing to the database on a dry run', function () {
    $writes = 0;
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)) {
            $writes++;
        }
    });

    $this->artisan('pnlcs:i18n-convert', ['--seed-only' => true, '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertExitCode(0);

    expect($writes)->toBe(0);
});
