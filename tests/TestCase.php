<?php

namespace Tests;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Force test database connection. Uses DB_DATABASE from the environment
        // (phpunit.xml / .env.testing) so the suite does not silently connect
        // to a hard-coded database that may not exist on this install.
        // Under --parallel a test that uses a database trait runs on its
        // process's own copy, named the way Laravel's parallel testing names
        // it; pointing every process back at the one database made them
        // deadlock and drop tables under each other.
        $database = env('DB_DATABASE', 'pnlcs_test');
        config(['database.connections.mysql.database' => $this->usesParallelDatabaseCopy() ? $database.'_test_'.ParallelTesting::token() : $database]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        // Start transaction for rollback
        DB::beginTransaction();

        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    /**
     * Sign a test in on the guard the account belongs to.
     *
     * Laravel's actingAs() also makes the guard it is given the default one for
     * the rest of the test, and with no guard it keeps that default. So a test
     * that acted as staff first and then wrote actingAs($customer) put the
     * customer on the admin guard: the customer's pages answered as a guest and
     * redirected to the sign-in page. It only showed when the two accounts were
     * handed the same id, because the password fingerprint that ends a session
     * after a password change (EndSessionAfterPasswordChange) is keyed by id -
     * so which test broke depended on how many rows the run had created before
     * it, and the suite was a lottery. Naming the guard here fixes every test
     * at once, including the ones nobody has written yet.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        return parent::actingAs($user, $guard ?? ($user instanceof Admin ? 'admin' : 'web'));
    }

    /**
     * Whether Laravel's parallel testing gave this test a database of its own.
     *
     * It makes the per-process copy only for a test case that uses one of the
     * database traits (TestDatabases::bootTestDatabase()), so a plain PHPUnit
     * test such as ExampleTest stays on DB_DATABASE, as it would serially.
     */
    private function usesParallelDatabaseCopy(): bool
    {
        if (! ParallelTesting::token() || ParallelTesting::option('without_databases')) {
            return false;
        }

        return array_intersect(class_uses_recursive(static::class), [
            DatabaseMigrations::class,
            DatabaseTransactions::class,
            DatabaseTruncation::class,
            RefreshDatabase::class,
        ]) !== [];
    }

    protected function tearDown(): void
    {
        // Rollback all changes
        DB::rollBack();
        parent::tearDown();
    }
}
