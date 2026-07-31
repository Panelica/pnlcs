<?php

use App\Constants\Permissions;
use App\Models\Admin;
use App\Models\AdminRole;
use Database\Seeders\DatabaseSeeder;

/**
 * Giving a member of staff a role that can do something.
 *
 * The roles screen asked for a name and a description. It never asked which
 * permissions the role should have, and the controller never stored any, so
 * every role created through the panel came out with no permissions at all —
 * an account that could sign in, see the dashboard, and get 403 everywhere
 * else. The only way to grant a single permission was to write the JSON column
 * by hand. The catalogue of permissions had been sitting in
 * app/Constants/Permissions.php, complete and grouped, referenced by nothing.
 */
function rolesAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('a role created through the panel keeps the permissions it was given', function () {
    $this->actingAs(rolesAdmin(), 'admin')
        ->post(route('admin.config.admin-roles.store'), [
            'name' => 'Invoicing',
            'description' => 'Bills and nothing else',
            'permissions' => ['list_invoices', 'view_invoices'],
        ])->assertRedirect();

    expect(AdminRole::where('name', 'Invoicing')->firstOrFail()->permissions)
        ->toBe(['list_invoices', 'view_invoices']);
});

test('editing a role changes what it can do', function () {
    $role = AdminRole::factory()->create([
        'name' => 'Support',
        'is_full_admin' => false,
        'permissions' => ['list_tickets'],
    ]);

    $this->actingAs(rolesAdmin(), 'admin')
        ->put(route('admin.config.admin-roles.update', $role), [
            'name' => 'Support',
            'permissions' => ['list_tickets', 'view_tickets', 'reply_tickets'],
        ])->assertRedirect();

    expect($role->fresh()->permissions)->toBe(['list_tickets', 'view_tickets', 'reply_tickets']);
});

test('a permission the code does not know is refused', function () {
    $this->actingAs(rolesAdmin(), 'admin')
        ->post(route('admin.config.admin-roles.store'), [
            'name' => 'Made Up',
            'permissions' => ['do_whatever_i_like'],
        ])->assertSessionHasErrors('permissions.0');

    expect(AdminRole::where('name', 'Made Up')->exists())->toBeFalse();
});

test('what the panel grants is what the routes ask for', function () {
    $role = AdminRole::factory()->create([
        'name' => 'Client Desk',
        'is_full_admin' => false,
        'permissions' => [],
    ]);

    $this->actingAs(rolesAdmin(), 'admin')
        ->put(route('admin.config.admin-roles.update', $role), [
            'name' => 'Client Desk',
            'permissions' => ['list_clients'],
        ]);

    $staff = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($staff, 'admin')->get(route('admin.clients.index'))->assertOk();
    $this->actingAs($staff, 'admin')->get(route('admin.products.index'))->assertForbidden();
});

test('the roles that ship with the software use permission names the routes recognise', function () {
    $this->seed(DatabaseSeeder::class);

    $known = Permissions::all();

    foreach (AdminRole::where('is_full_admin', false)->get() as $role) {
        expect(array_diff($role->permissions ?? [], $known))
            ->toBe([], "role [{$role->name}] grants permissions nothing checks");
    }
});

test('the last way in cannot be closed from the roles screen', function () {
    $admin = rolesAdmin();

    // Taking the full-administrator flag off your own role, with nothing else
    // granting manage_roles, leaves the installation with no way back in.
    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.admin-roles.update', $admin->role), [
            'name' => $admin->role->name,
            'permissions' => ['list_clients'],
        ])->assertSessionHasErrors();

    expect($admin->role->fresh()->hasPermission('manage_roles'))->toBeTrue();
});
