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

/*
 * The installer's tests park storage/installed.lock and put it back. There is
 * one lock for every process, so under --parallel the others met an installer
 * that was open or closed at the wrong moment. They run in a second, serial
 * pass: php artisan test --parallel --exclude-group=serial, then
 * php artisan test --group=serial. A plain php artisan test runs them all.
 */
/*
 * An addon keeps its migrations inside its own module, and `php artisan
 * migrate` only finds them while the addon is active - so a database built for
 * the suite (or rebuilt with --recreate-databases) has none of its tables. The
 * addon's own tests then fail on a missing table rather than on anything they
 * are about. This installs them once per process, the same guarded path
 * BankPaymentsModule::activate() takes, before any test touches them.
 */
function ensureBankPaymentsTables(): void
{
    if (Illuminate\Support\Facades\Schema::hasTable('bank_imports')
        && Illuminate\Support\Facades\Schema::hasTable('bank_payer_profiles')
        && Illuminate\Support\Facades\Schema::hasColumn('bank_transactions', 'matching_status')) {
        return;
    }

    /*
     * CREATE TABLE commits the transaction this test is wrapped in - the
     * database ends one implicitly before DDL - and once it has, the rollback
     * in tearDown has nothing left to undo: everything the test writes after
     * this point stays for good. One row per table was exactly how it showed
     * up when bank_payer_profiles was added: a leftover mailbox that
     * BankMailbox::firstOrFail() then picked up in a later run, so a test about
     * an encrypted password read someone else's row and failed.
     *
     * The transaction is still empty here, because this runs first in
     * beforeEach, so it is given back cleanly: roll it back, run the DDL, open
     * a new one at the same level for the trait to roll back as usual.
     */
    $connection = Illuminate\Support\Facades\DB::connection();
    $wasInTransaction = $connection->transactionLevel() > 0;

    if ($wasInTransaction) {
        $connection->rollBack();
    }

    Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'modules/Addons/BankPayments/Database/Migrations',
        '--force' => true,
    ]);

    if ($wasInTransaction) {
        $connection->beginTransaction();
    }
}

/*
 * These six write a real addon or module into modules/ and delete it again,
 * and modules/ is one directory shared by every parallel worker. The tests
 * that scan the tree - module completeness, the translation key scan, the
 * no-browser-dialogs scan - and the addon manager itself then see a module
 * that is half written or already gone, in a process that never asked for it.
 *
 * It shows up as a failure in whichever test happened to be looking:
 * AddonServiceProviderTest's "addons without a provider file add none" went
 * red once in a run of 5,730 and passed on its own and on the next full run.
 * Nothing about the product was wrong either time, which is the kind of red
 * that teaches people to run the suite again instead of reading it.
 *
 * They are quick, and in the serial pass they have the tree to themselves.
 */
pest()->group('serial')->in(
    'Feature/InstallDatabaseStepTest.php',
    'Feature/InstallerLanguageTest.php',
    'Feature/InstallerReachableTest.php',
    'Feature/InstallRequirementsAndImapTest.php',
    'Feature/AddonAdminMenuTest.php',
    'Feature/AddonHookFilesTest.php',
    'Feature/AddonServiceProviderTest.php',
    'Feature/AddonUpgradeTest.php',
    'Feature/AdminMenuProviderTest.php',
    'Feature/ModuleDiscoveryTest.php',
    'Feature/ModuleServiceProvidersTest.php',
    /*
     * And these five share one file: storage/app/invoice-templates/custom.html,
     * the operator's uploaded template, of which the product has exactly one.
     * Two of them delete it to start from nothing while the others write it and
     * read it back, so in parallel a test lost the template between its own
     * write and its own read:
     *
     *   file_get_contents(storage/app/invoice-templates/custom.html):
     *   Failed to open stream: No such file or directory
     *
     * at the line after the one that had just written it. Same shape as the
     * modules/ race above - a real path the product owns, not a temporary one -
     * and the same remedy.
     */
    'Feature/InvoiceTemplateTest.php',
    'Feature/InvoiceTemplateLanguageTest.php',
    'Feature/UploadedInvoiceTemplateSurvivesUpdateTest.php',
    'Feature/CompanyBankAccountsTest.php',
    'Feature/VatSummaryTest.php',
);
