<?php

/*
 * Compares two snapshots (snapshot.php). Prints what differs; exits 0 when
 * nothing does, 1 otherwise. --files-only / --tables-only narrow it;
 * --ignore=<prefix> leaves paths out (repeatable); --content-only=<prefix>
 * compares content but not permissions there (the Docker entrypoint makes
 * storage/ group-writable on every start, as image 1.4 already did);
 * --ignore-table=<name> leaves out a table the running application writes to
 * by itself (the activity log, the scheduler's own runs); --ignore-row=<key>
 * leaves out one row of the settings table (settings.LastCronRun, the
 * scheduler's heartbeat, written every minute).
 */

$args = array_slice($argv, 1);
$opts = array_filter($args, fn ($a) => str_starts_with($a, '--'));
$paths = array_values(array_diff($args, $opts));
$a = json_decode(file_get_contents($paths[0]), true);
$b = json_decode(file_get_contents($paths[1]), true);
$ignore = [];
$contentOnly = [];
$ignoreTables = [];
$ignoreRows = [];
foreach ($opts as $o) {
    if (str_starts_with($o, '--ignore=')) {
        $ignore[] = substr($o, 9);
    }
    if (str_starts_with($o, '--content-only=')) {
        $contentOnly[] = substr($o, 15);
    }
    if (str_starts_with($o, '--ignore-row=')) {
        $ignoreRows[] = substr($o, 13);
    }
    if (str_starts_with($o, '--ignore-table=')) {
        $ignoreTables[] = substr($o, 15);
    }
}
$fp = function (?string $value, string $path) use ($contentOnly) {
    if ($value !== null && array_filter($contentOnly, fn ($p) => str_starts_with($path, $p)) && preg_match('/^[0-7]+:(.*)$/', $value, $m)) {
        return $m[1];
    }

    return $value;
};
$keep = fn ($p) => ! array_filter($ignore, fn ($i) => str_starts_with($p, $i));
$diff = [];

if (! in_array('--tables-only', $opts, true)) {
    foreach (array_unique(array_merge(array_keys($a['files']), array_keys($b['files']))) as $p) {
        if ($keep($p) && $fp($a['files'][$p] ?? null, $p) !== $fp($b['files'][$p] ?? null, $p)) {
            $diff[] = 'file  '.$p.'  '.(isset($a['files'][$p]) ? '' : '(added)').(isset($b['files'][$p]) ? '' : '(removed)');
        }
    }
}
if (! in_array('--files-only', $opts, true)) {
    foreach (array_unique(array_merge(array_keys($a['tables']), array_keys($b['tables']))) as $t) {
        // A table whose only difference is an ignored row is compared row by row below.
        $onlyIgnoredRows = $t === 'settings' && $ignoreRows !== [] && array_diff_assoc(array_diff_key($a['rows'] ?? [], array_flip($ignoreRows)), array_diff_key($b['rows'] ?? [], array_flip($ignoreRows))) === [];
        if (! in_array($t, $ignoreTables, true) && ! $onlyIgnoredRows && ($a['tables'][$t] ?? null) !== ($b['tables'][$t] ?? null)) {
            $diff[] = 'table '.$t.'  '.(isset($a['tables'][$t]) ? '' : '(added)').(isset($b['tables'][$t]) ? '' : '(removed)');
        }
    }
}

if (! in_array('--files-only', $opts, true)) {
    foreach (array_unique(array_merge(array_keys($a['rows'] ?? []), array_keys($b['rows'] ?? []))) as $r) {
        if (! in_array($r, $ignoreRows, true) && ($a['rows'][$r] ?? null) !== ($b['rows'][$r] ?? null)) {
            $diff[] = '  row '.$r;
        }
    }
}

foreach (array_slice($diff, 0, 40) as $line) {
    echo $line, "\n";
}
if (count($diff) > 40) {
    echo '... and ', count($diff) - 40, " more\n";
}
exit($diff === [] ? 0 : 1);
