<?php

use Illuminate\Support\Facades\File;

/**
 * The backup command shells out to mysqldump, so it sits outside the cron smoke
 * net. A backup that silently produces an empty or truncated archive is only
 * discovered when someone tries to restore it, so this asserts the archive is
 * actually restorable: valid gzip, real schema inside, and mysqldump's
 * completion trailer, which it only writes when the dump finished.
 */
test('the database backup produces a restorable archive and rotates old ones', function () {
    $dir = storage_path('app/testing-backups-'.uniqid());
    File::ensureDirectoryExists($dir);

    try {
        $this->artisan('pnlcs:db-backup', ['--dir' => $dir, '--retention' => 2])
            ->assertSuccessful();

        $files = glob($dir.'/*.sql.gz');
        expect($files)->toHaveCount(1);

        $archive = $files[0];
        expect(filesize($archive))->toBeGreaterThan(1024);

        $sql = '';
        $handle = gzopen($archive, 'rb');
        while (! gzeof($handle)) {
            $sql .= gzread($handle, 65536);
        }
        gzclose($handle);

        expect($sql)->toContain('CREATE TABLE')
            ->toContain('`clients`')
            ->toContain('`invoices`')
            // mysqldump writes this last; a truncated dump will not have it.
            ->toContain('Dump completed on');

        // Rotation: a third run must leave only the two newest archives.
        $this->artisan('pnlcs:db-backup', ['--dir' => $dir, '--retention' => 2])->assertSuccessful();
        sleep(1);
        $this->artisan('pnlcs:db-backup', ['--dir' => $dir, '--retention' => 2])->assertSuccessful();

        expect(count(glob($dir.'/*.sql.gz')))->toBeLessThanOrEqual(2);
    } finally {
        File::deleteDirectory($dir);
    }
});
