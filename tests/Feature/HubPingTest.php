<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Services\Hub\HubPing;
use App\Services\Hub\NoticeBoard;
use App\Services\Updates\FileSets\PackageFileSet;
use App\Services\Updates\Installation;
use App\Services\Updates\PackageStore;
use App\Services\Updates\ReleaseIndex;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
 * The once-a-day ping to hub.pnlcs.com (App\Services\Hub\HubPing): what it
 * sends and does not send, that a hub failing changes nothing, and that one
 * answer cannot take an install's identity away.
 */

function hubAnswer(array $extra = []): array
{
    return ['protocol' => 1, 'latest' => ['stable' => '9.9.9'], 'notices' => []] + $extra;
}

function hubFake(array $answer = []): void
{
    // A fresh fake each time: stubs added to an existing fake do not replace it.
    Http::swap(new Factory);
    Http::fake(['hub.pnlcs.com/*' => Http::response(hubAnswer($answer))]);
}

test('the ping carries the contact details, address and versions, and nothing else', function () {
    Setting::set('CompanyName', 'Acme Hosting');
    Setting::set('Email', 'hello@acme.test');
    Setting::set('PhoneNumber', '+90 (212) 555-01-01');
    Setting::set('Country', 'tr');
    Setting::set('DefaultLanguage', 'tr');
    $role = AdminRole::factory()->create(['is_full_admin' => true]);
    Admin::factory()->create(['role_id' => $role->id, 'first_name' => 'Ada', 'last_name' => 'Owner', 'email' => 'ada@acme.test']);
    Admin::factory()->create(['role_id' => $role->id, 'first_name' => 'Later', 'last_name' => 'Admin', 'email' => 'later@acme.test']);
    hubFake();

    expect(app(HubPing::class)->send())->toBeTrue();

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://hub.pnlcs.com/v1/ping'
            && str_starts_with($request->header('User-Agent')[0] ?? '', 'PNLCS/')
            && array_keys($body) === ['protocol', 'install_id', 'install_secret', 'url', 'company_name', 'company_email', 'phone', 'country', 'admin_name', 'admin_email', 'locale', 'version', 'install_mode', 'docker', 'install_method', 'php', 'db']
            && $body['company_name'] === 'Acme Hosting'
            && $body['phone'] === '+90 (212) 555-01-01'
            && $body['country'] === 'TR'
            && $body['admin_email'] === 'ada@acme.test'
            && preg_match('/^[0-9a-f]{64}$/', $body['install_secret']) === 1;
    });
    expect(Setting::get('hub_last_ping_at'))->not->toBeNull();
});

test('text the hub would refuse is cleaned before it is sent', function () {
    Setting::set('CompanyName', "Acme\u{202E}\nHosting");
    Setting::set('PhoneNumber', 'call me maybe');
    Setting::set('Country', 'Turkey');
    hubFake();

    app(HubPing::class)->send();

    Http::assertSent(fn (Request $r) => $r['company_name'] === 'AcmeHosting' && $r['phone'] === '' && $r['country'] === '');
});

test('the wizard answers go once, with the next ping', function () {
    Setting::set('hub_source', 'A friend');
    Setting::set('hub_community_profile', '1');
    Setting::set('hub_answers_pending', '1');
    hubFake();

    app(HubPing::class)->send();
    app(HubPing::class)->send();

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data());
    expect($sent[0]['source'] ?? null)->toBe('A friend')
        ->and($sent[0]['community_profile'] ?? null)->toBeTrue()
        ->and($sent[1])->not->toHaveKey('source')
        ->and($sent[1])->not->toHaveKey('community_profile');
});

test('nothing is sent outside production or under the test suite', function () {
    Http::fake();

    $this->artisan('pnlcs:hub-ping --now')->assertSuccessful();

    expect(HubPing::allowed())->toBeFalse();
    Http::assertNothingSent();
});

test('a hub that is down, slow or broken changes nothing and throws nothing', function () {
    Http::fake(['hub.pnlcs.com/*' => fn () => throw new ConnectionException('timed out')]);
    expect(app(HubPing::class)->send())->toBeFalse();

    Http::fake(['hub.pnlcs.com/*' => Http::response('oops', 500)]);
    expect(app(HubPing::class)->send())->toBeFalse();

    Http::fake(['hub.pnlcs.com/*' => Http::response('', 302, ['Location' => 'https://elsewhere.test/'])]);
    expect(app(HubPing::class)->send())->toBeFalse();

    expect(Setting::get('hub_last_ping_at'))->toBeNull();
});

test('an answer larger than 64 KB is not read', function () {
    Http::fake(['hub.pnlcs.com/*' => Http::response(json_encode(hubAnswer(['reset_id' => true, 'pad' => str_repeat('x', 70000)])))]);

    expect(app(HubPing::class)->send())->toBeFalse()
        ->and(Setting::get('hub_reset_seen_on'))->toBeNull();
});

test('a request for a new identity is applied only on a second, different day', function () {
    $hub = app(HubPing::class);
    $first = $hub->identity();
    hubFake(['reset_id' => true]);

    $hub->send();
    $hub->send();
    expect($hub->identity())->toBe($first);

    $this->travel(1)->days();
    $hub->send();

    expect($hub->identity())->not->toBe($first)
        ->and(Str::isUuid($hub->identity()))->toBeTrue();
});

test('an answer without the request forgets an earlier one', function () {
    $hub = app(HubPing::class);
    $first = $hub->identity();

    hubFake(['reset_id' => true]);
    $hub->send();
    $this->travel(1)->days();
    hubFake();
    $hub->send();
    $this->travel(1)->days();
    hubFake(['reset_id' => true]);
    $hub->send();

    expect($hub->identity())->toBe($first);
});

test('it sends once a day, at the install\'s own hour', function () {
    $hub = app(HubPing::class);
    $hour = $hub->hour();
    $this->travelTo(now()->startOfDay()->addHours($hour)->addMinutes(5));

    expect($hub->due())->toBeTrue();

    Setting::set('hub_last_attempt_on', now()->toDateString());
    expect($hub->due())->toBeFalse();

    if ($hour > 0) {
        Setting::set('hub_last_attempt_on', now()->subDay()->toDateString());
        $this->travelTo(now()->startOfDay()->addHours($hour - 1));
        expect($hub->due())->toBeFalse();
    }
});

test('without a cron, a week without a ping makes the dashboard due to send it', function () {
    $hub = app(HubPing::class);

    expect($hub->overdue())->toBeTrue();

    Setting::set('hub_last_ping_at', now()->subDays(2)->toIso8601String());
    expect($hub->overdue())->toBeFalse();

    Setting::set('hub_last_ping_at', now()->subDays(8)->toIso8601String());
    expect($hub->overdue())->toBeTrue();
});

test('the hub address cannot come from a setting', function () {
    Setting::set('hub_url', 'https://evil.test/');
    hubFake();

    app(HubPing::class)->send();

    Http::assertSent(fn (Request $r) => $r->url() === 'https://hub.pnlcs.com/v1/ping');
});

test('an install that never saw the wizard is asked its two questions once', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
        ->assertOk()->assertSee(__('admin.hub.source'));

    $this->actingAs($admin, 'admin')->post(route('admin.hub.answers'), ['source' => 'GitHub', 'community_profile' => '1'])
        ->assertRedirect();

    expect(Setting::get('hub_source'))->toBe('GitHub')
        ->and(Setting::get('hub_community_profile'))->toBe('1')
        ->and(Setting::get('hub_answers_pending'))->toBe('1');

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
        ->assertOk()->assertDontSee(__('admin.hub.source'));
});

test('"not now" closes the box without answering', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.hub.answers'), ['skip' => '1', 'source' => 'ignored']);

    expect(Setting::get('hub_asked'))->toBe('1')
        ->and(Setting::get('hub_answers_pending'))->toBeNull();
});

test('the wizard asks the two questions and says what is sent', function () {
    $lock = storage_path('installed.lock');
    $parked = $lock.'.parked-by-hub-test';
    $wasLocked = file_exists($lock);
    if ($wasLocked) {
        rename($lock, $parked);
    }

    try {
        DB::table('admins')->delete();
        $this->get('/install/app')->assertOk()
            ->assertSee('name="hub_source"', false)
            ->assertSee('name="hub_community_profile"', false)
            ->assertSee('name="install_purpose" value="live" checked', false);
    } finally {
        if ($wasLocked && file_exists($parked)) {
            rename($parked, $lock);
        }
    }

    Http::fake();
    $this->get('/install/finish')->assertOk()
        ->assertSee(__('install.hub_what_is_sent'))
        ->assertSee(config('hub.what_is_sent_url'), false);

    // Not the session that installed, and not production: nothing is sent.
    Http::assertNothingSent();
});

test('live or test goes with every ping, and only once it is answered', function () {
    hubFake();
    app(HubPing::class)->send();

    Setting::set('InstallPurpose', 'test', 'general');
    app(HubPing::class)->send();
    app(HubPing::class)->send();

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data())->values();
    expect($sent[0])->not->toHaveKey('purpose')
        ->and($sent[1]['purpose'] ?? null)->toBe('test')
        ->and($sent[2]['purpose'] ?? null)->toBe('test');
});

test('live or test can be changed later in Setup, and nothing else is accepted', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))
        ->assertOk()->assertSee('name="InstallPurpose"', false);

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['InstallPurpose' => 'live'])->assertSessionHasNoErrors();
    expect(Setting::get('InstallPurpose'))->toBe('live');

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['InstallPurpose' => 'staging'])->assertSessionHasErrors('InstallPurpose');
    expect(Setting::get('InstallPurpose'))->toBe('live');
});

test('the one-time box asks live or test too', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.hub.answers'), ['purpose' => 'test'])->assertRedirect();

    expect(Setting::get('InstallPurpose'))->toBe('test');
});

test('only staff who may change settings answer the questions or close a notice', function () {
    $role = AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => ['list_clients']]);
    $staff = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staff, 'admin')->get(route('admin.dashboard'))->assertOk()->assertDontSee(__('admin.hub.source'));
    $this->actingAs($staff, 'admin')->post(route('admin.hub.answers'), ['purpose' => 'test'])->assertForbidden();
    $this->actingAs($staff, 'admin')->post(route('admin.hub.notices.dismiss', 'any'))->assertForbidden();

    expect(Setting::get('InstallPurpose'))->toBeNull();
});

test('the ping says how PNLCS was installed', function () {
    $root = sys_get_temp_dir().'/hub-install-'.bin2hex(random_bytes(4));
    mkdir($root);
    $method = function () use ($root) {
        $installation = new Installation(app(PackageStore::class), app(ReleaseIndex::class), $root);

        return (new HubPing($installation, app(NoticeBoard::class)))->installMethod();
    };

    try {
        putenv('PNLCS_DIR=/app');
        expect($method())->toBe('docker');
        putenv('PNLCS_DIR');

        if (Installation::inContainer()) {
            return; // The suite itself runs in a container: every answer is docker.
        }

        expect($method())->toBe('unknown');

        mkdir($root.'/.git');
        expect($method())->toBe('git');
        rmdir($root.'/.git');

        file_put_contents($root.'/'.PackageFileSet::MANIFEST, '{}');
        expect($method())->toBe('package');

        // install.sh writes PNLCS_INSTALLED_BY=script into the .env it makes.
        config(['hub.installed_by' => 'script']);
        expect($method())->toBe('script');
    } finally {
        putenv('PNLCS_DIR');
        @unlink($root.'/'.PackageFileSet::MANIFEST);
        @rmdir($root.'/.git');
        @rmdir($root);
    }
});

test('install.sh marks the install it makes', function () {
    expect(file_get_contents(base_path('install.sh')))->toContain('PNLCS_INSTALLED_BY=script');
});
