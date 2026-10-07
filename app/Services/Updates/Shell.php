<?php

namespace App\Services\Updates;

use Symfony\Component\Process\Process;

/**
 * Runs the updater's commands (git, tar, php) without the system temporary
 * directory.
 *
 * A Symfony Process keeps output in php://temp, which spills to a temporary
 * file after 1 MB - and under a hosting account's cron the system temporary
 * directory is often not writable (/tmp owned by root, 755). The output is
 * collected here instead, and the command is given a temporary directory it
 * can write to.
 */
final class Shell
{
    private static ?string $tempDir = null;

    /**
     * @param  array<int, string>  $command
     * @param  callable(string, string): void|null  $onOutput  called with (Process::OUT|Process::ERR, chunk)
     * @return array{code: int, out: string, err: string}
     */
    public static function run(array $command, ?string $cwd = null, ?string $input = null, int $timeout = 120, ?callable $onOutput = null): array
    {
        $out = '';
        $err = '';
        $process = new Process($command, $cwd, self::environment(), $input, $timeout);
        $process->disableOutput();
        $process->run(function (string $type, string $chunk) use (&$out, &$err, $onOutput) {
            $type === Process::ERR ? $err .= $chunk : $out .= $chunk;
            if ($onOutput) {
                $onOutput($type, $chunk);
            }
        });

        return ['code' => (int) $process->getExitCode(), 'out' => $out, 'err' => $err];
    }

    /** A temporary directory this process and its commands can write to. */
    public static function tempDir(): string
    {
        if (self::$tempDir !== null) {
            return self::$tempDir;
        }

        $system = sys_get_temp_dir();
        if (self::writable($system)) {
            return self::$tempDir = $system;
        }

        $own = rtrim((string) config('updates.path', storage_path('app/pnlcs-update')), '/').'/tmp';
        if (! is_dir($own)) {
            @mkdir($own, 0700, true);
        }

        return self::$tempDir = $own;
    }

    /** @return array<string, string>|null null keeps the inherited environment as it is */
    private static function environment(): ?array
    {
        $dir = self::tempDir();

        return $dir === sys_get_temp_dir() ? null : ['TMPDIR' => $dir, 'TMP' => $dir, 'TEMP' => $dir];
    }

    /** is_writable() says yes to root everywhere; only a file that can be made proves it. */
    private static function writable(string $dir): bool
    {
        $probe = @tempnam($dir, 'pnlcs');
        if ($probe === false || ! str_starts_with($probe, rtrim($dir, '/').'/')) {
            if ($probe !== false) {
                @unlink($probe);
            }

            return false;
        }
        @unlink($probe);

        return true;
    }
}
