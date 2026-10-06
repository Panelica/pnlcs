<?php

namespace App\Services\Updates;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Carries the operator's change to a file over to the new version of it, the
 * way git does when two branches touched the same file: changes to different
 * lines both survive; changes to the same lines are a conflict, and the
 * result then holds both, between conflict markers, for a person to resolve.
 */
class ThreeWayMerge
{
    public function __construct(private readonly ?string $workDir = null) {}

    public function available(): bool
    {
        return (new ExecutableFinder)->find('git') !== null;
    }

    /**
     * @return array{clean: bool, content: string}
     */
    public function merge(string $base, string $ours, string $theirs): array
    {
        if ($this->isBinary($base) || $this->isBinary($ours) || $this->isBinary($theirs) || ! $this->available()) {
            return ['clean' => false, 'content' => $ours];
        }

        // Not sys_get_temp_dir(): under cron an open_basedir can put it out of
        // reach (DbBackupCommand ran into exactly that). Our own storage is
        // writable and private.
        $dir = ($this->workDir ?? storage_path('app/pnlcs-update/tmp')).'/merge-'.bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        try {
            file_put_contents("{$dir}/ours", $ours);
            file_put_contents("{$dir}/base", $base);
            file_put_contents("{$dir}/theirs", $theirs);

            $process = new Process([
                'git', 'merge-file', '-p',
                '-L', 'your version', '-L', 'installed version', '-L', 'new version',
                "{$dir}/ours", "{$dir}/base", "{$dir}/theirs",
            ], null, null, null, 60);
            $process->run();

            // The exit code is the number of conflicts; above 127 is an error.
            $code = (int) $process->getExitCode();

            if ($code > 127) {
                return ['clean' => false, 'content' => $ours];
            }

            return ['clean' => $code === 0, 'content' => $process->getOutput()];
        } finally {
            foreach (['ours', 'base', 'theirs'] as $f) {
                @unlink("{$dir}/{$f}");
            }
            @rmdir($dir);
        }
    }

    public function isBinary(string $content): bool
    {
        return str_contains(substr($content, 0, 8000), "\0");
    }
}
