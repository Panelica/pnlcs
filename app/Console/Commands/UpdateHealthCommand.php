<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The last step of an update: does the new version work? The database
 * answers, no migration is left over, and the home page, the client login and
 * the admin login render (with the active theme) without a server error. Run
 * by the updater in a fresh process, so it is the new code that answers; a
 * failure rolls the update back.
 */
class UpdateHealthCommand extends Command
{
    protected $signature = 'pnlcs:update-health';

    protected $description = 'Check that this installation works (run by the updater after an update)';

    public function handle(): int
    {
        $failures = [];

        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            $failures[] = 'database: '.$e->getMessage();
        }

        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
            $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
            if ($pending !== []) {
                $failures[] = 'migrations not run: '.implode(', ', array_slice($pending, 0, 5));
            }
        } catch (Throwable $e) {
            $failures[] = 'migrations: '.$e->getMessage();
        }

        $cookies = [];
        $down = storage_path('framework/down');
        if (is_file($down)) {
            $data = json_decode((string) file_get_contents($down), true);
            if (! empty($data['secret'])) {
                $cookies['laravel_maintenance'] = MaintenanceModeBypassCookie::create($data['secret'])->getValue();
            }
        }

        $url = parse_url((string) config('app.url')) ?: [];
        $server = ['HTTP_HOST' => $url['host'] ?? 'localhost', 'HTTPS' => ($url['scheme'] ?? 'http') === 'https' ? 'on' : 'off', 'SERVER_PORT' => $url['port'] ?? (($url['scheme'] ?? 'http') === 'https' ? 443 : 80)];
        $kernel = app(Kernel::class);

        foreach (['/', '/client/login', '/admin/login'] as $uri) {
            try {
                $request = Request::create($uri, 'GET', [], $cookies, [], $server);
                $response = $kernel->handle($request);
                $status = $response->getStatusCode();
                if ($status >= 500) {
                    $exception = $response->exception ?? null;
                    $failures[] = "{$uri}: HTTP {$status}".($exception ? ' '.get_class($exception).': '.$exception->getMessage() : '');
                }
                $kernel->terminate($request, $response);
            } catch (Throwable $e) {
                $failures[] = "{$uri}: ".$e->getMessage();
            }
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return self::FAILURE;
        }

        $this->info('healthy');

        return self::SUCCESS;
    }
}
