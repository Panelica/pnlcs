<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/*
 * Every test starts from the same database.
 *
 * Without this the suite ran against whatever the last run left behind, so a
 * test could pass because an earlier one seeded a row and fail on its own. It
 * also made switching branches look like product breakage: rows and schema
 * carried over from the previous branch's migration set.
 *
 * Transactions rather than a full refresh: the schema is migrated once, and each
 * test is rolled back afterwards. A per-test rebuild costs the suite half an
 * hour instead of a few minutes, which nobody would run.
 */
pest()->use(DatabaseTransactions::class)->in('Feature', 'Unit');

/*
 * The suite tests an INSTALLED system. Without the lock, RedirectToInstaller
 * sends every page to the wizard, and on a virgin clone the first file in the
 * run - AccountTest - fails eleven times with 302s that vanish on the second
 * run once some later test has minted an admin. Found by running the suite on
 * a brand-new server; every developer clone had the lock from its own wizard
 * run, which is why nobody had seen it. The installer's own tests park and
 * restore the lock themselves, so they are unaffected.
 */
$installedLock = __DIR__.'/../storage/installed.lock';
if (! file_exists($installedLock)) {
    @file_put_contents($installedLock, date('c'));
}
