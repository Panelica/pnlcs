<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Services\Hub\HubPing;
use App\Services\Hub\NoticeBoard;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/*
 * Notices from the PNLCS team arrive in the hub's answer and are trusted only
 * with the notice key's signature. A hub (or DNS) that is taken over must not
 * be able to put "download this now" on every PNLCS admin panel, and an old
 * signed notice must not come back for ever.
 */

/** @return array{0: OpenSSLAsymmetricKey, 1: string} private key, public PEM */
function noticeKeys(): array
{
    $private = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

    return [$private, openssl_pkey_get_details($private)['key']];
}

function signed(array $statement, $private): array
{
    $text = json_encode($statement);
    openssl_sign($text, $signature, $private, OPENSSL_ALGO_SHA256);

    return ['statement' => $text, 'signature' => base64_encode($signature)];
}

function notice(array $over = []): array
{
    return $over + [
        'id' => 'n-'.Str::random(6),
        'level' => 'critical',
        'title' => 'Security update: install the latest release now',
        'body' => 'A fix for a flaw in the client area.',
        'url' => 'https://github.com/Panelica/pnlcs/security/advisories/GHSA-x',
        'affects' => '',
        'issued_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'),
        'expires_at' => now()->addDays(20)->utc()->format('Y-m-d\TH:i:s\Z'),
    ];
}

/** Keys made, the public half configured; the private one returned. */
function noticeKeyInstalled()
{
    [$private, $public] = noticeKeys();
    config(['hub.notice_public_key' => $public]);

    return $private;
}

function hubSends(array $items): void
{
    // A fresh fake each time: stubs added to an existing fake do not replace it.
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(['hub.pnlcs.com/*' => Http::response(['protocol' => 1, 'notices' => $items])]);
    app(HubPing::class)->send();
}

function board(): NoticeBoard
{
    return app(NoticeBoard::class);
}

test('a signed critical notice is shown on every admin page', function () {
    $key = noticeKeyInstalled();
    hubSends([signed(notice(['title' => 'Install 9.9.9 now']), $key)]);

    $admin = Admin::factory()->create();
    foreach (['admin.dashboard', 'admin.clients.index'] as $page) {
        $this->actingAs($admin, 'admin')->get(route($page))->assertOk()->assertSee('Install 9.9.9 now');
    }
});

test('a notice the notice key did not sign is not shown', function () {
    noticeKeyInstalled();
    [$otherKey] = noticeKeys();

    hubSends([signed(notice(), $otherKey)]);

    expect(board()->current('critical'))->toBe([]);
});

test('a statement changed after signing is not shown', function () {
    $key = noticeKeyInstalled();
    $item = signed(notice(['title' => 'Harmless']), $key);
    $item['statement'] = str_replace('Harmless', 'Download this now', $item['statement']);

    hubSends([$item]);

    expect(board()->current('critical'))->toBe([]);
});

test('without a notice key nothing is shown, whatever arrives', function () {
    [$private] = noticeKeys();
    config(['hub.notice_public_key' => null]);

    hubSends([signed(notice(), $private)]);

    expect(board()->current('critical'))->toBe([]);
});

test('a notice past its end, or lasting more than 90 days, is not shown', function () {
    $key = noticeKeyInstalled();

    hubSends([
        signed(notice(['id' => 'old', 'issued_at' => now()->subDays(40)->utc()->format('Y-m-d\TH:i:s\Z'), 'expires_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z')]), $key),
        signed(notice(['id' => 'forever', 'expires_at' => now()->addDays(120)->utc()->format('Y-m-d\TH:i:s\Z')]), $key),
        signed(notice(['id' => 'no-end', 'expires_at' => null]), $key),
    ]);

    expect(board()->current('critical'))->toBe([]);
});

test('a notice that has ended stops showing', function () {
    $key = noticeKeyInstalled();
    hubSends([signed(notice(['id' => 'soon', 'expires_at' => now()->addDays(2)->utc()->format('Y-m-d\TH:i:s\Z')]), $key)]);
    expect(board()->current('critical'))->toHaveCount(1);

    $this->travel(3)->days();

    expect(board()->current('critical'))->toBe([]);
});

test('only PNLCS addresses, and GitHub under Panelica, may be linked', function () {
    foreach ([
        'https://pnlcs.com/blog/1-7-1' => true,
        'https://docs.pnlcs.com/updates/' => true,
        'https://github.com/Panelica/pnlcs/releases' => true,
        'http://pnlcs.com/' => false,
        'https://pnlcs.com.evil.test/' => false,
        'https://evil.test/pnlcs.com' => false,
        'https://pnlcs.com@evil.test/' => false,
        'https://github.com/attacker/pnlcs' => false,
        'https://github.com/Panelica' => false,
        'https://docs.pnlcs.com/../x' => false,
        'https://docs.pnlcs.com/a/./b' => false,
        'https://pnlcs.com/?next=https://evil.test' => false,
        'javascript:alert(1)' => false,
    ] as $url => $allowed) {
        expect(NoticeBoard::allowedUrl($url))->toBe($allowed, $url);
    }

    $key = noticeKeyInstalled();
    hubSends([signed(notice(['url' => 'https://evil.test/update.zip']), $key)]);
    expect(board()->current('critical'))->toBe([]);
});

test('a revoked notice never comes back, even sent again', function () {
    $key = noticeKeyInstalled();
    $original = notice(['id' => '2026-11-sec-01']);
    hubSends([signed($original, $key)]);
    expect(board()->current('critical'))->toHaveCount(1);

    hubSends([signed(['revokes' => '2026-11-sec-01', 'issued_at' => $original['issued_at'], 'expires_at' => $original['expires_at']], $key)]);
    expect(board()->current('critical'))->toBe([]);

    hubSends([signed($original, $key)]);
    expect(board()->current('critical'))->toBe([])
        ->and(json_decode(Setting::get('hub_revoked_notices'), true))->toContain('2026-11-sec-01');
});

test('an unsigned revocation is not obeyed', function () {
    $key = noticeKeyInstalled();
    hubSends([signed(notice(['id' => 'keep']), $key)]);

    hubSends([['statement' => json_encode(['revokes' => 'keep']), 'signature' => base64_encode('nope')]]);

    expect(board()->current('critical'))->toHaveCount(1);
});

test('a notice for versions this install is not on is not shown', function () {
    $key = noticeKeyInstalled();

    hubSends([signed(notice(['id' => 'older', 'affects' => '<0.0.1']), $key)]);

    expect(board()->current('critical'))->toBe([]);
});

test('the admins are mailed about a critical notice once', function () {
    $key = noticeKeyInstalled();
    Setting::set('SystemEmailAddress', 'ops@acme.test');
    $role = AdminRole::factory()->create(['is_full_admin' => true]);
    Admin::factory()->create(['role_id' => $role->id, 'email' => 'owner@acme.test']);
    Admin::factory()->create(['role_id' => $role->id, 'email' => 'gone@acme.test', 'is_disabled' => true]);
    $mails = new ArrayObject;
    Event::listen(MessageSent::class, fn ($e) => $mails->append($e->message));
    $item = signed(notice(['title' => 'Install 9.9.9 now']), $key);

    hubSends([$item]);
    hubSends([$item]);

    $to = collect($mails[0]->getTo())->map->getAddress()->all();
    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getSubject())->toBe('[PNLCS] Install 9.9.9 now')
        ->and($to)->toContain('ops@acme.test', 'owner@acme.test')
        ->and($to)->not->toContain('gone@acme.test');
});

test('an info notice is on the dashboard, is not mailed, and can be closed', function () {
    $key = noticeKeyInstalled();
    $mails = new ArrayObject;
    Event::listen(MessageSent::class, fn ($e) => $mails->append($e));
    hubSends([signed(notice(['id' => 'hello', 'level' => 'info', 'title' => 'PNLCS 2.0 is out']), $key)]);
    Setting::set('hub_asked', '1');
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertSee('PNLCS 2.0 is out');
    $this->actingAs($admin, 'admin')->get(route('admin.clients.index'))->assertDontSee('PNLCS 2.0 is out');
    $this->actingAs($admin, 'admin')->post(route('admin.hub.notices.dismiss', 'hello'))->assertRedirect();
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertDontSee('PNLCS 2.0 is out');

    expect($mails)->toHaveCount(0);
});

test('a critical notice cannot be closed', function () {
    $key = noticeKeyInstalled();
    hubSends([signed(notice(['id' => 'stays']), $key)]);

    $this->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.hub.notices.dismiss', 'stays'));

    expect(board()->current('critical'))->toHaveCount(1);
});

test('a notice is plain text: markup in it is shown, not run', function () {
    $key = noticeKeyInstalled();
    hubSends([signed(notice(['title' => '<script>alert(1)</script>Update', 'body' => '<img src=x onerror=alert(2)>']), $key)]);

    $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x onerror', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;Update', false);
});
