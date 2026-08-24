<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DbBackupCommand extends Command
{
    protected $signature = 'pnlcs:db-backup
        {--dir= : Target directory (default: storage/app/backups/db)}
        {--retention= : Number of backups to keep (default: setting db_backup_retention or 7)}
        {--php : Dump over the PHP connection even when a dump binary exists}';

    protected $description = 'Dump the database to a gzip file with rotation';

    public function handle(): int
    {
        if (Setting::get('db_backup_enabled', '1') !== '1') {
            $this->info('Database backups are disabled (setting db_backup_enabled).');
            return self::SUCCESS;
        }

        $dir = rtrim($this->option('dir') ?: storage_path('app/backups/db'), '/');
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            $this->error("Cannot create backup directory: {$dir}");
            return self::FAILURE;
        }

        $db = config('database.connections.mysql');
        $file = $dir . '/pnlcs-' . now()->format('Ymd-His') . '.sql.gz';

        $cnf = $this->writeCredentialFile($db);

        try {
            $dumpBin = $this->option('php') ? null : $this->findMysqldump();
            if (!$dumpBin) {
                // The billing container ships without a client toolset, so this
                // command failed silently every night since the install - the
                // knowledge base written by hand died with the database and
                // there was not one backup to restore it from. No binary means
                // PHP dumps the database itself over the connection it already
                // has: slower, but a backup that exists.
                $this->warn('mysqldump not found - falling back to a PHP dump over the DB connection.');

                if (! $this->phpDump($file)) {
                    Log::error('DB backup failed', ['error' => 'php fallback dump failed']);
                    app(NotificationService::class)->dispatch('backup.failed', [
                        'event_type' => 'backup.failed',
                        'subject'    => 'Database backup FAILED',
                        'message'    => 'Database backup failed: php fallback dump failed',
                    ]);
                    $this->error('Backup failed: php fallback dump failed.');
                    return self::FAILURE;
                }

                $removed = $this->rotate($dir, (int) ($this->option('retention') ?: Setting::get('db_backup_retention', '7')));
                run_hook('AfterDatabaseBackup', ['file' => $file, 'size' => filesize($file)]);
                Log::info('DB backup completed', ['file' => $file, 'size' => filesize($file), 'rotated' => $removed, 'method' => 'php']);
                $this->info(sprintf('Backup written (php dump): %s (%s KB)', $file, (int) (filesize($file) / 1024)));

                return self::SUCCESS;
            }

            $cmd = sprintf(
                'set -o pipefail; %s --defaults-extra-file=%s --single-transaction --quick --routines --triggers --no-tablespaces %s | gzip > %s',
                escapeshellarg($dumpBin),
                escapeshellarg($cnf),
                escapeshellarg($db['database']),
                escapeshellarg($file)
            );

            // pipefail needs bash — /bin/sh is dash on Debian/Ubuntu.
            $process = new Process(['bash', '-c', $cmd], null, null, null, 600);
            $process->run();

            $ok = $process->isSuccessful()
                && is_file($file)
                && filesize($file) > 512
                && str_starts_with((string) file_get_contents($file, false, null, 0, 2), "\x1f\x8b");

            if (!$ok) {
                @unlink($file);
                $error = trim($process->getErrorOutput()) ?: 'unknown error';
                Log::error('DB backup failed', ['error' => $error]);
                app(NotificationService::class)->dispatch('backup.failed', [
                    'event_type' => 'backup.failed',
                    'subject'    => 'Database backup FAILED',
                    'message'    => "Database backup failed: {$error}",
                ]);
                $this->error("Backup failed: {$error}");
                return self::FAILURE;
            }
        } finally {
            @unlink($cnf);
        }

        $removed = $this->rotate($dir, (int) ($this->option('retention') ?: Setting::get('db_backup_retention', '7')));

        run_hook('AfterDatabaseBackup', ['file' => $file, 'size' => filesize($file)]);
        Log::info('DB backup completed', ['file' => $file, 'size' => filesize($file), 'rotated' => $removed]);
        $this->info(sprintf('Backup written: %s (%s KB)%s', $file, (int) (filesize($file) / 1024), $removed ? ", rotated out {$removed} old file(s)" : ''));

        return self::SUCCESS;
    }


    /**
     * A dump produced by PHP itself: schema and rows, INSERTs in batches,
     * gzip-compressed - restorable by piping into the standard client. Used
     * when no dump binary exists on this machine.
     */
    private function phpDump(string $file): bool
    {
        $gz = gzopen($file, 'wb6');
        if ($gz === false) {
            return false;
        }

        try {
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

            $tables = array_map('current', $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_NUM));
            foreach ($tables as $table) {
                $qt = '`'.str_replace('`', '``', $table).'`';
                $create = $pdo->query("SHOW CREATE TABLE {$qt}")->fetch(\PDO::FETCH_NUM)[1];
                gzwrite($gz, "DROP TABLE IF EXISTS {$qt};\n{$create};\n\n");

                $stmt = $pdo->query("SELECT * FROM {$qt}");
                $batch = [];
                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row);
                    $batch[] = '('.implode(',', $vals).')';
                    if (count($batch) >= 200) {
                        gzwrite($gz, "INSERT INTO {$qt} VALUES\n".implode(",\n", $batch).";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    gzwrite($gz, "INSERT INTO {$qt} VALUES\n".implode(",\n", $batch).";\n");
                }
                gzwrite($gz, "\n");
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($file);
            \Illuminate\Support\Facades\Log::error('PHP dump failed', ['error' => $e->getMessage()]);

            return false;
        }

        gzclose($gz);

        return is_file($file) && filesize($file) > 512;
    }

    private function findMysqldump(): ?string
    {
        foreach (['/opt/panelica/services/mysql/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/bin/mysqldump'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        $which = trim((string) shell_exec('command -v mysqldump 2>/dev/null'));

        return $which !== '' ? $which : null;
    }

    /**
     * Credentials go through a 0600 defaults file — never on the command line.
     */
    private function writeCredentialFile(array $db): string
    {
        $cnf = tempnam(sys_get_temp_dir(), 'pnlcs-dump-');
        $lines = "[client]\nuser=\"{$db['username']}\"\npassword=\"{$db['password']}\"\n";
        if (!empty($db['unix_socket'])) {
            $lines .= "socket=\"{$db['unix_socket']}\"\n";
        } else {
            $lines .= "host=\"{$db['host']}\"\nport=\"" . ($db['port'] ?? 3306) . "\"\n";
        }
        file_put_contents($cnf, $lines);
        chmod($cnf, 0600);

        return $cnf;
    }

    private function rotate(string $dir, int $keep): int
    {
        $keep = max(1, $keep);
        $files = glob($dir . '/pnlcs-*.sql.gz') ?: [];
        rsort($files); // newest first (timestamped names sort lexically)

        $removed = 0;
        foreach (array_slice($files, $keep) as $old) {
            if (@unlink($old)) {
                $removed++;
            }
        }

        return $removed;
    }
}
