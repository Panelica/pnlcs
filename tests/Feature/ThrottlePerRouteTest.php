<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Support\FakeProxmox;

/*
 * `throttle:N,M` counted per route.
 *
 * The framework keyed an unnamed limit on the signed-in user alone, so every
 * throttled route shared one counter per customer. The VPS page polls status,
 * graphs, snapshots and backups; by the time the customer pressed Reinstall
 * (3 per 10 minutes) the shared count was long past 3 and the first click came
 * back "Too Many Attempts".
 */

require_once __DIR__.'/../Support/proxmox_helpers.php';

beforeEach(function () {
    \App\Models\Currency::getDefault() ?? \App\Models\Currency::factory()->default()->create();
});

function throttleTestRoutes(): void
{
    Route::middleware(['web', 'throttle:2,1'])->get('/_throttle/a', fn () => 'a')->name('throttle-test.a');
    Route::middleware(['web', 'throttle:2,1'])->get('/_throttle/b', fn () => 'b')->name('throttle-test.b');
}

it('counts each throttled route on its own, for a customer', function () {
    throttleTestRoutes();
    $user = User::factory()->create();

    $this->actingAs($user)->get('/_throttle/a')->assertOk();
    $this->actingAs($user)->get('/_throttle/a')->assertOk();
    $this->actingAs($user)->get('/_throttle/a')->assertStatus(429);

    // Before: b shared a's counter and was refused at once.
    $this->actingAs($user)->get('/_throttle/b')->assertOk();
});

it('still limits each route, and each customer separately', function () {
    throttleTestRoutes();
    [$ada, $bob] = [User::factory()->create(), User::factory()->create()];

    $this->actingAs($ada)->get('/_throttle/b')->assertOk();
    $this->actingAs($ada)->get('/_throttle/b')->assertOk();
    $this->actingAs($ada)->get('/_throttle/b')->assertStatus(429);
    $this->actingAs($bob)->get('/_throttle/b')->assertOk();
});

it('counts a visitor per route too, so a contact form does not use up their logins', function () {
    throttleTestRoutes();

    $this->get('/_throttle/a');
    $this->get('/_throttle/a');
    $this->get('/_throttle/a')->assertStatus(429);
    $this->get('/_throttle/b')->assertOk();
});

it('counts a signed-in admin per route, not as an anonymous address', function () {
    throttleTestRoutes();
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get('/_throttle/a');
    $this->actingAs($admin, 'admin')->get('/_throttle/a');
    $this->actingAs($admin, 'admin')->get('/_throttle/a')->assertStatus(429);
    // Another admin behind the same office address is not refused with them.
    $this->actingAs(Admin::factory()->create(), 'admin')->get('/_throttle/a')->assertOk();
});

it('lets the customer reinstall after the VPS page has been polling', function () {
    FakeProxmox::install()->template(9000);
    $service = pveService(pveServer());
    $customer = pveCustomer($service);

    foreach (range(1, 10) as $_) {
        $this->actingAs($customer)->getJson(route('client.services.vps.status', $service));
    }

    expect($this->actingAs($customer)->postJson(route('client.services.vps.reinstall', $service), [])->status())->not->toBe(429);
});
