<?php

use App\Services\Updates\FileSets\GitFileSet;
use Symfony\Component\Process\Process;

/**
 * The files a git installation was cloned with, as the updater compares them.
 */
test('a git installation is compared with what a release ships: the paths the package leaves out are left out', function () {
    $root = base_path();
    $archive = new Process(['sh', '-c', 'git -c safe.directory=* archive --format=tar HEAD | tar -tf - | grep -v "/$"'], $root, null, null, 120);
    $archive->mustRun();
    $shipped = array_values(array_filter(explode("\n", $archive->getOutput())));
    sort($shipped);

    $entries = array_keys((new GitFileSet($root))->entries());
    sort($entries);

    // tests/, docs/, tools/ ... are export-ignore: a release removes nothing there.
    expect($entries)->toBe($shipped)
        ->and($entries)->not->toContain('tests/Pest.php');
});

test('reading a large clone needs no writable temporary directory (a hosting account\'s cron)', function () {
    $script = tempnam(storage_path('framework/testing'), 'gitset').'.php';
    file_put_contents($script, '<?php require '.var_export(base_path('vendor/autoload.php'), true).';'
        .'$app = require '.var_export(base_path('bootstrap/app.php'), true).';'
        .'$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();'
        .'echo count((new App\Services\Updates\FileSets\GitFileSet(base_path()))->entries());');

    // A temporary directory nobody can write to (/proc, even for root), like /tmp (root, 755)
    // under a Panelica account's cron.
    $run = new Process([PHP_BINARY, '-d', 'sys_temp_dir=/proc', $script], base_path(), ['APP_ENV' => 'testing'], null, 300);
    $run->run();
    @unlink($script);

    expect(trim($run->getErrorOutput()))->toBe('')
        ->and((int) $run->getOutput())->toBeGreaterThan(1000);
});
