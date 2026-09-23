<?php

use App\Models\TicketDepartment;
use App\Services\Mail\ImapMailboxClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
 * Two things a fresh self-hosted install on a clean server ran into.
 *
 * 1. The requirements page asked whether .env existed, not whether the wizard
 *    could write it. On a clean Ubuntu 24.04 following the README, .env was
 *    root-owned: every check was green, the administrator was created, and the
 *    last step answered 500 ("file_put_contents(.env): Permission denied").
 *
 * 2. PHP 8.4 moved imap out of core and Debian 13 does not package it. With a
 *    department set to import mail, the importer died every five minutes on
 *    "Call to undefined function imap_open()", measured on a clean Debian 13.
 */

test('the requirements page checks that the wizard can write .env', function () {
    $lock = storage_path('installed.lock');
    $parked = $lock.'.parked-by-requirements-test';
    $wasLocked = file_exists($lock);
    if ($wasLocked) {
        rename($lock, $parked);
    }

    try {
        DB::table('admins')->delete();

        $this->get('/install/requirements')
            ->assertSuccessful()
            ->assertViewHas('writable', fn (array $writable) => array_key_exists('.env', $writable)
                && $writable['.env'] === is_writable(base_path('.env')))
            ->assertSee('Writable Files &amp; Directories', false);
    } finally {
        if ($wasLocked && file_exists($parked)) {
            rename($parked, $lock);
        }
    }
});

test('a mailbox import without the imap extension logs what to install instead of crashing', function () {
    if (function_exists('imap_open')) {
        $this->markTestSkipped('imap is installed here; the missing-extension path cannot be exercised.');
    }

    $department = TicketDepartment::factory()->create([
        'import_active' => true,
        'import_protocol' => 'imap',
        'import_host' => '127.0.0.1',
        'import_port' => 993,
        'import_encryption' => 'ssl',
        'import_username' => 'u',
        'import_password' => 'p',
    ]);

    Log::spy();

    expect((new ImapMailboxClient)->fetchMessages($department))->toBe([]);

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message) => str_contains($message, 'imap extension is not installed'))
        ->once();
});
