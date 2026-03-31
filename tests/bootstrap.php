<?php

// Run once before the test suite to set up the database
// This avoids the per-test migrate:fresh issue with MySQL DDL transactions

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Run migrate:fresh on the test database
$exitCode = $kernel->call('migrate:fresh', ['--force' => true]);
if ($exitCode !== 0) {
    echo 'ERROR: migrate:fresh failed with exit code ' . $exitCode . PHP_EOL;
    exit(1);
}

echo 'Test database migrated successfully.' . PHP_EOL;
