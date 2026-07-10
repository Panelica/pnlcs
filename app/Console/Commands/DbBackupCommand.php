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
        {--retention= : Number of backups to keep (default: setting db_backup_retention or 7)}';

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
            $dumpBin = $this->findMysqldump();
            if (!$dumpBin) {
                $this->error('mysqldump binary not found.');
                return self::FAILURE;
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
