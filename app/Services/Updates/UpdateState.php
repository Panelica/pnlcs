<?php

namespace App\Services\Updates;

use RuntimeException;

/**
 * The updater's own records, under storage/app/pnlcs-update: the run in
 * progress, the status the admin area polls, the last check, the history, and
 * the operator's decisions on conflicts. Files, not database rows: a run that
 * has to restore the database must not restore away its own record.
 */
class UpdateState
{
    /** @var resource|null */
    private $lock = null;

    public function __construct(private readonly ?string $path = null) {}

    public function path(string $relative = ''): string
    {
        $base = rtrim($this->path ?? (string) config('updates.path'), '/');
        if (! is_dir($base)) {
            mkdir($base, 0750, true);
        }

        return $relative === '' ? $base : "{$base}/{$relative}";
    }

    /** One update at a time, whoever starts it. */
    public function acquireLock(): void
    {
        $this->lock = fopen($this->path('update.lock'), 'c');
        if ($this->lock === false || ! flock($this->lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another update is running.');
        }
    }

    public function releaseLock(): void
    {
        if ($this->lock) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
            $this->lock = null;
        }
    }

    public function isLocked(): bool
    {
        $handle = @fopen($this->path('update.lock'), 'c');
        if ($handle === false) {
            return false;
        }
        $free = flock($handle, LOCK_EX | LOCK_NB);
        if ($free) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return ! $free;
    }

    /** @return array<string, mixed>|null */
    public function read(string $file): ?array
    {
        $full = $this->path($file);
        $data = is_file($full) ? json_decode((string) file_get_contents($full), true) : null;

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $data */
    public function write(string $file, array $data): void
    {
        $full = $this->path($file);
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0750, true);
        }
        $tmp = $full.'.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        rename($tmp, $full);
    }

    /**
     * Takes a file for this process alone: of two processes that claim it at
     * once (the request started right away and the scheduler's minute), only
     * one gets it.
     *
     * @return array<string, mixed>|null
     */
    public function claim(string $file): ?array
    {
        $full = $this->path($file);
        $claimed = $full.'.'.getmypid().'.'.bin2hex(random_bytes(4));

        if (! @rename($full, $claimed)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($claimed), true);
        @unlink($claimed);

        return is_array($data) ? $data : null;
    }

    public function forget(string $file): void
    {
        @unlink($this->path($file));
    }

    /** What the admin area shows while an update is prepared or applied. */
    public function status(string $state, string $step, array $extra = []): void
    {
        $this->write('status.json', ['state' => $state, 'step' => $step, 'updated_at' => now()->toIso8601String()] + $extra);
    }

    /** @param array<string, mixed> $entry */
    public function addHistory(array $entry): void
    {
        $history = $this->read('history.json') ?? [];
        array_unshift($history, $entry);
        $this->write('history.json', array_slice($history, 0, 50));
    }

    /** @return array<int, array<string, mixed>> */
    public function history(): array
    {
        return $this->read('history.json') ?? [];
    }

    /**
     * The operator's decisions on the conflicts of an update to $version:
     * path => "new", "mine", or the content of the file they edited.
     *
     * @return array<string, string>
     */
    public function resolutions(string $version): array
    {
        $out = [];
        foreach ($this->read("resolutions/{$version}.json") ?? [] as $path => $choice) {
            if ($choice === 'edited') {
                $file = $this->path("resolutions/{$version}/files/{$path}");
                if (is_file($file)) {
                    $out[$path] = (string) file_get_contents($file);
                }
            } elseif (in_array($choice, [UpdatePlan::TAKE_NEW, UpdatePlan::KEEP_MINE], true)) {
                $out[$path] = $choice;
            }
        }

        return $out;
    }

    public function resolve(string $version, string $path, string $choice, ?string $editedContent = null): void
    {
        self::assertSafe($version, $path);
        $choices = $this->read("resolutions/{$version}.json") ?? [];
        $choices[$path] = $editedContent !== null ? 'edited' : $choice;
        if ($editedContent !== null) {
            $file = $this->path("resolutions/{$version}/files/{$path}");
            @mkdir(dirname($file), 0750, true);
            file_put_contents($file, $editedContent);
        }
        $this->write("resolutions/{$version}.json", $choices);
    }

    public function clearResolution(string $version, string $path): void
    {
        self::assertSafe($version, $path);
        $choices = $this->read("resolutions/{$version}.json") ?? [];
        unset($choices[$path]);
        $this->write("resolutions/{$version}.json", $choices);
        @unlink($this->path("resolutions/{$version}/files/{$path}"));
    }

    /** A version and a path that stay inside the updater's directory. */
    public static function assertSafe(string $version, string $path): void
    {
        if (Version::parse($version) === null || $path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path) || str_starts_with($path, '/')) {
            throw new RuntimeException('Not a path of this installation.');
        }
    }
}
