<?php

/**
 * A net, so this does not come back.
 *
 * A deleted customer keeps its row. Anything written through the model knows
 * that; anything that goes to the table directly has to say so itself, and ten
 * places had forgotten - the dashboard counted twenty deleted customers among
 * the living, and eight reports listed them.
 *
 * This walks the code rather than the database: every query that names the
 * clients table has to mention deleted_at somewhere in the same file.
 */
it('keeps every raw clients query aware of deleted customers', function () {
    $offenders = [];

    foreach (['app', 'modules'] as $dir) {
        $path = base_path($dir);

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            $touchesTable = preg_match('/DB::table\(["\']clients["\']\)/', $source)
                || preg_match('/->join\(["\']clients["\']/', $source);

            if ($touchesTable && ! str_contains($source, 'deleted_at')) {
                $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([]);
});
