<?php

namespace App\Services\Updates\FileSets;

use App\Services\Updates\Shell;
use RuntimeException;

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
        $ignored = $this->exportIgnored();
        foreach (explode("\0", rtrim($this->git(['ls-tree', '-r', '-z', '--full-tree', $this->revision]), "\0")) as $line) {
            if ($line === '' || ! preg_match('/^(\d+) (\w+) ([0-9a-f]+)\t(.+)$/s', $line, $m) || $m[2] !== 'blob') {
                continue;
            }
            // What a release package leaves out (export-ignore) was never part
            // of what an update ships or removes.
            if ($this->isExportIgnored($m[4], $ignored)) {
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
        if ($blobs === []) {
            return [];
        }
        $run = Shell::run(['git', '-c', 'safe.directory=*', '-C', $this->root, 'cat-file', '--batch'], null, implode("\n", array_values($blobs))."\n", 600);
        if ($run['code'] !== 0) {
            throw new RuntimeException('git cat-file: '.trim($run['err']));
        }
        $output = $run['out'];

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
        $run = Shell::run(array_merge(['git', '-c', 'safe.directory=*', '-C', $this->root], $args), null, null, 120);

        if ($run['code'] !== 0) {
            throw new RuntimeException('git '.implode(' ', $args).': '.trim($run['err']));
        }

        return $run['out'];
    }

    /**
     * The export-ignore patterns of the commit's own .gitattributes - the
     * paths `git archive`, and so every release package, leaves out.
     *
     * @return array<int, string>
     */
    private function exportIgnored(): array
    {
        $run = Shell::run(['git', '-c', 'safe.directory=*', '-C', $this->root, 'show', $this->revision.':.gitattributes'], null, null, 60);
        if ($run['code'] !== 0) {
            return [];
        }

        $patterns = [];
        foreach (preg_split('/\R/', $run['out']) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = preg_split('/\s+/', $line);
            $pattern = array_shift($parts);
            if (in_array('export-ignore', $parts, true)) {
                $patterns[] = $pattern;
            }
        }

        return $patterns;
    }

    /**
     * gitattributes matching: a pattern with a slash is anchored at the root, one
     * without matches a name at any depth; a directory's attribute covers
     * everything in it.
     *
     * @param  array<int, string>  $patterns
     */
    private function isExportIgnored(string $path, array $patterns): bool
    {
        if ($patterns === []) {
            return false;
        }

        $segments = explode('/', $path);
        for ($i = count($segments); $i >= 1; $i--) {
            $candidate = implode('/', array_slice($segments, 0, $i));
            foreach ($patterns as $pattern) {
                $anchored = str_contains(rtrim($pattern, '/'), '/');
                $glob = ltrim(rtrim($pattern, '/'), '/');
                if ($anchored ? fnmatch($glob, $candidate, FNM_PATHNAME) : fnmatch($glob, $segments[$i - 1])) {
                    return true;
                }
            }
        }

        return false;
    }
}
