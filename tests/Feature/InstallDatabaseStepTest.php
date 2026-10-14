<?php

use Illuminate\Support\Facades\DB;

/*
 * The wizard's database step on a real web server.
 *
 * It ran every migration in one request - 20 seconds on one test machine, over
 * a minute on another, while nginx and Apache give PHP 60 seconds by default -
 * and it only checked that a migrations table existed, so a database whose
 * migrations had stopped halfway was sent on to the administrator step. The
 * tables are now made over several short requests that resume where they
 * stopped.
 */

/** A migration the test database has not run yet; returns its name. */
function pendingInstallMigration(): string
{
    $dir = storage_path('framework/testing/install-migrations-'.bin2hex(random_bytes(4)));
    mkdir($dir, 0777, true);
    $name = '2099_01_01_000000_install_wizard_test_'.bin2hex(random_bytes(4));
    // No schema change: the test database keeps its transaction.
    file_put_contents("{$dir}/{$name}.php", "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\n\nreturn new class extends Migration\n{\n    public function up(): void {}\n\n    public function down(): void {}\n};\n");
    app('migrator')->path($dir);

    return $name;
}

/** Runs $test as on a machine that is not installed yet. */
function notInstalled(Closure $test): void
{
    $lock = storage_path('installed.lock');
    $parked = $lock.'.parked-by-test';
    $wasLocked = file_exists($lock);
    if ($wasLocked) {
        rename($lock, $parked);
    }
    DB::table('admins')->delete();

    try {
        $test();
    } finally {
        if ($wasLocked && file_exists($parked)) {
            rename($parked, $lock);
        }
    }
}

afterEach(function () {
    foreach (glob(storage_path('framework/testing/install-migrations-*')) ?: [] as $dir) {
        array_map('unlink', glob("{$dir}/*.php") ?: []);
        @rmdir($dir);
    }
});

test('a database with every migration run goes on to the administrator step', function () {
    notInstalled(function () {
        $this->get('/install/database')->assertRedirect('/install/admin');
        $this->get('/install/database/migrate')->assertRedirect('/install/admin');
    });
});

test('a database with migrations left carries on with them instead of moving on', function () {
    notInstalled(function () {
        pendingInstallMigration();

        $this->get('/install/database')->assertRedirect('/install/database/migrate');
        $this->get('/install/database/migrate')->assertSuccessful()->assertSee(__('install.migrate_title'));
    });
});

test('the migrate step runs what is left and says when it is done', function () {
    notInstalled(function () {
        $name = pendingInstallMigration();

        $this->postJson('/install/database/migrate')
            ->assertOk()
            ->assertJson(['ok' => true, 'busy' => false, 'done' => true, 'pending' => 0]);

        expect(DB::table('migrations')->where('migration', $name)->exists())->toBeTrue();
    });
});

test('a second request while the migrations run is told to wait', function () {
    notInstalled(function () {
        pendingInstallMigration();
        $lock = fopen(storage_path('framework/cache/install-migrate.lock'), 'c');
        flock($lock, LOCK_EX);

        try {
            $this->postJson('/install/database/migrate')
                ->assertOk()
                ->assertJson(['ok' => true, 'busy' => true, 'done' => false, 'pending' => 1]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    });
});

test('a lock file that cannot be opened is reported, not waited on', function () {
    notInstalled(function () {
        pendingInstallMigration();
        $lock = storage_path('framework/cache/install-migrate.lock');
        @unlink($lock);
        // A directory in its place: fopen fails for root too.
        mkdir($lock);

        try {
            $this->postJson('/install/database/migrate')
                ->assertStatus(500)
                ->assertJson(['ok' => false, 'done' => false]);
        } finally {
            rmdir($lock);
        }
    });
});

test('settings that do not connect are not written to .env', function () {
    notInstalled(function () {
        $env = base_path('.env');
        $before = is_file($env) ? file_get_contents($env) : null;

        $this->from('/install/database')->post('/install/database', [
            'host' => '127.0.0.1', 'port' => 1, 'database' => 'nope', 'username' => 'nope', 'password' => 'secret',
        ])->assertRedirect('/install/database')->assertSessionHasErrors('database');

        expect(is_file($env) ? file_get_contents($env) : null)->toBe($before);
    });
});

test('the finish page shows the scheduler line, with the real path only to the installer', function () {
    $this->get('/install/finish')->assertOk()->assertSee('cd /path/to/pnlcs && php artisan schedule:run', false);

    $this->withSession(['install.admin_id' => 1])->get('/install/finish')
        ->assertOk()->assertSee('cd '.base_path().' && php artisan schedule:run', false);
});
