<?php

/**
 * Every question and notice goes through the panel's own dialogs
 * (resources/js/dialogs.js: pnConfirm, pnDialog), never the browser's alert(),
 * confirm() or prompt(): those cannot be styled or translated, look like a
 * phishing page to customers, and are blocked outright in some embeds.
 */
test('no view or script uses the browser\'s alert, confirm or prompt', function () {
    $found = [];
    $roots = [resource_path('views'), base_path('themes'), base_path('modules'), resource_path('js')];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! preg_match('/\.(php|js)$/', $file->getFilename()) || str_ends_with($file->getPathname(), 'resources/js/dialogs.js')) {
                continue;
            }
            foreach (file($file->getPathname()) as $n => $line) {
                // A call of the bare function or of window.*, not a method of
                // something else (pnDialog.confirm, Swal.fire, $request->confirm).
                if (preg_match('/(?<![\w.$>-])(?:window\.)?(alert|confirm|prompt)\s*\(/', $line) && ! preg_match('/^\s*(\/\/|\*|#)/', $line)) {
                    $found[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($n + 1).'  '.trim($line);
                }
            }
        }
    }

    expect($found)->toBe([]);
});

test('every layout that serves pages loads the dialogs', function (string $layout) {
    $source = file_get_contents(base_path($layout));

    expect($source)->toContain("@include('partials.dialog-boot')")
        ->and(preg_match('/@vite\(\[[^\]]*resources\/js\/(app|dialogs)\.js/', $source))->toBe(1);
})->with([
    'resources/views/admin/layouts/app.blade.php',
    'resources/views/client/layouts/app.blade.php',
    'themes/flavor/views/client/layouts/app.blade.php',
]);
