<?php

use App\Http\Middleware\BlockBannedIp;
use App\Models\Admin;
use App\Models\BannedIp;
use Illuminate\Support\Facades\Cache;

/**
 * banned_ips previously had full CRUD but nothing ever read the table —
 * a ban blocked nothing. The BlockBannedIp middleware now guards the whole
 * client area (exact match or trailing-* prefix patterns). Admin routes are
 * deliberately NOT covered so an admin who bans their own IP can undo it.
 */
beforeEach(fn () => Cache::forget(BlockBannedIp::CACHE_KEY));

test('a banned ip is blocked from the client area', function () {
    BannedIp::create(['ip' => '203.0.113.66', 'reason' => 'abuse']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.66'])
        ->get(route('client.login'))
        ->assertForbidden();
});

test('an unbanned ip is served normally', function () {
    BannedIp::create(['ip' => '203.0.113.66', 'reason' => 'abuse']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.67'])
        ->get(route('client.login'))
        ->assertOk();
});

test('trailing wildcard patterns block the whole prefix', function () {
    BannedIp::create(['ip' => '198.51.100.*', 'reason' => 'botnet range']);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
        ->get(route('client.login'))
        ->assertForbidden();

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.101.42'])
        ->get(route('client.login'))
        ->assertOk();
});

test('admin routes stay reachable from a banned ip', function () {
    BannedIp::create(['ip' => '203.0.113.66', 'reason' => 'abuse']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.66'])
        ->get(route('admin.login'))
        ->assertOk();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.66'])
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('removing the ban restores access immediately (cache is busted)', function () {
    $ban = BannedIp::create(['ip' => '203.0.113.66', 'reason' => 'temporary']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.66'])
        ->get(route('client.login'))
        ->assertForbidden();

    $ban->delete();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.66'])
        ->get(route('client.login'))
        ->assertOk();
});

test('adding a ban takes effect immediately (cache is busted on save)', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.66'])
        ->get(route('client.login'))
        ->assertOk();

    BannedIp::create(['ip' => '203.0.113.66', 'reason' => 'fresh ban']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.66'])
        ->get(route('client.login'))
        ->assertForbidden();
});
