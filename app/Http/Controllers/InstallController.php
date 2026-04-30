<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
            'storage/'                => is_writable(storage_path()),
            'storage/framework/'      => is_writable(storage_path('framework')),
            'storage/logs/'           => is_writable(storage_path('logs')),
            'bootstrap/cache/'        => is_writable(base_path('bootstrap/cache')),
        ];

        $assets = [
            'vendor/'        => is_dir(base_path('vendor')) && file_exists(base_path('vendor/autoload.php')),
            'public/build/'  => is_dir(base_path('public/build')),
            '.env'           => file_exists(base_path('.env')),
        ];

        $allOk = $phpOk
            && !in_array(false, $extensions, true)
            && !in_array(false, $writable, true)
            && !in_array(false, $assets, true);

        return view('install.requirements', compact('php', 'phpOk', 'extensions', 'writable', 'assets', 'allOk'));
    }

    // ─── Step 2: Database ───────────────────────────────────────────────
    public function database()
    {
        // If migrations already applied (Docker case), skip DB step.
        try {
            if (Schema::hasTable('migrations')) {
                return redirect('/install/admin');
            }
        } catch (\Throwable $e) { /* DB not configured yet */ }

        return view('install.database', [
            'host'     => config('database.connections.mysql.host', '127.0.0.1'),
            'port'     => config('database.connections.mysql.port', '3306'),
            'database' => config('database.connections.mysql.database', 'pnlcs'),
            'username' => config('database.connections.mysql.username', 'pnlcs'),
        ]);
    }

    public function testDatabase(Request $request)
    {
        $data = $request->validate([
            'host'     => 'required|string|max:255',
            'port'     => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
        ]);

        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s', $data['host'], (int) $data['port'], $data['database']);
            $pdo = new \PDO($dsn, $data['username'], $data['password'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->query('SELECT 1');
            return response()->json(['ok' => true, 'message' => 'Connection successful.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveDatabase(Request $request)
    {
        $data = $request->validate([
            'host'     => 'required|string|max:255',
            'port'     => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
        ]);

        $this->writeEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST'       => $data['host'],
            'DB_PORT'       => (string) $data['port'],
            'DB_DATABASE'   => $data['database'],
            'DB_USERNAME'   => $data['username'],
            'DB_PASSWORD'   => $data['password'] ?? '',
        ]);

        // Reload config + reset connection so artisan migrate uses new creds.
        Artisan::call('config:clear');
        DB::purge('mysql');
        config([
            'database.connections.mysql.host'     => $data['host'],
            'database.connections.mysql.port'     => (int) $data['port'],
            'database.connections.mysql.database' => $data['database'],
            'database.connections.mysql.username' => $data['username'],
            'database.connections.mysql.password' => $data['password'] ?? '',
        ]);

        try {
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['migrate' => 'Migration failed: ' . $e->getMessage()]);
        }

        return redirect('/install/admin');
    }

    // ─── Step 3: Admin account ──────────────────────────────────────────
    public function admin()
    {
        return view('install.admin');
    }

    public function saveAdmin(Request $request)
    {
        $data = $request->validate([
            'username'   => 'required|string|min:3|max:64|alpha_dash',
            'email'      => 'required|email|max:255',
            'first_name' => 'required|string|max:64',
            'last_name'  => 'nullable|string|max:64',
            'password'   => 'required|string|min:6|max:255|confirmed',
        ]);

        // Run db:seed first so AdminRole + base data exist (idempotent — firstOrCreate).
        try {
            Artisan::call('db:seed', ['--force' => true, '--no-interaction' => true]);
        } catch (\Throwable $e) {
            // Continue — seeder failure shouldn't block admin creation if role already exists.
        }

        $role = \App\Models\AdminRole::query()->orderBy('id')->first();
        if (!$role) {
            $role = \App\Models\AdminRole::create([
                'name' => 'Super Administrator',
                'slug' => 'super-admin',
                'permissions' => ['*'],
            ]);
        }

        \App\Models\Admin::updateOrCreate(
            ['username' => $data['username']],
            [
                'role_id'    => $role->id,
                'email'      => $data['email'],
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'] ?? '',
                'password'   => Hash::make($data['password']),
            ]
        );

        session(['install.admin_username' => $data['username']]);

        return redirect('/install/app');
    }

    // ─── Step 4: App settings ───────────────────────────────────────────
    public function app()
    {
        return view('install.app', [
            'app_url'    => config('app.url', 'http://localhost'),
            'app_name'   => config('app.name', 'PNLCS'),
            'app_locale' => config('app.locale', 'en'),
        ]);
    }

    public function saveApp(Request $request)
    {
        $data = $request->validate([
            'app_url'    => 'required|url|max:255',
            'app_name'   => 'required|string|max:64',
            'app_locale' => ['required', Rule::in(['en', 'tr', 'de', 'fr', 'es', 'it', 'ru', 'pt-br', 'nl', 'pl', 'cs', 'tr'])],
        ]);

        $this->writeEnv([
            'APP_URL'    => $data['app_url'],
            'APP_NAME'   => '"' . str_replace('"', '\"', $data['app_name']) . '"',
            'APP_LOCALE' => $data['app_locale'],
            'APP_DEBUG'  => 'false',
            'APP_ENV'    => 'production',
        ]);

        Artisan::call('config:clear');
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        // Create lock file to permanently disable wizard.
        @file_put_contents(storage_path('installed.lock'), date('c'));

        return redirect('/install/finish');
    }

    // ─── Step 5: Finish ─────────────────────────────────────────────────
    public function finish()
    {
        return view('install.finish', [
            'username' => session('install.admin_username', 'admin'),
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────
    private function writeEnv(array $values): void
    {
        $path = base_path('.env');
        if (!file_exists($path)) {
            copy(base_path('.env.example'), $path);
        }
        $content = file_get_contents($path);

        foreach ($values as $key => $val) {
            $line = "{$key}={$val}";
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", $line, $content);
            } else {
                $content .= "\n" . $line;
            }
        }
        file_put_contents($path, $content);
    }
}
