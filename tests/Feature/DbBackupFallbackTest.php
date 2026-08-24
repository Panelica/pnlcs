<?php

use Illuminate\Support\Facades\File;

/*
 * The nightly backup must produce a backup on a machine with no dump binary.
 *
 * The billing container ships without a client toolset, so pnlcs:db-backup
 * failed silently every night since the install - and when the database was
 * recreated, the hand-written knowledge base died with it and there was not
 * one backup to restore from. The command now falls back to dumping over the
 * PDO connection it already has.
 */

test('a backup is produced even without a dump binary, and it holds the data', function () {
    $dir = storage_path('framework/testing/backup-'.uniqid());

    try {
        // The test database genuinely contains this suite's schema and seed
        // rows; a dump smaller than that is a failure dressed as success.
        // --php forces the fallback branch: on this machine a real dump
        // binary exists, on the billing container it does not, and the branch
        // must be provable wherever the suite runs.
        $this->artisan('pnlcs:db-backup', ['--dir' => $dir, '--retention' => 1, '--php' => true])
            ->assertExitCode(0);

        $files = glob($dir.'/pnlcs-*.sql.gz');
        expect($files)->toHaveCount(1);

        $sql = (string) gzdecode((string) file_get_contents($files[0]));
        expect(strlen($sql))->toBeGreaterThan(10_000)
            ->and($sql)->toContain('CREATE TABLE')
            ->and($sql)->toContain('`kb_articles`')      // the table this was written for
            ->and($sql)->toContain('`clients`')
            ->and($sql)->toContain('SET FOREIGN_KEY_CHECKS=1;'); // reached the end, not truncated
    } finally {
        File::deleteDirectory($dir);
    }
});
