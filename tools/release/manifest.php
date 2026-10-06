<?php

/*
 * Writes .pnlcs-release.json into an unpacked release: the version, what it
 * needs, and the fingerprint of every file (sha256, or "link:<target>").
 * Used by build-package.sh; the updater compares installations against it.
 *
 *   php manifest.php <release-dir> <version> <commit> <runtime-image>
 */

[$script, $dir, $version, $commit, $runtimeImage] = $argv + [null, null, null, null, null];
if (! $dir || ! $version || ! is_dir($dir)) {
    fwrite(STDERR, "usage: php manifest.php <release-dir> <version> <commit> <runtime-image>\n");
    exit(64);
}

$dir = rtrim($dir, '/');
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($it as $file) {
    $path = substr($file->getPathname(), strlen($dir) + 1);
    if ($path === '.pnlcs-release.json') {
        continue;
    }
    if ($file->isLink()) {
        $files[$path] = 'link:'.readlink($file->getPathname());
    } elseif ($file->isFile()) {
        $files[$path] = hash_file('sha256', $file->getPathname());
    }
}
ksort($files, SORT_STRING);

$composer = json_decode((string) file_get_contents("{$dir}/composer.json"), true);
$php = preg_replace('/[^0-9.].*$/', '', ltrim((string) ($composer['require']['php'] ?? '8.4'), '^~>= ')) ?: '8.4';
$extensions = [];
foreach (array_keys($composer['require'] ?? []) as $package) {
    if (str_starts_with($package, 'ext-')) {
        $extensions[] = substr($package, 4);
    }
}
// The extensions the install wizard checks one by one (docs/install/requirements.md).
$extensions = array_values(array_unique(array_merge($extensions, ['bcmath', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip'])));
sort($extensions);

$manifest = [
    'version' => $version,
    'commit' => $commit,
    'built_at' => gmdate('c'),
    'requires' => ['php' => substr_count($php, '.') === 1 ? "{$php}.0" : $php, 'extensions' => $extensions, 'runtime_image' => $runtimeImage],
    'files' => $files,
];

file_put_contents("{$dir}/.pnlcs-release.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo count($files)." files\n";
