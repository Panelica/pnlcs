<?php

use Symfony\Component\Process\Process;

/**
 * The script that changes the files of an installation (resources/updater/apply.php).
 *
 * The promise it carries: an update ends on the new files or on the old ones,
 * byte for byte, even when it is stopped half way.
 */
function applierTree(string $dir, array $files): void
{
    foreach ($files as $path => $content) {
        @mkdir(dirname("{$dir}/{$path}"), 0777, true);
        str_starts_with($content, 'link:')
            ? symlink(substr($content, 5), "{$dir}/{$path}")
            : file_put_contents("{$dir}/{$path}", $content);
    }
}

/** Every file and link under a directory, with its content and mode. */
function applierSnapshot(string $dir): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($dir) + 1);
        $out[$rel] = $file->isLink() ? 'link:'.readlink($file->getPathname())
            : ($file->isDir() ? 'dir' : sprintf('%o:', fileperms($file->getPathname()) & 0777).hash_file('sha256', $file->getPathname()));
    }
    ksort($out);

    return $out;
}

function applierRun(string $verb, string $planFile, array $env = []): Process
{
    $process = new Process([PHP_BINARY, resource_path('updater/apply.php'), $verb, $planFile], null, $env);
    $process->run();

    return $process;
}

function applierScenario(): array
{
    $base = storage_path('framework/testing/applier-'.bin2hex(random_bytes(6)));
    $root = "{$base}/site";
    $run = "{$base}/run";
    $new = "{$run}/new";
    mkdir($run, 0777, true);

    applierTree($root, [
        'app/Keep.php' => 'unchanged',
        'app/Change.php' => 'old',
        'app/Gone/Old.php' => 'to be removed',
        'resources/views/x.blade.php' => 'old view',
        'vendor/autoload.php' => 'old vendor',
        'vendor/lib/a.php' => 'old lib',
        'themes/mine/theme.json' => 'operator theme',
        '.env' => 'APP_KEY=secret',
        'public/themes' => 'link:../themes',
    ]);
    chmod("{$root}/app/Change.php", 0640);
    applierTree($new, [
        'app/Change.php' => 'new',
        'app/Added/New.php' => 'brand new',
        'vendor/autoload.php' => 'new vendor',
        'public/build/app.js' => 'js',
    ]);
    file_put_contents("{$run}/merged-x", 'merged view');

    $plan = [
        'root' => $root, 'run_dir' => $run, 'new_root' => $new,
        'actions' => [
            ['type' => 'write', 'path' => 'app/Change.php', 'source' => 'new'],
            ['type' => 'write', 'path' => 'app/Added/New.php', 'source' => 'new'],
            ['type' => 'write', 'path' => 'resources/views/x.blade.php', 'source' => 'merged', 'from' => "{$run}/merged-x"],
            ['type' => 'delete', 'path' => 'app/Gone/Old.php'],
        ],
        'replace_directories' => ['vendor', 'public/build'],
        'keep_directories' => ['app'],
    ];
    file_put_contents("{$run}/plan.json", json_encode($plan));

    return [$root, $run];
}

test('an update writes, merges, removes and rebuilds what the plan says, and nothing else', function () {
    [$root, $run] = applierScenario();

    $result = applierRun('apply', "{$run}/plan.json");

    expect($result->getExitCode())->toBe(0, $result->getErrorOutput())
        ->and(file_get_contents("{$root}/app/Change.php"))->toBe('new')
        ->and(fileperms("{$root}/app/Change.php") & 0777)->toBe(0640)
        ->and(file_get_contents("{$root}/app/Added/New.php"))->toBe('brand new')
        ->and(file_get_contents("{$root}/resources/views/x.blade.php"))->toBe('merged view')
        ->and(file_exists("{$root}/app/Gone/Old.php"))->toBeFalse()
        ->and(is_dir("{$root}/app/Gone"))->toBeFalse()
        ->and(file_get_contents("{$root}/vendor/autoload.php"))->toBe('new vendor')
        ->and(file_exists("{$root}/vendor/lib/a.php"))->toBeFalse()
        ->and(file_get_contents("{$root}/public/build/app.js"))->toBe('js')
        ->and(file_get_contents("{$root}/app/Keep.php"))->toBe('unchanged')
        ->and(file_get_contents("{$root}/themes/mine/theme.json"))->toBe('operator theme')
        ->and(file_get_contents("{$root}/.env"))->toBe('APP_KEY=secret')
        ->and(readlink("{$root}/public/themes"))->toBe('../themes');
});

test('a rollback puts every byte back, after a finished run', function () {
    [$root, $run] = applierScenario();
    $before = applierSnapshot($root);

    applierRun('apply', "{$run}/plan.json");
    $result = applierRun('rollback', "{$run}/plan.json");

    expect($result->getExitCode())->toBe(0, $result->getErrorOutput())
        ->and(applierSnapshot($root))->toBe($before);
});

test('a run stopped at any step rolls back to exactly what was there', function (int $steps) {
    [$root, $run] = applierScenario();
    $before = applierSnapshot($root);

    $stopped = applierRun('apply', "{$run}/plan.json", ['PNLCS_UPDATE_FAIL_AFTER' => (string) $steps]);
    expect($stopped->getExitCode())->toBe(1);

    $result = applierRun('rollback', "{$run}/plan.json");

    expect($result->getExitCode())->toBe(0, $result->getErrorOutput())
        ->and(applierSnapshot($root))->toBe($before);
})->with([0, 1, 2, 3, 4, 5]);

test('rolling back twice is harmless', function () {
    [$root, $run] = applierScenario();
    $before = applierSnapshot($root);

    applierRun('apply', "{$run}/plan.json");
    applierRun('rollback', "{$run}/plan.json");
    $again = applierRun('rollback', "{$run}/plan.json");

    expect($again->getExitCode())->toBe(0)
        ->and(applierSnapshot($root))->toBe($before);
});

test('a run is never applied twice over itself', function () {
    [, $run] = applierScenario();

    applierRun('apply', "{$run}/plan.json");
    $second = applierRun('apply', "{$run}/plan.json");

    expect($second->getExitCode())->toBe(1)
        ->and($second->getErrorOutput())->toContain('Roll it back first');
});

test('a path that leaves the installation is refused before anything is written', function () {
    [$root, $run] = applierScenario();
    $plan = json_decode(file_get_contents("{$run}/plan.json"), true);
    $plan['actions'] = [['type' => 'write', 'path' => '../outside.php', 'source' => 'new']];
    file_put_contents("{$run}/plan.json", json_encode($plan));

    $result = applierRun('apply', "{$run}/plan.json");

    expect($result->getExitCode())->toBe(1)
        ->and(file_exists(dirname($root).'/outside.php'))->toBeFalse();
});
