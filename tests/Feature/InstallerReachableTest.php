<?php

use Illuminate\Support\Facades\DB;

/*
 * The install wizard has to exist on a fresh deployment.
 *
 * storage/installed.lock was committed to the repository by accident. The
 * container entrypoint clones this repo on first start, so the lock arrived
 * before anyone had installed anything, EnsureNotInstalled saw it and answered
 * 404, and /install was unreachable on every new deployment - while the site
 * itself came up fine, which is why nobody noticed. Found on a real deployment
 * on 2026-08-20; the lock was seven days old and had been shipped that whole
 * time.
 */

test('the repository does not ship an installed lock', function () {
    if (! is_dir(base_path('.git'))) {
        $this->markTestSkipped('Not a git checkout - nothing to inspect.');
    }

    $tracked = trim((string) shell_exec(
        'cd '.escapeshellarg(base_path()).' && git ls-files storage/installed.lock 2>/dev/null'
    ));

    // A tracked lock means a fresh clone believes it is already installed.
    expect($tracked)->toBe('');
});

test('the lock is ignored so it cannot be committed again', function () {
    expect(file_get_contents(base_path('.gitignore')))->toContain('installed.lock');
});

test('a deployment with no lock and no administrator can reach the wizard', function () {
    // Put the machine into the state a new deployment is in, whatever state
    // this particular checkout happens to be in, and put it back afterwards.
    $lock = storage_path('installed.lock');
    $parked = $lock.'.parked-by-test';
    $wasLocked = file_exists($lock);
    if ($wasLocked) {
        rename($lock, $parked);
    }

    try {
        // No administrator either: that is the other half of "not installed".
        DB::table('admins')->delete();

        // The entry point sends the visitor into the first step rather than
        // rendering itself; what matters is that it is not a 404.
        $this->get('/install')->assertRedirect();
        $this->get('/install/requirements')->assertSuccessful();
    } finally {
        if ($wasLocked && file_exists($parked)) {
            rename($parked, $lock);
        }
    }
});

test('the wizard closes once the lock is there', function () {
    $lock = storage_path('installed.lock');
    $wasLocked = file_exists($lock);
    if (! $wasLocked) {
        file_put_contents($lock, date('c'));
    }

    try {
        // The other side of the same guard: an installed system must not offer
        // the wizard to a passer-by.
        $this->get('/install')->assertNotFound();
    } finally {
        if (! $wasLocked) {
            @unlink($lock);
        }
    }
});
