<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * What a member of staff can do through the API.
 *
 * Every admin screen is behind a permission. The API was behind none of them:
 * the middleware checked that the caller was somebody and waved through all
 * 160 endpoints. A support agent could generate a credential — or simply send
 * their own panel username and password, which the WHMCS-compatible path
 * accepts — and then read every invoice, delete clients and write settings
 * that the panel would not even show them. Two-factor authentication sits on
 * the login form, so that path went around it too.
 */
function apiStaff(array $permissions): Admin
{
    return Admin::factory()->create([
        'password' => bcrypt('staff-password'),
        'role_id' => AdminRole::factory()->create([
            'is_full_admin' => false,
            'permissions' => $permissions,
        ])->id,
    ]);
}

function credentialFor(Admin $admin): array
{
    $secret = Str::random(64);

    $credential = ApiCredential::create([
        'admin_id' => $admin->id,
        'identifier' => Str::random(32),
        'secret' => ApiCredential::hashSecret($secret),
        'description' => 'test',
        'active' => true,
    ]);

    return ['api_key' => $credential->identifier, 'api_secret' => $secret];
}

test('a support credential cannot read the client list', function () {
    Client::factory()->create(['first_name' => 'Private', 'last_name' => 'Customer']);

    $this->getJson('/api/v1/getclients?'.http_build_query(credentialFor(apiStaff(['list_tickets']))))
        ->assertForbidden();
});

test('a support credential cannot write a system setting', function () {
    $this->postJson('/api/v1/setconfigurationvalue', array_merge(
        credentialFor(apiStaff(['list_tickets'])),
        ['setting' => 'CompanyName', 'value' => 'Taken Over']
    ))->assertForbidden();

    expect(Setting::where('setting', 'CompanyName')->value('value'))->not->toBe('Taken Over');
});

test('a support credential still reads tickets', function () {
    $this->getJson('/api/v1/gettickets?'.http_build_query(credentialFor(apiStaff(['list_tickets']))))
        ->assertOk();
});

test('a full administrator credential is unaffected', function () {
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);

    $this->getJson('/api/v1/getclients?'.http_build_query(credentialFor($admin)))->assertOk();
});

test('staff cannot use their panel password to go around their permissions', function () {
    $staff = apiStaff(['list_tickets']);

    $this->getJson('/api/v1/getclients?'.http_build_query([
        'username' => $staff->username,
        'password' => 'staff-password',
    ]))->assertForbidden();
});

test('a credential whose owner is gone is refused', function () {
    $staff = apiStaff(['list_clients']);
    $credential = credentialFor($staff);
    $staff->delete();

    // Removing a member of staff takes their credentials with them, so the key
    // is not recognised at all. The middleware also refuses a credential whose
    // owner has gone by some other route - see asAdmin().
    $this->getJson('/api/v1/getclients?'.http_build_query($credential))
        ->assertUnauthorized();

    expect(ApiCredential::where('identifier', $credential['api_key'])->exists())->toBeFalse();
});
