<?php

use App\Models\Admin;
use App\Models\AdminRole;

/**
 * Removing staff beyond your own rights.
 *
 * Editing an administrator or a role already asks whether the operator could
 * have granted it. Deleting did not: staff management alone was enough to
 * delete a full administrator, and role management to delete a role with
 * rights the operator does not hold.
 */
function deletingStaffActor(array $permissions): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Staff '.uniqid(), 'is_full_admin' => false, 'permissions' => $permissions])->id,
    ]);
}

function deletingOwnerRole(): AdminRole
{
    return AdminRole::factory()->create(['name' => 'Owner '.uniqid(), 'is_full_admin' => true, 'permissions' => []]);
}

it('does not let a staff manager delete a full administrator', function () {
    $actor = deletingStaffActor(['manage_staff']);
    $owner = Admin::factory()->create(['role_id' => deletingOwnerRole()->id]);

    $this->actingAs($actor, 'admin')->delete(route('admin.config.admins.destroy', $owner))->assertForbidden();

    expect(Admin::whereKey($owner->id)->exists())->toBeTrue();
});

it('does not let a staff manager delete an administrator with rights they do not hold', function () {
    $actor = deletingStaffActor(['manage_staff', 'list_clients']);
    $target = deletingStaffActor(['list_clients', 'manage_settings']);

    $this->actingAs($actor, 'admin')->delete(route('admin.config.admins.destroy', $target))->assertForbidden();

    expect(Admin::whereKey($target->id)->exists())->toBeTrue();
});

it('still lets a staff manager delete an administrator within their rights', function () {
    $actor = deletingStaffActor(['manage_staff', 'list_clients']);
    $target = deletingStaffActor(['list_clients']);

    $this->actingAs($actor, 'admin')->delete(route('admin.config.admins.destroy', $target))->assertRedirect();

    expect(Admin::whereKey($target->id)->exists())->toBeFalse();
});

it('still lets a full administrator delete another full administrator', function () {
    $actor = Admin::factory()->create(['role_id' => deletingOwnerRole()->id]);
    $other = Admin::factory()->create(['role_id' => deletingOwnerRole()->id]);

    $this->actingAs($actor, 'admin')->delete(route('admin.config.admins.destroy', $other))->assertRedirect();

    expect(Admin::whereKey($other->id)->exists())->toBeFalse();
});

it('does not let a role manager delete a role with rights they do not hold', function () {
    $actor = deletingStaffActor(['manage_roles']);
    $role = AdminRole::factory()->create(['name' => 'Bigger '.uniqid(), 'is_full_admin' => false, 'permissions' => ['manage_roles', 'manage_settings']]);
    $owners = deletingOwnerRole();

    $this->actingAs($actor, 'admin')->delete(route('admin.config.admin-roles.destroy', $role))->assertForbidden();
    $this->actingAs($actor, 'admin')->delete(route('admin.config.admin-roles.destroy', $owners))->assertForbidden();

    expect(AdminRole::whereKey([$role->id, $owners->id])->count())->toBe(2);
});

it('still lets a role manager delete an empty role within their rights', function () {
    $actor = deletingStaffActor(['manage_roles', 'list_clients']);
    $role = AdminRole::factory()->create(['name' => 'Small '.uniqid(), 'is_full_admin' => false, 'permissions' => ['list_clients']]);

    $this->actingAs($actor, 'admin')->delete(route('admin.config.admin-roles.destroy', $role))->assertRedirect();

    expect(AdminRole::whereKey($role->id)->exists())->toBeFalse();
});
