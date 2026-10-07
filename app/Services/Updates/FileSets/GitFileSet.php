<?php

namespace App\Services\Updates\FileSets;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The commit an installation made with `git clone` was checked out at. Before
 * release packages existed every installation was a clone of main; this is
 * the version its files are compared against on its first packaged update.
 */
final class GitFileSet implements FileSet
{
    /** @var array<string, string>|null path => fingerprint */
    private ?array $entries = null;

    /** @var array<string, string> path => blob id */
    private array $blobs = [];

    /** @var array<string, string> path => content */
    private array $contents = [];

    public function __construct(private readonly string $root, private readonly string $revision = 'HEAD') {}

    public function commit(): string
    {
        return trim($this->git(['rev-parse', $this->revision]));
    }

    public function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $links = [];
        foreach (explode("\0", rtrim($this->git(['ls-tree', '-r', '-z', '--full-tree', $this->revision]), "\0")) as $line) {
            if ($line === '' || ! preg_match('/^(\d+) (\w+) ([0-9a-f]+)\t(.+)$/s', $line, $m) || $m[2] !== 'blob') {
                continue;
            }
            $this->blobs[$m[4]] = $m[3];
            if ($m[1] === '120000') {
                $links[$m[4]] = true;
            }
        }

        // One `git cat-file --batch` for every blob: a process per file takes
        // minutes on a slow disk.
        $this->contents = $this->readBlobs($this->blobs);
        $this->entries = [];

        foreach ($this->contents as $path => $content) {
            $this->entries[$path] = isset($links[$path]) ? 'link:'.$content : Fingerprint::ofContent($content);
        }

        return $this->entries;
    }

    public function read(string $path): ?string
    {
        $this->entries();

        return $this->contents[$path] ?? null;
    }

    /**
     * @param  array<string, string>  $blobs
     * @return array<string, string>
     */
    private function readBlobs(array $blobs): array
    {
        $process = new Process(['git', '-c', 'safe.directory=*', '-C', $this->root, 'cat-file', '--batch'], null, null, implode("\n", array_values($blobs))."\n", 600);
        $process->mustRun();
        $output = $process->getOutput();

        $contents = [];
        $offset = 0;
        foreach ($blobs as $path => $id) {
            $eol = strpos($output, "\n", $offset);
            if ($eol === false) {
                throw new RuntimeException('git cat-file ended early.');
            }
            [$sha, , $size] = explode(' ', substr($output, $offset, $eol - $offset)) + [null, null, null];
            if ($sha !== $id || ! is_numeric($size)) {
                throw new RuntimeException("git cat-file answered {$sha} for {$id}.");
            }
            $contents[$path] = substr($output, $eol + 1, (int) $size);
            $offset = $eol + 1 + (int) $size + 1;
        }

        return $contents;
    }

    /** @param array<int, string> $args */
    private function git(array $args): string
    {
        // safe.directory: the clone often belongs to another user than the one
        // updating (root cloned it, www-data runs it), and git then refuses to
        // read it at all ("dubious ownership"). Reading is all that is done here.
        $process = new Process(array_merge(['git', '-c', 'safe.directory=*', '-C', $this->root], $args), null, null, null, 120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('git '.implode(' ', $args).': '.trim($process->getErrorOutput()));
        }

        return $process->getOutput();
    }
}
