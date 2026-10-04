<?php

/*
 * Three *.orig copies left by a merge (ProductController, ServiceWelcomeMail,
 * PanelicaModule) were committed beside the real files. They are never
 * loaded, but they turn up in every search of the code with old versions of
 * methods that have since changed.
 */

test('no merge leftovers are kept in the code', function () {
    $left = [];
    foreach (['app', 'modules', 'resources', 'routes', 'config', 'database'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (preg_match('/\.(orig|rej)$/', $file->getFilename())) {
                $left[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    expect($left)->toBe([]);
});
