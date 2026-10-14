<?php

namespace App\Http\Controllers;

use App\Constants\Permissions;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Language;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * 5-step install wizard. Routes:
 *   GET  /install                  → redirect to /install/requirements
 *   GET  /install/requirements     → preflight checks
 *   GET  /install/database         → DB config form (skipped if migrations already applied)
 *   POST /install/database/test    → AJAX-style connection test (returns JSON)
 *   POST /install/database         → write DB creds to .env, run migrations
 *   GET  /install/admin            → admin account form
 *   POST /install/admin            → create admin in DB
 *   GET  /install/app              → application settings form (URL, name, locale)
 *   POST /install/app              → write to .env, finalize, write installed.lock
 *   GET  /install/finish           → done page with login link
 */
class InstallController extends Controller
{
    private const REQUIRED_EXTENSIONS = [
        'bcmath', 'curl', 'dom', 'fileinfo', 'gd', 'intl',
        'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip',
    ];

    public function index()
    {
        return redirect('/install/requirements');
    }

    // ─── Step 1: Requirements ───────────────────────────────────────────
    public function requirements()
    {
        $php = PHP_VERSION;
        $phpOk = version_compare($php, '8.4.0', '>=');

        $extensions = [];
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }

        $writable = [
            'storage/' => is_writable(storage_path()),
            'storage/framework/' => is_writable(storage_path('framework')),
            'storage/logs/' => is_writable(storage_path('logs')),
            'bootstrap/cache/' => is_writable(base_path('bootstrap/cache')),
            // The wizard writes the database, URL and name it is given into
            // .env. Asking only whether the file exists let a root-owned .env
            // through this page, and the last step then died with a 500 after
            // the administrator had already been created.
            '.env' => is_writable(base_path('.env')),
        ];

        $assets = [
            'vendor/' => is_dir(base_path('vendor')) && file_exists(base_path('vendor/autoload.php')),
            'public/build/' => is_dir(base_path('public/build')),
            '.env' => file_exists(base_path('.env')),
        ];

        $allOk = $phpOk
            && ! in_array(false, $extensions, true)
            && ! in_array(false, $writable, true)
            && ! in_array(false, $assets, true);

        return view('install.requirements', compact('php', 'phpOk', 'extensions', 'writable', 'assets', 'allOk'));
    }

    // ─── Step 2: Database ───────────────────────────────────────────────
    public function database()
    {
        // Tables already there (Docker, install.sh, a run that was cut off):
        // a finished set goes on to the administrator, an unfinished one
        // carries on where it stopped. Seeing only a migrations table used to
        // send a half-built database on to the next step.
        $status = $this->migrationStatus();
        if ($status !== null && $status['ran'] > 0) {
            return redirect($status['pending'] === 0 ? '/install/admin' : '/install/database/migrate');
        }

        return view('install.database', [
            'host' => config('database.connections.mysql.host', '127.0.0.1'),
            'port' => config('database.connections.mysql.port', '3306'),
            'database' => config('database.connections.mysql.database', 'pnlcs'),
            'username' => config('database.connections.mysql.username', 'pnlcs'),
        ]);
    }

    public function testDatabase(Request $request)
    {
        $data = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
        ]);

        $error = $this->connectionError($data);

        return $error === null
            ? response()->json(['ok' => true, 'message' => 'Connection successful.'])
            : response()->json(['ok' => false, 'message' => $error], 422);
    }

    /** @param  array<string, mixed>  $data  null when these settings connect, otherwise why not */
    private function connectionError(array $data): ?string
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s', $data['host'], (int) $data['port'], $data['database']);
            $pdo = new \PDO($dsn, $data['username'], $data['password'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->query('SELECT 1');

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function saveDatabase(Request $request)
    {
        $data = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
        ]);

        // Settings that do not connect are not written: a wrong password used
        // to land in .env first and fail afterwards.
        $error = $this->connectionError($data);
        if ($error !== null) {
            return back()->withInput($request->except('password'))
                ->withErrors(['database' => __('install.db_connect_failed', ['message' => $error])]);
        }

        $this->writeEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['host'],
            'DB_PORT' => (string) $data['port'],
            'DB_DATABASE' => $data['database'],
            'DB_USERNAME' => $data['username'],
            'DB_PASSWORD' => $data['password'] ?? '',
        ]);
        Artisan::call('config:clear');

        // The tables are made by the next page, a few at a time.
        return redirect('/install/database/migrate');
    }

    /** One request runs migrations for about this long, then reports back. */
    private const MIGRATE_SECONDS = 10;

    /**
     * The tables are created over several short requests. As one request the
     * ~180 migrations took 20 seconds on one test machine and over a minute on
     * another, while nginx and Apache wait 60 seconds for PHP by default: on a
     * slow host the page could be cut off with the migrations still running.
     */
    public function migrate(): \Illuminate\Http\RedirectResponse|\Illuminate\Contracts\View\View
    {
        $status = $this->migrationStatus();
        if ($status === null) {
            return redirect('/install/database');
        }
        if ($status['pending'] === 0 && $status['ran'] > 0) {
            return redirect('/install/admin');
        }

        return view('install.migrate', $status);
    }

    public function runMigrations(): \Illuminate\Http\JsonResponse
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        // Two tabs, or a retry while a cut-off request is still running, must
        // not run the same migration twice.
        $lock = @fopen(storage_path('framework/cache/install-migrate.lock'), 'c');
        if ($lock === false) {
            // Reported, not treated as "busy": the page would wait forever.
            return response()->json(['ok' => false, 'done' => false, 'message' => error_get_last()['message'] ?? 'storage/framework/cache is not writable'], 500);
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return response()->json(['ok' => true, 'busy' => true, 'done' => false] + ($this->migrationStatus() ?? []));
        }

        try {
            $migrator = app('migrator');
            $repository = $migrator->getRepository();
            if (! $repository->repositoryExists()) {
                $repository->createRepository();
            }
            $pending = array_diff_key($migrator->getMigrationFiles($this->migrationPaths()), array_flip($repository->getRan()));
            $migrator->requireFiles($pending);

            $started = microtime(true);
            foreach ($pending as $file) {
                $migrator->runPending([$file]);
                if (microtime(true) - $started >= self::MIGRATE_SECONDS) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'done' => false, 'message' => $e->getMessage()] + ($this->migrationStatus() ?? []), 500);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $status = $this->migrationStatus() ?? ['ran' => 0, 'pending' => 1, 'total' => 1];

        return response()->json(['ok' => true, 'busy' => false, 'done' => $status['pending'] === 0] + $status);
    }

    /** @return array{ran: int, pending: int, total: int}|null null while the database cannot be reached */
    private function migrationStatus(): ?array
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles($this->migrationPaths());
            $ran = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];
            $pending = count(array_diff_key($files, array_flip($ran)));

            return ['ran' => count($files) - $pending, 'pending' => $pending, 'total' => count($files)];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<string> where `php artisan migrate` looks */
    private function migrationPaths(): array
    {
        return array_merge([database_path('migrations')], app('migrator')->paths());
    }

    // ─── Step 3: Admin account ──────────────────────────────────────────
    public function admin()
    {
        return view('install.admin');
    }

    public function saveAdmin(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|min:3|max:64|alpha_dash',
            'email' => 'required|email|max:255',
            'first_name' => 'required|string|max:64',
            'last_name' => 'nullable|string|max:64',
            'password' => 'required|string|min:6|max:255|confirmed',
        ]);

        // Asked before the seeder runs, because the seeder creates an
        // administrator of its own and would make every install look finished.
        $before = Admin::query()->count();
        $mine = (int) $request->session()->get('install.admin_id');

        if ($before > 0 && ! ($before === 1 && $mine > 0 && Admin::whereKey($mine)->exists())) {
            abort(404);
        }

        // Run db:seed first so AdminRole + base data exist. Only once per
        // install: the seeded administrator is renamed to whatever the operator
        // chose just below, so a second run no longer recognises it and would
        // create "admin" / "admin123" all over again.
        if (! $request->session()->get('install.seeded')) {
            // About ten seconds on a fresh database; a slow host must not
            // stop it halfway.
            @set_time_limit(0);
            ignore_user_abort(true);
            try {
                Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);
                $request->session()->put('install.seeded', true);
            } catch (\Throwable $e) {
                // Continue — seeder failure shouldn't block admin creation if role already exists.
            }
        }

        // The seeder may have created an administrator during this very
        // request; on a first install that row is ours to fill in.
        if ($mine === 0 && $before === 0) {
            $mine = (int) (Admin::query()->orderBy('id')->value('id') ?? 0);
        }

        $role = AdminRole::query()->orderBy('id')->first();
        if (! $role) {
            // is_full_admin is what hasPermission() looks at first, and it is
            // what both seeders set. Without it the role fell back to its
            // permission list, and ['*'] is not a wildcard anything here
            // expands - it is a string that matches no permission at all, so
            // the only administrator on a fresh install could open nothing.
            // 'slug' went with it: admin_roles has no such column.
            $role = AdminRole::create([
                'name' => 'Full Administrator',
                'description' => 'Full access to all areas',
                'is_full_admin' => true,
                'permissions' => Permissions::all(),
            ]);
        }

        // Keyed on the row this session created, not on the username: keying on
        // the username is what allowed an existing administrator's password to
        // be written.
        $admin = Admin::updateOrCreate(
            ['id' => $mine ?: null],
            [
                'username' => $data['username'],
                'role_id' => $role->id,
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? '',
                'password' => Hash::make($data['password']),
            ]
        );

        session([
            'install.admin_username' => $data['username'],
            'install.admin_id' => $admin->id,
        ]);

        return redirect('/install/app');
    }

    // ─── Step 4: App settings ───────────────────────────────────────────
    public function app()
    {
        return view('install.app', [
            'app_url' => config('app.url', 'http://localhost'),
            'app_name' => config('app.name', 'PNLCS'),
            'app_locale' => config('app.locale', 'en'),
        ]);
    }

    public function saveApp(Request $request)
    {
        $data = $request->validate([
            'app_url' => 'required|url|max:255',
            'app_name' => 'required|string|max:64',
            'app_locale' => ['required', Rule::in(['en', 'tr', 'de', 'fr', 'es', 'it', 'ru', 'pt-br', 'nl', 'pl', 'cs', 'zh'])],
        ]);

        $this->writeEnv([
            'APP_URL' => $data['app_url'],
            'APP_NAME' => '"'.str_replace('"', '\"', $data['app_name']).'"',
            'APP_LOCALE' => $data['app_locale'],
            'APP_DEBUG' => 'false',
            'APP_ENV' => 'production',
        ]);

        Language::where('is_default', true)->update(['is_default' => false]);
        Language::where('code', $data['app_locale'])->update([
            'is_active' => true,
            'is_default' => true,
        ]);
        Setting::set('DefaultLanguage', $data['app_locale'], 'language');
        if ($adminId = $request->session()->get('install.admin_id')) {
            Admin::whereKey($adminId)->update(['language' => $data['app_locale']]);
        }

        Artisan::call('config:clear');
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        // Create lock file to permanently disable wizard.
        @file_put_contents(storage_path('installed.lock'), date('c'));
        $request->session()->forget('install.in_progress');

        // Use current request's scheme+host (bypass stale URL generator).
        return redirect($request->getSchemeAndHttpHost().'/install/finish');
    }

    // ─── Step 5: Finish ─────────────────────────────────────────────────
    public function finish()
    {
        return view('install.finish', [
            'username' => session('install.admin_username', 'admin'),
            // This page stays open after the install; only the session that
            // installed sees the server path in the cron line.
            'path' => session('install.admin_id') ? base_path() : '/path/to/pnlcs',
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────
    private function writeEnv(array $values): void
    {
        $path = base_path('.env');
        if (! file_exists($path)) {
            copy(base_path('.env.example'), $path);
        }
        $content = file_get_contents($path);

        // Values are quoted and written literally: an unquoted secret with a
        // '#' or a space was cut short by the parser and finished the install
        // with the wrong password, and a value carrying $1 was mangled by
        // preg_replace's back-references. See EnvWriter.
        $content = \App\Support\EnvWriter::apply($content, $values);

        file_put_contents($path, $content);
    }
}
