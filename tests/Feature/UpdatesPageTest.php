<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Services\Updates\UpdateBar;
use App\Services\Updates\UpdateState;
use App\Services\Updates\Version;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\UploadedFile;

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
        ->assertRedirect();

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

/** A report with one conflict whose merged text holds markers, as a check leaves it. */
function updatesConflict(UpdateState $state): void
{
    $state->write('latest.json', ['latest' => ['version' => '9.9.9', 'pre_release' => false]]);
    $state->write('report.json', ['to' => '9.9.9', 'ok' => false, 'blocking' => [], 'warnings' => [], 'plan' => ['write' => 0, 'delete' => 0],
        'conflicts' => [['path' => 'public/robots.txt', 'kind' => 'both_changed', 'has_merged' => true]]]);
    @mkdir($state->path('preflight/9.9.9/public'), 0777, true);
    file_put_contents($state->path('preflight/9.9.9/public/robots.txt'), "User-agent: *\n<<<<<<< your version\nDisallow: /mine\n=======\nDisallow: /theirs\n>>>>>>> new version\n");
}

test('the merged text is offered in an editor on the page', function () {
    $state = updatesState();
    updatesConflict($state);

    $this->actingAs(updatesAdmin(), 'admin')
        ->get(route('admin.config.updates'))
        ->assertOk()
        ->assertSee('name="resolved_text[0]"', false)
        ->assertSee('&lt;&lt;&lt;&lt;&lt;&lt;&lt; your version', false)
        ->assertSee(__('admin.updates.choose_file'));
});

test('a file edited on the page is saved as the decision, with the line endings it had', function () {
    $state = updatesState();
    updatesConflict($state);

    $this->actingAs(updatesAdmin(), 'admin')
        ->post(route('admin.config.updates.resolve'), ['choice' => ['edited'], 'resolved_text' => ["User-agent: *\r\nDisallow: /mine\r\nDisallow: /theirs\r\n"]])
        ->assertSessionHas('success');

    expect($state->resolutions('9.9.9'))->toBe(['public/robots.txt' => "User-agent: *\nDisallow: /mine\nDisallow: /theirs\n"]);
});

test('a file that still has conflict markers is refused, and nothing is saved', function () {
    $state = updatesState();
    updatesConflict($state);

    $this->actingAs(updatesAdmin(), 'admin')
        ->post(route('admin.config.updates.resolve'), ['choice' => ['edited'], 'resolved_text' => ["<<<<<<< your version\nDisallow: /mine\n=======\n>>>>>>> new version\n"]])
        ->assertSessionHas('error', __('admin.updates.markers_left', ['path' => 'public/robots.txt']));

    expect($state->resolutions('9.9.9'))->toBe([]);
});

test('an uploaded file is saved as the decision', function () {
    $state = updatesState();
    updatesConflict($state);
    $upload = UploadedFile::fake()->createWithContent('robots.txt', "User-agent: *\nDisallow: /both\n");

    $this->actingAs(updatesAdmin(), 'admin')
        ->post(route('admin.config.updates.resolve'), ['choice' => ['edited'], 'file' => [$upload]])
        ->assertSessionHas('success');

    expect($state->resolutions('9.9.9'))->toBe(['public/robots.txt' => "User-agent: *\nDisallow: /both\n"]);
});

test('the update bar shows on admin pages while a newer release waits', function () {
    $state = updatesState();
    $state->write('latest.json', ['latest' => ['version' => '99.0.0', 'pre_release' => false]]);
    $admin = updatesAdmin();

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.updates.bar_available', ['version' => '99.0.0', 'installed' => (string) Version::installed()]))
        ->assertSee(__('admin.updates.bar_hide'))
        ->assertSee(__('admin.updates.bar_off'));
});

test('the update bar is not shown to staff who cannot update, nor once turned off', function () {
    $state = updatesState();
    $state->write('latest.json', ['latest' => ['version' => '99.0.0', 'pre_release' => false]]);
    $bar = __('admin.updates.bar_open');

    $this->actingAs(updatesAdmin(false, ['manage_settings']), 'admin')->get(route('admin.dashboard'))->assertOk()->assertDontSee($bar);

    $admin = updatesAdmin();
    $this->actingAs($admin, 'admin')->post(route('admin.config.updates.bar'), ['show' => '0'])->assertSessionHas('success', __('admin.updates.bar_turned_off'));
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->assertDontSee($bar);

    $this->actingAs($admin, 'admin')->post(route('admin.config.updates.bar'), ['show' => '1']);
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->assertSee($bar);
});

test('an update that did not finish is shown even with the bar turned off', function () {
    $state = updatesState();
    $state->write('current-run.json', ['id' => 'x', 'to' => '99.0.0', 'phase' => 'migrating']);
    $admin = updatesAdmin();
    Setting::set(UpdateBar::settingKey($admin), '1', 'updates');

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.updates.bar_unfinished', ['version' => '99.0.0']));
});

test('saving the decisions can start the update straight away', function () {
    $state = updatesState();
    updatesConflict($state);

    $this->actingAs(updatesAdmin(), 'admin')
        ->post(route('admin.config.updates.resolve'), ['choice' => ['mine'], 'then' => 'apply'])
        ->assertRedirect();

    expect($state->resolutions('9.9.9'))->toBe(['public/robots.txt' => 'mine'])
        ->and($state->read('request.json')['action'])->toBe('apply');
});

test('of two processes that claim a request, only one gets it', function () {
    $state = updatesState();
    $state->write('request.json', ['action' => 'apply', 'version' => '9.9.9']);

    expect($state->claim('request.json'))->toBe(['action' => 'apply', 'version' => '9.9.9'])
        ->and($state->claim('request.json'))->toBeNull();
});

test('the administrator who starts an update can watch it through maintenance', function () {
    $state = updatesState();
    $state->write('latest.json', ['latest' => ['version' => '9.9.9']]);

    $response = $this->actingAs(updatesAdmin(), 'admin')->post(route('admin.config.updates.apply'));

    $secret = $state->read('request.json')['maintenance_secret'];
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'laravel_maintenance');
    expect($secret)->toMatch('/^[0-9a-f]{32}$/')
        ->and($cookie)->not->toBeNull()
        ->and(MaintenanceModeBypassCookie::isValid($cookie->getValue(), $secret))->toBeTrue();
});

test('a requested update that cannot even begin says why instead of staying queued', function (array $request) {
    $state = updatesState();
    config(['updates.index_url' => 'file:///nonexistent/pnlcs-index.json']);
    app()->forgetInstance(\App\Services\Updates\ReleaseIndex::class);
    $state->write('request.json', $request);
    $state->status('queued', 'download', ['version' => '9.9.9', 'action' => $request['action']]);

    $this->artisan('pnlcs:update', ['--from-request' => true])->assertExitCode(1);

    $status = $state->read('status.json');
    expect($status['state'])->toBe('error')
        ->and($status['message'] ?? '')->not->toBe('')
        ->and($state->read('request.json'))->toBeNull();
})->with([
    'apply' => [['action' => 'apply', 'version' => '9.9.9', 'by' => 'admin']],
    'check' => [['action' => 'prepare', 'version' => '9.9.9', 'by' => 'admin']],
]);

test('the scheduled update work is held back by nothing a crash could leave behind', function (string $command) {
    $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, $command));

    // The updater's own lock is released by the system when its process dies;
    // the scheduler's overlap lock would stay in the cache for hours, and
    // where only the scheduler may start the update (SELinux) hold it back.
    expect($event)->not->toBeNull()
        ->and($event->withoutOverlapping)->toBeFalse()
        ->and($event->evenInMaintenanceMode)->toBeTrue();
})->with(['pnlcs:update --from-request', 'pnlcs:update-rollback --abandoned']);
