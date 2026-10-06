<?php

/*
 * PNLCS updater: the step that changes files.
 *
 * Runs as a plain PHP script, outside the application, because it replaces the
 * application: a Laravel process that swapped its own vendor/ directory would
 * go on to load classes from the new one into the old process.
 *
 *   php apply.php apply    <run-dir>/plan.json
 *   php apply.php rollback <run-dir>/plan.json
 *
 * Every step is written to <run-dir>/journal.jsonl before it is taken, and what
 * it replaces is kept in <run-dir>/backup first. Rollback walks the journal
 * backwards, and is safe to run on a journal cut off at any point - by an error,
 * a killed process or a power cut - and safe to run twice.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 3 || ! in_array($argv[1], ['apply', 'rollback'], true)) {
    fwrite(STDERR, "usage: php apply.php apply|rollback <plan.json>\n");
    exit(64);
}

$plan = json_decode((string) file_get_contents($argv[2]), true);
if (! is_array($plan) || ! isset($plan['root'], $plan['run_dir'], $plan['new_root'], $plan['actions'])) {
    fwrite(STDERR, "unreadable plan: {$argv[2]}\n");
    exit(65);
}

$root = rtrim($plan['root'], '/');
$run = rtrim($plan['run_dir'], '/');
$journal = "{$run}/journal.jsonl";
$backup = "{$run}/backup";

try {
    if ($argv[1] === 'apply') {
        pnlcs_apply($plan, $root, $run, $journal, $backup);
        echo "applied\n";
    } else {
        pnlcs_rollback($root, $run, $journal);
        echo "rolled back\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}

function pnlcs_apply(array $plan, string $root, string $run, string $journal, string $backup): void
{
    if (is_file($journal)) {
        throw new RuntimeException("{$journal} exists: this run was started before. Roll it back first.");
    }

    $newRoot = rtrim($plan['new_root'], '/');
    $keepDirectories = array_flip($plan['keep_directories'] ?? []);
    $log = fopen($journal, 'xb');

    // Fault injection for the update lab (tools/update-lab): stop after N
    // steps, the way a killed process or a full disk would.
    $failAfter = getenv('PNLCS_UPDATE_FAIL_AFTER');
    $steps = 0;

    foreach ($plan['actions'] as $action) {
        if ($failAfter !== false && $failAfter !== '' && $steps++ >= (int) $failAfter) {
            throw new RuntimeException("stopped after {$failAfter} steps (PNLCS_UPDATE_FAIL_AFTER)");
        }

        $path = pnlcs_safe_path($action['path']);
        $target = "{$root}/{$path}";

        pnlcs_ensure_directory($log, $root, dirname($path));

        if ($action['type'] === 'write') {
            $source = $action['source'] === 'new' ? "{$newRoot}/{$path}" : $action['from'];
            $existed = file_exists($target) || is_link($target);

            if ($existed) {
                pnlcs_copy_aside($target, "{$backup}/{$path}");
            }
            pnlcs_journal($log, ['op' => 'write', 'path' => $path, 'existed' => $existed]);
            pnlcs_put($source, $target, $existed ? $target : null);
        } elseif ($action['type'] === 'delete') {
            pnlcs_journal($log, ['op' => 'delete', 'path' => $path]);
            pnlcs_move($target, "{$backup}/{$path}");
            pnlcs_remove_empty_parents($log, $root, dirname($path), $keepDirectories);
        } else {
            throw new RuntimeException("unknown action {$action['type']}");
        }
    }

    foreach ($plan['replace_directories'] ?? [] as $dir) {
        if ($failAfter !== false && $failAfter !== '' && $steps++ >= (int) $failAfter) {
            throw new RuntimeException("stopped after {$failAfter} steps (PNLCS_UPDATE_FAIL_AFTER)");
        }

        $dir = pnlcs_safe_path($dir);
        if (! is_dir("{$newRoot}/{$dir}")) {
            continue;
        }

        pnlcs_ensure_directory($log, $root, dirname($dir));
        $existed = file_exists("{$root}/{$dir}");
        pnlcs_journal($log, ['op' => 'replace', 'path' => $dir, 'existed' => $existed]);
        if ($existed) {
            pnlcs_move("{$root}/{$dir}", "{$backup}/{$dir}");
        }
        pnlcs_move("{$newRoot}/{$dir}", "{$root}/{$dir}");
    }

    pnlcs_journal($log, ['op' => 'done']);
    fclose($log);
}

function pnlcs_rollback(string $root, string $run, string $journal): void
{
    if (! is_file($journal)) {
        return; // nothing was changed
    }

    $entries = [];
    foreach (file($journal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry)) {
            $entries[] = $entry;
        }
    }

    $backup = "{$run}/backup";
    $discard = "{$run}/discarded";

    foreach (array_reverse($entries) as $entry) {
        $path = isset($entry['path']) ? pnlcs_safe_path($entry['path']) : null;
        $target = "{$root}/{$path}";

        switch ($entry['op']) {
            case 'write':
                if ($entry['existed']) {
                    if (file_exists("{$backup}/{$path}") || is_link("{$backup}/{$path}")) {
                        pnlcs_unlink($target);
                        pnlcs_move("{$backup}/{$path}", $target);
                    }
                } else {
                    pnlcs_unlink($target);
                }
                break;

            case 'delete':
                if ((file_exists("{$backup}/{$path}") || is_link("{$backup}/{$path}")) && ! file_exists($target) && ! is_link($target)) {
                    pnlcs_move("{$backup}/{$path}", $target);
                }
                break;

            case 'replace':
                $old = "{$backup}/{$path}";
                if (! $entry['existed'] || file_exists($old)) {
                    if (file_exists($target)) {
                        @mkdir(dirname("{$discard}/{$path}"), 0700, true);
                        pnlcs_move($target, "{$discard}/{$path}");
                    }
                    if (file_exists($old)) {
                        pnlcs_move($old, $target);
                    }
                }
                break;

            case 'mkdir':
                if (is_dir($target)) {
                    @rmdir($target);
                }
                break;

            case 'rmdir':
                if (! is_dir($target)) {
                    @mkdir($target, $entry['mode'] ?? 0755);
                }
                break;
        }
    }

    file_put_contents("{$run}/rolled-back", date('c')."\n");
}

/** A path inside the installation, relative, without any way out of it. */
function pnlcs_safe_path(string $path): string
{
    $path = ltrim($path, '/');
    if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path)) {
        throw new RuntimeException("refusing path {$path}");
    }

    return $path;
}

/** @param resource $log */
function pnlcs_journal($log, array $entry): void
{
    fwrite($log, json_encode($entry, JSON_UNESCAPED_SLASHES)."\n");
    fflush($log);
    if (function_exists('fsync')) {
        fsync($log);
    }
}

/** @param resource $log */
function pnlcs_ensure_directory($log, string $root, string $dir): void
{
    if ($dir === '.' || $dir === '' || is_dir("{$root}/{$dir}")) {
        return;
    }

    pnlcs_ensure_directory($log, $root, dirname($dir));
    pnlcs_journal($log, ['op' => 'mkdir', 'path' => $dir]);
    if (! mkdir("{$root}/{$dir}", 0755) && ! is_dir("{$root}/{$dir}")) {
        throw new RuntimeException("cannot create {$dir}");
    }
}

/** @param resource $log */
function pnlcs_remove_empty_parents($log, string $root, string $dir, array $keep): void
{
    while ($dir !== '.' && $dir !== '' && ! isset($keep[$dir])) {
        $absolute = "{$root}/{$dir}";
        if (! is_dir($absolute) || is_link($absolute) || (new FilesystemIterator($absolute))->valid()) {
            return;
        }
        $mode = fileperms($absolute) & 0777;
        pnlcs_journal($log, ['op' => 'rmdir', 'path' => $dir, 'mode' => $mode]);
        rmdir($absolute);
        $dir = dirname($dir);
    }
}

function pnlcs_copy_aside(string $from, string $to): void
{
    @mkdir(dirname($to), 0700, true);
    if (is_link($from)) {
        if (! symlink((string) readlink($from), $to)) {
            throw new RuntimeException("cannot back up link {$from}");
        }

        return;
    }
    if (! copy($from, $to)) {
        throw new RuntimeException("cannot back up {$from}");
    }
    chmod($to, fileperms($from) & 0777);
}

/**
 * Writes $source over $target through a temporary file in the same directory
 * and a rename, so the target is never seen half written.
 */
function pnlcs_put(string $source, string $target, ?string $modeFrom): void
{
    $tmp = $target.'.pnlcs-update-'.bin2hex(random_bytes(4));

    if (is_link($source)) {
        if (! symlink((string) readlink($source), $tmp)) {
            throw new RuntimeException("cannot write link {$target}");
        }
    } else {
        if (! copy($source, $tmp)) {
            @unlink($tmp);
            throw new RuntimeException("cannot write {$target}");
        }
        $mode = $modeFrom !== null && file_exists($modeFrom) && ! is_link($modeFrom) ? fileperms($modeFrom) : fileperms($source);
        chmod($tmp, $mode & 0777);
    }

    if (is_dir($target) && ! is_link($target)) {
        @unlink($tmp);
        throw new RuntimeException("a directory is in the way of {$target}");
    }

    if (! rename($tmp, $target)) {
        @unlink($tmp);
        throw new RuntimeException("cannot replace {$target}");
    }
}

function pnlcs_move(string $from, string $to): void
{
    if (! file_exists($from) && ! is_link($from)) {
        return;
    }

    @mkdir(dirname($to), 0700, true);
    if (@rename($from, $to)) {
        return;
    }

    // Another filesystem: copy, then remove.
    if (is_dir($from) && ! is_link($from)) {
        $code = 0;
        exec('cp -a '.escapeshellarg($from).' '.escapeshellarg($to).' && rm -rf '.escapeshellarg($from), $out, $code);
        if ($code !== 0) {
            throw new RuntimeException("cannot move {$from}");
        }

        return;
    }
    pnlcs_copy_aside($from, $to);
    unlink($from);
}

function pnlcs_unlink(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);
    }
}
