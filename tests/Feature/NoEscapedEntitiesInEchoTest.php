<?php

/**
 * An HTML entity written inside {{ }} is printed, not rendered.
 *
 * Blade escapes what {{ }} echoes, so `{{ $x ?? '&mdash;' }}` puts the five
 * characters &mdash; on the screen where a dash belongs. It read as a broken
 * page on the KSeF list, the invoice and order screens, and four config
 * tables. The character itself ('—') escapes to itself and is what belongs
 * there; an entity still works in plain HTML and inside {!! !!}.
 */
test('no blade echo prints an HTML entity as text', function () {
    $found = [];
    $roots = [resource_path('views'), base_path('themes'), base_path('modules')];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if (! preg_match_all('/\{\{(?:(?!\}\}).)*\}\}/s', $source, $echoes)) {
                continue;
            }

            foreach ($echoes[0] as $echo) {
                if (preg_match('/&[a-zA-Z][a-zA-Z0-9]{1,10};/', $echo, $entity)) {
                    $found[] = str_replace(base_path().'/', '', $file->getPathname()).'  '.trim($echo);
                }
            }
        }
    }

    expect($found)->toBe([]);
});
