<?php

namespace App\Services\Updates;

/**
 * What an update would do to the files of an installation, decided before
 * anything is touched.
 *
 * Conflicts are listed, never decided: an update with an unresolved conflict
 * does not start (RELEASING.md, promise 4).
 */
final class UpdatePlan
{
    /** A file the operator changed and the new version changed in the same place. */
    public const BOTH_CHANGED = 'both_changed';

    /** A file the operator changed and the new version no longer has. */
    public const REMOVED_IN_NEW = 'removed_in_new';

    /** A file the operator deleted and the new version changed. */
    public const DELETED_BY_OPERATOR = 'deleted_by_operator';

    /** The new version adds a file where the operator already has one of their own. */
    public const FILE_IN_THE_WAY = 'file_in_the_way';

    /** The new version adds files inside a directory of the operator's own (a theme, a module). */
    public const OPERATOR_DIRECTORY = 'operator_directory';

    /** Resolutions an operator can choose for a conflict. */
    public const TAKE_NEW = 'new';

    public const KEEP_MINE = 'mine';

    /** @var array<int, array{type: string, path: string, source?: string, content?: string}> */
    public array $actions = [];

    /** @var array<int, array{path: string, kind: string, merged?: string}> */
    public array $conflicts = [];

    /** @var array<int, string> files where the operator's change and the new version were merged */
    public array $merged = [];

    /** @var array<int, string> files the operator changed that the new version leaves alone: kept as they are */
    public array $kept = [];

    /** @var array<int, array{path: string, resolution: string}> conflicts the operator resolved */
    public array $resolved = [];

    /** @var array<int, string> directories rebuilt whole (vendor, public/build) */
    public array $replaceDirectories = [];

    public function write(string $path, string $source, ?string $content = null): void
    {
        $this->actions[] = array_filter(['type' => 'write', 'path' => $path, 'source' => $source, 'content' => $content], fn ($v) => $v !== null);
    }

    public function delete(string $path): void
    {
        $this->actions[] = ['type' => 'delete', 'path' => $path];
    }

    public function conflict(string $path, string $kind, ?string $merged = null): void
    {
        $this->conflicts[] = array_filter(['path' => $path, 'kind' => $kind, 'merged' => $merged], fn ($v) => $v !== null);
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }

    /** @return array<int, string> */
    public function pathsToWrite(): array
    {
        return array_column(array_filter($this->actions, fn ($a) => $a['type'] === 'write'), 'path');
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $count = fn (string $type) => count(array_filter($this->actions, fn ($a) => $a['type'] === $type));

        return [
            'write' => $count('write'),
            'delete' => $count('delete'),
            'merged' => $this->merged,
            'kept' => $this->kept,
            'resolved' => $this->resolved,
            'conflicts' => array_map(fn ($c) => ['path' => $c['path'], 'kind' => $c['kind']], $this->conflicts),
            'replace_directories' => $this->replaceDirectories,
        ];
    }
}
