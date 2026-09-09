<?php

use App\Models\Admin;
use App\Models\AdminRole;

/*
 * Staff management is a permission like any other, and it used to be enough
 * to become a full administrator: create a colleague on the full-admin role,
 * or a role with every right, and sign in as that. The rights an operator can
 * hand out are capped at the rights they hold.
 */

function staffManager(array $permissions): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Staff '.uniqid(), 'is_full_admin' => false, 'permissions' => $permissions])->id,
    ]);
}

function fullAdminRole(): AdminRole
{
    return AdminRole::factory()->create(['name' => 'Owner '.uniqid(), 'is_full_admin' => true, 'permissions' => []]);
}

test('a staff manager cannot create an administrator on the full-admin role', function () {
    $actor = staffManager(['manage_staff']);

    $this->actingAs($actor, 'admin')->post(route('admin.config.admins.store'), [
        'username' => 'newowner', 'email' => 'newowner@example.test', 'password' => 'secret-enough',
        'first_name' => 'New', 'last_name' => 'Owner', 'role_id' => fullAdminRole()->id,
    ])->assertSessionHasErrors('role_id');

    expect(Admin::where('username', 'newowner')->exists())->toBeFalse();
});

test('a staff manager cannot move an administrator onto a role with rights they do not hold', function () {
    // The actor may edit the target (whose rights are within their own) but
    // not lift them onto a role that is not.
    $actor = staffManager(['manage_staff', 'list_clients']);
    $target = staffManager(['list_clients']);
    $bigger = AdminRole::factory()->create(['name' => 'Bigger', 'is_full_admin' => false, 'permissions' => ['manage_staff', 'manage_roles', 'manage_settings']]);

    $this->actingAs($actor, 'admin')->put(route('admin.config.admins.update', $target), [
        'username' => $target->username, 'email' => $target->email, 'first_name' => 'T', 'last_name' => 'T', 'role_id' => $bigger->id,
    ])->assertSessionHasErrors('role_id');

    expect($target->fresh()->role_id)->not->toBe($bigger->id);
});

test('a staff manager cannot edit a full administrator at all', function () {
    $actor = staffManager(['manage_staff']);
    $owner = Admin::factory()->create(['role_id' => fullAdminRole()->id]);

    $this->actingAs($actor, 'admin')->put(route('admin.config.admins.update', $owner), [
        'username' => $owner->username, 'email' => $owner->email, 'first_name' => 'T', 'last_name' => 'T',
        'role_id' => $owner->role_id, 'password' => 'taken-over-now',
    ])->assertForbidden();
});

test('a role manager cannot create a full-admin role or one with rights they do not hold', function () {
    $actor = staffManager(['manage_roles', 'list_clients']);

    $this->actingAs($actor, 'admin')->post(route('admin.config.admin-roles.store'), [
        'name' => 'Sneaky', 'is_full_admin' => '1',
    ])->assertSessionHasErrors('is_full_admin');

    $this->actingAs($actor, 'admin')->post(route('admin.config.admin-roles.store'), [
        'name' => 'Sneaky2', 'permissions' => ['manage_roles', 'manage_settings'],
    ])->assertSessionHasErrors('permissions');

    expect(AdminRole::whereIn('name', ['Sneaky', 'Sneaky2'])->count())->toBe(0);
});

test('a full administrator still hands out anything', function () {
    $owner = Admin::factory()->create(['role_id' => fullAdminRole()->id]);

    $this->actingAs($owner, 'admin')->post(route('admin.config.admins.store'), [
        'username' => 'colleague', 'email' => 'colleague@example.test', 'password' => 'secret-enough',
        'first_name' => 'Co', 'last_name' => 'League', 'role_id' => fullAdminRole()->id,
    ])->assertSessionHasNoErrors();

    expect(Admin::where('username', 'colleague')->exists())->toBeTrue();
});
