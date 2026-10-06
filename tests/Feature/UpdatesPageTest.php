<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Services\Updates\UpdateState;

/**
 * Setup -> Updates: who may use it, and that it only ever asks the scheduler
 * to do the work.
 */
function updatesState(): UpdateState
{
    $dir = storage_path('framework/testing/updates-'.bin2hex(random_bytes(6)));
    config(['updates.path' => $dir]);
    app()->forgetInstance(UpdateState::class);

    return app(UpdateState::class);
}

function updatesAdmin(bool $full = true, array $permissions = []): Admin
{
    $role = $full ? AdminRole::factory()->fullAdmin()->create() : AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions]);

    return Admin::factory()->create(['role_id' => $role->id]);
}

test('a full administrator sees the page, with the installed version', function () {
    updatesState();

    $this->actingAs(updatesAdmin(), 'admin')
        ->get(route('admin.config.updates'))
        ->assertOk()
        ->assertSee(__('admin.updates.installed'))
        ->assertSee(trim((string) file_get_contents(base_path('VERSION'))));
});

test('an administrator without the update permission is kept out, even with every setting permission', function () {
    updatesState();

    $this->actingAs(updatesAdmin(false, ['manage_settings', 'view_system']), 'admin')
        ->get(route('admin.config.updates'))
        ->assertForbidden();
});

test('a role given the update permission may use it', function () {
    updatesState();

    $this->actingAs(updatesAdmin(false, ['manage_updates']), 'admin')
        ->get(route('admin.config.updates'))
        ->assertOk();
});

test('asking for an update only leaves a request for the scheduler', function () {
    $state = updatesState();
    $state->write('latest.json', ['checked_at' => now()->toIso8601String(), 'channel' => 'stable', 'latest' => [
        'version' => '9.9.9', 'tag' => 'v9.9.9', 'pre_release' => false, 'notes' => 'Notes', 'published_at' => null, 'url' => null,
    ]]);

    $this->actingAs(updatesAdmin(), 'admin')
        ->post(route('admin.config.updates.apply'))
        ->assertSessionHas('success', __('admin.updates.queued'));

    $request = $state->read('request.json');
    expect($request['action'])->toBe('apply')
        ->and($request['version'])->toBe('9.9.9');

    // A second click while one is waiting is refused.
    $this->actingAs(updatesAdmin(), 'admin')
        ->post(route('admin.config.updates.prepare'))
        ->assertSessionHas('error', __('admin.updates.busy'));
});

test('a conflict decision is stored only for paths the report lists, never outside the updater', function () {
    $state = updatesState();
    $state->write('latest.json', ['latest' => ['version' => '9.9.9']]);
    $state->write('report.json', ['to' => '9.9.9', 'ok' => false, 'conflicts' => [['path' => 'public/robots.txt', 'kind' => 'both_changed', 'has_merged' => true]]]);

    $this->actingAs(updatesAdmin(), 'admin')
        ->post(route('admin.config.updates.resolve'), ['choice' => ['new', 'new']])
        ->assertSessionHas('success');

    expect($state->read('resolutions/9.9.9.json'))->toBe(['public/robots.txt' => 'new'])
        ->and(fn () => $state->resolve('9.9.9', '../../.env', 'new'))->toThrow(RuntimeException::class);
});

test('the merged file of a conflict can be downloaded, and nothing else', function () {
    $state = updatesState();
    $state->write('latest.json', ['latest' => ['version' => '9.9.9']]);
    $state->write('report.json', ['to' => '9.9.9', 'conflicts' => [['path' => 'public/robots.txt', 'kind' => 'both_changed', 'has_merged' => true]]]);
    @mkdir($state->path('preflight/9.9.9/public'), 0777, true);
    file_put_contents($state->path('preflight/9.9.9/public/robots.txt'), "<<<<<<< your version\n");

    $admin = updatesAdmin();
    $this->actingAs($admin, 'admin')->get(route('admin.config.updates.merged', ['path' => 'public/robots.txt']))->assertOk();
    $this->actingAs($admin, 'admin')->get(route('admin.config.updates.merged', ['path' => '.env']))->assertNotFound();
});
