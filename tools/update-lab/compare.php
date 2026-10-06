<?php

/*
 * Compares two snapshots (snapshot.php). Prints what differs; exits 0 when
 * nothing does, 1 otherwise. --files-only / --tables-only narrow it;
 * --ignore=<prefix> leaves paths out (repeatable).
 */

$args = array_slice($argv, 1);
$opts = array_filter($args, fn ($a) => str_starts_with($a, '--'));
$paths = array_values(array_diff($args, $opts));
$a = json_decode(file_get_contents($paths[0]), true);
$b = json_decode(file_get_contents($paths[1]), true);
$ignore = [];
foreach ($opts as $o) {
    if (str_starts_with($o, '--ignore=')) {
        $ignore[] = substr($o, 9);
    }
}
$keep = fn ($p) => ! array_filter($ignore, fn ($i) => str_starts_with($p, $i));
$diff = [];

if (! in_array('--tables-only', $opts, true)) {
    foreach (array_unique(array_merge(array_keys($a['files']), array_keys($b['files']))) as $p) {
        if ($keep($p) && ($a['files'][$p] ?? null) !== ($b['files'][$p] ?? null)) {
            $diff[] = 'file  '.$p.'  '.(isset($a['files'][$p]) ? '' : '(added)').(isset($b['files'][$p]) ? '' : '(removed)');
        }
    }
}
if (! in_array('--files-only', $opts, true)) {
    foreach (array_unique(array_merge(array_keys($a['tables']), array_keys($b['tables']))) as $t) {
        if (($a['tables'][$t] ?? null) !== ($b['tables'][$t] ?? null)) {
            $diff[] = 'table '.$t.'  '.(isset($a['tables'][$t]) ? '' : '(added)').(isset($b['tables'][$t]) ? '' : '(removed)');
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
