<?php

use App\Constants\Permissions;
use App\Models\Admin;
use App\Models\AdminRole;

/**
 * The role the installer falls back on giving nobody any access.
 *
 * Step three seeds the database and then takes the first admin role it finds.
 * The seeding is wrapped in a catch, so when it fails the installer makes the
 * role itself - and it made one with a 'slug' the table does not have and no
 * is_full_admin. The slug is dropped on the way in, is_full_admin falls to its
 * default of 0, and the permissions it does set are ['*'], a wildcard nothing
 * in this application expands: hasPermission() compares the string it is given
 * against that array. So the one administrator on a newly installed panel is
 * locked out of every screen that asks for a permission.
 *
 * Both seeders, which is where a role is normally born, set is_full_admin.
 *
 * The fallback is reached when there is no role to take and no seeding left to
 * do, which is the state these tests set up.
 */
function installerSessionPastSeeding(): array
{
    return ['install.in_progress' => true, 'install.seeded' => true];
}

function installerAdminPayload(): array
{
    return [
        'username' => 'owner',
        'email' => 'owner@example.com',
        'first_name' => 'Owner',
        'last_name' => 'One',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];
}

beforeEach(function () {
    $path = sys_get_temp_dir().'/pnlcs-installer-role-'.uniqid();
    mkdir($path, 0777, true);
    app()->useStoragePath($path);

    Admin::query()->delete();
    AdminRole::query()->delete();
});

it('gives the administrator it creates a role that can actually do something', function () {
    $this->withSession(installerSessionPastSeeding())
        ->post('/install/admin', installerAdminPayload())
        ->assertRedirect('/install/app');

    $admin = Admin::where('username', 'owner')->firstOrFail();

    expect($admin->hasPermission(Permissions::LIST_CLIENTS))->toBeTrue();
});

it('marks the fallback role as a full administrator', function () {
    $this->withSession(installerSessionPastSeeding())
        ->post('/install/admin', installerAdminPayload());

    expect((bool) AdminRole::query()->orderBy('id')->first()->is_full_admin)->toBeTrue();
});

it('still creates the administrator the operator asked for', function () {
    $this->withSession(installerSessionPastSeeding())
        ->post('/install/admin', installerAdminPayload());

    $admin = Admin::where('username', 'owner')->firstOrFail();

    expect($admin->email)->toBe('owner@example.com')
        ->and($admin->first_name)->toBe('Owner');
});

it('takes a role that already exists rather than making another', function () {
    $existing = AdminRole::create([
        'name' => 'Full Administrator',
        'description' => 'Full access to all areas',
        'is_full_admin' => true,
    ]);

    $this->withSession(installerSessionPastSeeding())
        ->post('/install/admin', installerAdminPayload());

    expect(AdminRole::query()->count())->toBe(1)
        ->and(Admin::where('username', 'owner')->firstOrFail()->role_id)->toBe($existing->id);
});
