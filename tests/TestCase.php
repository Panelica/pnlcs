<?php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Force test database connection
        config(['database.connections.mysql.database' => 'pnlcs_test']);
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
