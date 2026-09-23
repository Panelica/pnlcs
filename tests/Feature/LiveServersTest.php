<?php

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Server;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Live Servers: the Panelica servers, one click from their control panel.
 *
 * The login asks the panel who owns the server's API key (GET /v1/me) and has
 * the panel mint its own single-use login URL for that user. The browser is
 * sent there; PNLCS never sees a password.
 */

function liveAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function liveServersPanelica(array $attrs = []): Server
{
    return Server::factory()->create($attrs + [
        'type' => 'panelica',
        'hostname' => 'panel.example.test',
        // serverHost() prefers the IP when both are set; leave it empty so the
        // faked hostname is the address every request goes to.
        'ip_address' => null,
        'port' => 8443,
        'password' => 'pk_live_test',
        'access_hash' => 'sk_live_test',
    ]);
}

// No test here may reach a real network: an unmatched URL is an error.
beforeEach(fn () => Http::preventStrayRequests());

test('the dashboard quick actions link to Live Servers', function () {
    $this->actingAs(liveAdmin(), 'admin')->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.live-servers.index'), false)
        ->assertSee(__('admin.dashboard.live_servers'));
});

test('only Panelica servers are listed', function () {
    liveServersPanelica(['name' => 'Panelica Frankfurt']);
    Server::factory()->create(['type' => 'cpanel', 'name' => 'Old cPanel Box']);

    $this->actingAs(liveAdmin(), 'admin')->get(route('admin.live-servers.index'))
        ->assertOk()
        ->assertSee('Panelica Frankfurt')
        ->assertDontSee('Old cPanel Box');
});

test('log in sends the browser to the panel\'s own single-use login url', function () {
    $server = liveServersPanelica();
    Http::fake([
        'panel.example.test:8443/api/external/v1/me' => Http::response(['status' => 'success', 'data' => ['user_id' => 'root-uuid-1']]),
        'panel.example.test:8443/api/external/v1/accounts/root-uuid-1/sso-login' => Http::response(['status' => 'success', 'data' => ['url' => 'https://panel.example.test:8443/auto-login?token=abc', 'expires_in' => 300]]),
    ]);

    $this->actingAs(liveAdmin(), 'admin')
        ->post(route('admin.live-servers.login', $server))
        ->assertRedirect('https://panel.example.test:8443/auto-login?token=abc');

    // The key owner was asked for, then a login minted for exactly that user.
    Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_ends_with($r->url(), '/v1/me'));
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/accounts/root-uuid-1/sso-login'));
    expect(ActivityLog::where('description', 'like', 'Live Servers: logged in to%')->exists())->toBeTrue();
});

test('a key the panel will not identify gives a readable error, not a redirect', function () {
    $server = liveServersPanelica();
    Http::fake(['*' => Http::response(['status' => 'error'], 401)]);

    $this->actingAs(liveAdmin(), 'admin')
        ->post(route('admin.live-servers.login', $server))
        ->assertRedirect(route('admin.live-servers.index'))
        ->assertSessionHas('error', __('admin.live_servers.error_key_owner'));
});

test('a login url that is not https is refused', function () {
    $server = liveServersPanelica();
    Http::fake([
        '*/v1/me' => Http::response(['data' => ['user_id' => 'u1']]),
        '*/sso-login' => Http::response(['data' => ['url' => 'javascript:alert(1)']]),
    ]);

    $this->actingAs(liveAdmin(), 'admin')
        ->post(route('admin.live-servers.login', $server))
        ->assertRedirect(route('admin.live-servers.index'))
        ->assertSessionHas('error', __('admin.live_servers.error_no_url'));
});

test('an unreachable server gives a readable error', function () {
    $server = liveServersPanelica();
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

    $this->actingAs(liveAdmin(), 'admin')
        ->post(route('admin.live-servers.login', $server))
        ->assertSessionHas('error', __('admin.live_servers.error_unreachable'));
});

test('a non-Panelica server id is a 404 and nothing is called', function () {
    $server = Server::factory()->create(['type' => 'cpanel']);
    Http::fake();

    $this->actingAs(liveAdmin(), 'admin')
        ->post(route('admin.live-servers.login', $server))
        ->assertNotFound();

    Http::assertNothingSent();
});

test('an admin without server permission cannot list or log in', function () {
    $server = liveServersPanelica();
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Tickets', 'permissions' => ['list_tickets']])->id,
    ]);
    Http::fake();

    $this->actingAs($admin, 'admin')->get(route('admin.live-servers.index'))->assertForbidden();
    $this->actingAs($admin, 'admin')->post(route('admin.live-servers.login', $server))->assertForbidden();
    Http::assertNothingSent();
});

test('the Live Servers text is translated, not typed into the page', function () {
    app()->setLocale('tr');
    expect(__('admin.dashboard.live_servers'))->toBe('Canlı Sunucular')
        ->and(__('admin.live_servers.login'))->toBe('Giriş yap');
    app()->setLocale('en');
    expect(__('admin.dashboard.live_servers'))->toBe('Live Servers');
});
