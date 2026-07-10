<?php

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Currency update
// ---------------------------------------------------------------------------

test('currency rates update from the primary provider', function () {
    Currency::query()->delete();
    Currency::create(['code' => 'USD', 'prefix' => '$', 'rate' => 1, 'is_default' => true]);
    Currency::create(['code' => 'EUR', 'prefix' => '€', 'rate' => 0.5]);
    Currency::create(['code' => 'TRY', 'prefix' => '₺', 'rate' => 1]);

    Http::fake([
        'open.er-api.com/*' => Http::response([
            'result' => 'success',
            'rates'  => ['EUR' => 0.91234, 'TRY' => 41.55, 'GBP' => 0.79],
        ]),
    ]);

    $this->artisan('pnlcs:currency-update')->assertExitCode(0);

    expect((float) Currency::where('code', 'EUR')->first()->rate)->toBe(0.91234)
        ->and((float) Currency::where('code', 'TRY')->first()->rate)->toBe(41.55)
        ->and((float) Currency::where('code', 'USD')->first()->rate)->toBe(1.0);
});

test('currency update falls back to frankfurter when er-api fails', function () {
    Currency::query()->delete();
    Currency::create(['code' => 'EUR', 'rate' => 1, 'is_default' => true]);
    Currency::create(['code' => 'USD', 'rate' => 1]);

    Http::fake([
        'open.er-api.com/*'      => Http::response('oops', 500),
        'api.frankfurter.app/*'  => Http::response(['base' => 'EUR', 'rates' => ['USD' => 1.08]]),
    ]);

    $this->artisan('pnlcs:currency-update')->assertExitCode(0);

    expect((float) Currency::where('code', 'USD')->first()->rate)->toBe(1.08);
});

test('currency update respects the disable setting', function () {
    Currency::query()->delete();
    Currency::create(['code' => 'USD', 'rate' => 1, 'is_default' => true]);
    Currency::create(['code' => 'EUR', 'rate' => 0.5]);
    Setting::set('currency_auto_update', '0');

    Http::fake();

    $this->artisan('pnlcs:currency-update')->assertExitCode(0);

    Http::assertNothingSent();
    expect((float) Currency::where('code', 'EUR')->first()->rate)->toBe(0.5);
});

// ---------------------------------------------------------------------------
// Database backup
// ---------------------------------------------------------------------------

test('db backup produces a valid gzip dump and rotates old files', function () {
    $dir = sys_get_temp_dir() . '/pnlcs-backup-test-' . uniqid();
    mkdir($dir, 0750, true);

    // Pre-seed fake "old" backups to prove rotation.
    foreach (['20200101-010101', '20200102-010101', '20200103-010101'] as $stamp) {
        file_put_contents("{$dir}/pnlcs-{$stamp}.sql.gz", str_repeat('x', 10));
    }

    $this->artisan('pnlcs:db-backup', ['--dir' => $dir, '--retention' => 2])->assertExitCode(0);

    $files = glob($dir . '/pnlcs-*.sql.gz');
    sort($files);

    // retention=2 → the new dump + the newest old file survive
    expect($files)->toHaveCount(2);

    $newest = end($files);
    expect(filesize($newest))->toBeGreaterThan(512)
        ->and(file_get_contents($newest, false, null, 0, 2))->toBe("\x1f\x8b");

    // The gzip contains a real SQL dump
    $sql = shell_exec('zcat ' . escapeshellarg($newest) . ' | head -50');
    expect($sql)->toContain('CREATE TABLE');

    array_map('unlink', glob($dir . '/*'));
    rmdir($dir);
});

test('db backup respects the disable setting', function () {
    Setting::set('db_backup_enabled', '0');

    $dir = sys_get_temp_dir() . '/pnlcs-backup-disabled-' . uniqid();

    $this->artisan('pnlcs:db-backup', ['--dir' => $dir])->assertExitCode(0);

    expect(is_dir($dir) ? glob($dir . '/*') : [])->toBeEmpty();
    Setting::set('db_backup_enabled', '1');
});
