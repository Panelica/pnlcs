<?php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Force test database connection. Uses DB_DATABASE from the environment
        // (phpunit.xml / .env.testing) so the suite does not silently connect
        // to a hard-coded database that may not exist on this install.
        config(['database.connections.mysql.database' => env('DB_DATABASE', 'pnlcs_test')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        
        // Start transaction for rollback
        DB::beginTransaction();
        
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    }
    
    protected function tearDown(): void
    {
        // Rollback all changes
        DB::rollBack();
        parent::tearDown();
    }
}
