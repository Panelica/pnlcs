<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;

/**
 * The general settings page has a Maintenance Mode switch. It was written to
 * the database and read by nothing, so an operator who put the panel into
 * maintenance kept serving the customer area as normal.
 *
 * The switch must close the customer area without locking the operator out —
 * otherwise turning it on would be a one-way door.
 */
beforeEach(fn () => Setting::set('MaintenanceMode', '0', 'general'));

function clientUser(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

test('the customer area is open when the switch is off', function () {
    $this->get(route('client.login'))->assertOk();
});

test('the customer area closes when the switch is on', function () {
    Setting::set('MaintenanceMode', '1', 'general');

    $response = $this->get(route('client.login'));

    $response->assertStatus(503)
        ->assertHeader('Retry-After')
        ->assertSee(__('client.maintenance.title'));
});

test('a logged-in customer is also held out', function () {
    Setting::set('MaintenanceMode', '1', 'general');

    $this->actingAs(clientUser())
        ->get(route('client.home'))
        ->assertStatus(503);
});

test('the operator is never locked out of the admin area', function () {
    Setting::set('MaintenanceMode', '1', 'general');

    $this->get(route('admin.login'))->assertOk();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('an admin browsing the customer area is not held out', function () {
    Setting::set('MaintenanceMode', '1', 'general');

    // Whatever the page decides to do with an authenticated admin, the
    // maintenance gate itself must not be what stops them.
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('client.login'))
        ->assertRedirect();
});

test('the API stays reachable for monitoring', function () {
    Setting::set('MaintenanceMode', '1', 'general');

    $this->getJson('/api/health')->assertOk();
});

test('an unreadable or unset switch never takes the site down', function () {
    Setting::where('setting', 'MaintenanceMode')->delete();

    $this->get(route('client.login'))->assertOk();
});
