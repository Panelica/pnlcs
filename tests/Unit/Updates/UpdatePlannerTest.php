<?php

use App\Services\Updates\FileSets\ArrayFileSet;
use App\Services\Updates\ThreeWayMerge;
use App\Services\Updates\UpdatePlan;
use App\Services\Updates\UpdatePlanner;

/**
 * The decisions an update makes about each file, before it touches any.
 *
 * Every case below is one of the promises in RELEASING.md: the operator's own
 * files and changes are never overwritten, a conflict stops the update, and
 * only files the installed version shipped are ever changed or removed.
 */
function plannerRoot(array $files): string
{
    $root = storage_path('framework/testing/planner-'.bin2hex(random_bytes(6)));
    foreach ($files as $path => $content) {
        @mkdir(dirname("{$root}/{$path}"), 0777, true);
        file_put_contents("{$root}/{$path}", $content);
    }
    @mkdir($root, 0777, true);

    return $root;
}

function plan(array $base, array $disk, array $new, array $resolutions = []): UpdatePlan
{
    $root = plannerRoot($disk);

    return (new UpdatePlanner(new ThreeWayMerge(storage_path('framework/testing'))))
        ->plan(new ArrayFileSet($base), new ArrayFileSet($new), $root, $resolutions);
}

function actionFor(UpdatePlan $plan, string $path): ?array
{
    foreach ($plan->actions as $action) {
        if ($action['path'] === $path) {
            return $action;
        }
    }

    return null;
}

$lines = "one\ntwo\nthree\nfour\nfive\nsix\nseven\n";

test('a file nobody changed takes the new version', function () {
    $plan = plan(['a.php' => 'old'], ['a.php' => 'old'], ['a.php' => 'new']);

    expect(actionFor($plan, 'a.php'))->toBe(['type' => 'write', 'path' => 'a.php', 'source' => 'new'])
        ->and($plan->hasConflicts())->toBeFalse();
});

test('a file only the operator changed is kept as it is', function () {
    $plan = plan(['robots.txt' => 'shipped'], ['robots.txt' => 'mine'], ['robots.txt' => 'shipped']);

    expect($plan->actions)->toBe([])
        ->and($plan->kept)->toBe(['robots.txt']);
});

test('changes to different lines are merged, both survive', function () use ($lines) {
    $mine = str_replace('two', 'TWO (mine)', $lines);
    $new = str_replace('six', 'SIX (new)', $lines);

    $plan = plan(['v.blade.php' => $lines], ['v.blade.php' => $mine], ['v.blade.php' => $new]);

    $action = actionFor($plan, 'v.blade.php');
    expect($action['source'])->toBe('merged')
        ->and($action['content'])->toContain('TWO (mine)')->toContain('SIX (new)')
        ->and($plan->merged)->toBe(['v.blade.php'])
        ->and($plan->hasConflicts())->toBeFalse();
});

test('changes to the same lines are a conflict, and nothing is written', function () use ($lines) {
    $plan = plan(['v.blade.php' => $lines], ['v.blade.php' => str_replace('two', 'mine', $lines)], ['v.blade.php' => str_replace('two', 'theirs', $lines)]);

    expect($plan->actions)->toBe([])
        ->and($plan->conflicts[0]['path'])->toBe('v.blade.php')
        ->and($plan->conflicts[0]['kind'])->toBe(UpdatePlan::BOTH_CHANGED)
        ->and($plan->conflicts[0]['merged'])->toContain('<<<<<<< your version')->toContain('mine')->toContain('theirs');
});

test('a binary file changed on both sides is a conflict, not a merge', function () {
    $plan = plan(['logo.png' => "\x89PNG\0a"], ['logo.png' => "\x89PNG\0b"], ['logo.png' => "\x89PNG\0c"]);

    expect($plan->conflicts[0]['kind'])->toBe(UpdatePlan::BOTH_CHANGED)
        ->and($plan->actions)->toBe([]);
});

test('a file the operator deleted stays deleted, unless the new version changed it', function () {
    $untouched = plan(['old.php' => 'x'], [], ['old.php' => 'x']);
    $changed = plan(['old.php' => 'x'], [], ['old.php' => 'y']);

    expect($untouched->actions)->toBe([])->and($untouched->hasConflicts())->toBeFalse()
        ->and($changed->conflicts[0]['kind'])->toBe(UpdatePlan::DELETED_BY_OPERATOR);
});

test('a file the new version drops is removed only if the operator never changed it', function () {
    $untouched = plan(['gone.php' => 'x'], ['gone.php' => 'x'], []);
    $changed = plan(['gone.php' => 'x'], ['gone.php' => 'edited'], []);

    expect(actionFor($untouched, 'gone.php'))->toBe(['type' => 'delete', 'path' => 'gone.php'])
        ->and($changed->actions)->toBe([])
        ->and($changed->conflicts[0]['kind'])->toBe(UpdatePlan::REMOVED_IN_NEW);
});

test('a new file never lands on a file of the operator\'s own', function () {
    $shipped = ['app/Hooks/example.php.disabled' => 'x'];
    $different = plan($shipped, $shipped + ['app/Hooks/billing.php' => 'theirs'], $shipped + ['app/Hooks/billing.php' => 'shipped']);
    $same = plan($shipped, $shipped + ['app/Hooks/billing.php' => 'shipped'], $shipped + ['app/Hooks/billing.php' => 'shipped']);

    expect($different->actions)->toBe([])
        ->and($different->conflicts[0]['kind'])->toBe(UpdatePlan::FILE_IN_THE_WAY)
        ->and($same->actions)->toBe([])->and($same->hasConflicts())->toBeFalse();
});

test('a new version never puts files inside the operator\'s own theme', function () {
    $plan = plan(
        ['themes/starter/theme.json' => '{}'],
        ['themes/starter/theme.json' => '{}', 'themes/acme/theme.json' => '{"slug":"acme"}'],
        ['themes/starter/theme.json' => '{}', 'themes/acme/theme.json' => '{}', 'themes/acme/views/home.blade.php' => 'x'],
    );

    expect($plan->actions)->toBe([])
        ->and($plan->conflicts)->toBe([['path' => 'themes/acme', 'kind' => UpdatePlan::OPERATOR_DIRECTORY]]);
});

test('a new built-in theme in a directory nobody has is simply installed', function () {
    $plan = plan(['themes/starter/theme.json' => '{}'], ['themes/starter/theme.json' => '{}'], ['themes/starter/theme.json' => '{}', 'themes/pnlcs-new/theme.json' => '{}']);

    expect(actionFor($plan, 'themes/pnlcs-new/theme.json')['source'])->toBe('new')
        ->and($plan->hasConflicts())->toBeFalse();
});

test('the operator\'s own files are not part of the plan at all', function () {
    $plan = plan(
        ['themes/starter/theme.json' => '{}'],
        ['themes/starter/theme.json' => '{}', 'themes/acme/theme.json' => '{}', 'modules/Servers/Mine/MineModule.php' => '<?php'],
        ['themes/starter/theme.json' => '{}'],
    );

    expect($plan->actions)->toBe([])->and($plan->hasConflicts())->toBeFalse();
});

test('the user space is never planned, whatever a package holds', function () {
    $plan = plan(
        ['.env' => 'A', 'storage/app/x' => 'A', 'public/storage' => 'link:../storage/app/public', '.env.example' => 'A'],
        ['.env' => 'mine', 'storage/app/x' => 'mine', '.env.example' => 'A'],
        ['.env' => 'B', 'storage/app/x' => 'B', 'storage/app/new' => 'B', '.env.backup' => 'B', '.env.example' => 'B'],
    );

    expect(array_column($plan->actions, 'path'))->toBe(['.env.example'])
        ->and($plan->hasConflicts())->toBeFalse();
});

test('vendor and the built assets are rebuilt whole, not merged', function () {
    $plan = plan(['vendor/a.php' => 'x'], ['vendor/a.php' => 'patched'], ['vendor/a.php' => 'y', 'public/build/app.js' => 'js']);

    expect($plan->actions)->toBe([])
        ->and($plan->hasConflicts())->toBeFalse()
        ->and($plan->replaceDirectories)->toBe(['vendor', 'public/build']);
});

test('the operator\'s decisions resolve conflicts', function () use ($lines) {
    $base = ['v.php' => $lines, 'r.txt' => 'shipped'];
    $disk = ['v.php' => str_replace('two', 'mine', $lines), 'r.txt' => 'mine'];
    $new = ['v.php' => str_replace('two', 'theirs', $lines), 'r.txt' => 'new'];

    $takeNew = plan($base, $disk, $new, ['v.php' => UpdatePlan::TAKE_NEW, 'r.txt' => UpdatePlan::KEEP_MINE]);
    $edited = plan($base, $disk, $new, ['v.php' => "merged by hand\n", 'r.txt' => UpdatePlan::TAKE_NEW]);

    expect($takeNew->hasConflicts())->toBeFalse()
        ->and(actionFor($takeNew, 'v.php')['source'])->toBe('new')
        ->and(actionFor($takeNew, 'r.txt'))->toBeNull()
        ->and($edited->hasConflicts())->toBeFalse()
        ->and(actionFor($edited, 'v.php'))->toBe(['type' => 'write', 'path' => 'v.php', 'source' => 'resolved', 'content' => "merged by hand\n"])
        ->and(actionFor($edited, 'r.txt')['source'])->toBe('new');
});

test('the same change made on both sides is no conflict', function () {
    $plan = plan(['a.php' => 'old'], ['a.php' => 'fixed'], ['a.php' => 'fixed']);

    expect($plan->actions)->toBe([])->and($plan->hasConflicts())->toBeFalse();
});
