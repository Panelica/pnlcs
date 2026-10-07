<?php

/*
 * A fingerprint of an installation: every file (but the updater's own state,
 * logs and rebuilt caches), and every database table (CHECKSUM TABLE).
 * Two snapshots that are equal are the same installation, byte for byte.
 *
 *   php snapshot.php <installation>   (JSON on stdout)
 */

$root = rtrim($argv[1] ?? '', '/');
$skip = ['storage/app/pnlcs-update', 'storage/logs', 'storage/framework', 'bootstrap/cache'];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($it as $file) {
    $path = substr($file->getPathname(), strlen($root) + 1);
    foreach ($skip as $s) {
        if ($path === $s || str_starts_with($path, "{$s}/")) {
            continue 2;
        }
    }
    if ($file->isLink()) {
        $files[$path] = 'link:'.readlink($file->getPathname());
    } elseif ($file->isFile()) {
        $files[$path] = sprintf('%o:', fileperms($file->getPathname()) & 0777).hash_file('sha256', $file->getPathname());
    } elseif ($file->isDir()) {
        $files[$path.'/'] = 'dir';
    }
}
ksort($files);

$env = [];
foreach (file("{$root}/.env", FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
        $env[$m[1]] = trim($m[2], '"');
    }
}
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']}", $env['DB_USERNAME'], $env['DB_PASSWORD']);
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
$checksums = [];
foreach ($tables as $table) {
    $checksums[$table] = $pdo->query('CHECKSUM TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
}

// Row by row for the settings table, so a difference names the setting.
$rows = [];
foreach ($pdo->query('SELECT `setting`, `value` FROM `settings`') as $row) {
    $rows['settings.'.$row['setting']] = sha1((string) $row['value']);
}
ksort($rows);

echo json_encode(['files' => $files, 'tables' => $checksums, 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
