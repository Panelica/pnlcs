<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Force test database — NEVER touch production
        config(['database.connections.mysql.database' => 'pnlcs_test']);
        
        // Disable Vite in tests
        $this->withoutVite();

        // Disable CSRF
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    }
}
