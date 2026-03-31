<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;


// ===== HELPERS =====

function makeStaffAdmin(array $attrs = []): Admin
{
    $role = AdminRole::factory()->fullAdmin()->create();
    return Admin::factory()->create(array_merge(['role_id' => $role->id], $attrs));
}

// ===== ADMIN LIST =====

test('admin can view staff management page', function () {
    $admin = makeStaffAdmin();
    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.admins'));
    $response->assertStatus(200)->assertSee('Staff Management');
});

test('staff page lists all admins', function () {
    $admin = makeStaffAdmin();
    $other = makeStaffAdmin(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.admins'));
    $response->assertSee('Jane')->assertSee('Doe');
});

// ===== CREATE ADMIN =====

test('admin can create a new admin', function () {
    $actor = makeStaffAdmin();
    $role = AdminRole::factory()->create();

    $response = $this->actingAs($actor, 'admin')->post(route('admin.config.admins.store'), [
        'first_name' => 'Test',
        'last_name'  => 'User',
        'username'   => 'testuser123',
        'email'      => 'testuser123@example.com',
        'password'   => 'secret123',
        'role_id'    => $role->id,
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseHas('admins', ['username' => 'testuser123', 'email' => 'testuser123@example.com']);
});

test('create admin requires all fields', function () {
    $actor = makeStaffAdmin();
    $response = $this->actingAs($actor, 'admin')->post(route('admin.config.admins.store'), []);
    $response->assertSessionHasErrors(['username', 'email', 'password', 'first_name', 'last_name', 'role_id']);
});

test('create admin enforces unique username', function () {
    $actor = makeStaffAdmin(['username' => 'existing']);
    $role = AdminRole::factory()->create();

    $response = $this->actingAs($actor, 'admin')->post(route('admin.config.admins.store'), [
        'first_name' => 'Test',
        'last_name'  => 'User',
        'username'   => 'existing',
        'email'      => 'new@example.com',
        'password'   => 'secret123',
        'role_id'    => $role->id,
    ]);

    $response->assertSessionHasErrors('username');
});

test('admin password is hashed on creation', function () {
    $actor = makeStaffAdmin();
    $role = AdminRole::factory()->create();

    $this->actingAs($actor, 'admin')->post(route('admin.config.admins.store'), [
        'first_name' => 'Hash',
        'last_name'  => 'Test',
        'username'   => 'hashtest',
        'email'      => 'hash@example.com',
        'password'   => 'plaintext',
        'role_id'    => $role->id,
    ]);

    $created = Admin::where('username', 'hashtest')->first();
    expect($created->password)->not->toBe('plaintext');
    expect(\Illuminate\Support\Facades\Hash::check('plaintext', $created->password))->toBeTrue();
});

// ===== UPDATE ADMIN =====

test('admin can update another admin', function () {
    $actor = makeStaffAdmin();
    $target = makeStaffAdmin(['first_name' => 'Old', 'last_name' => 'Name']);

    $response = $this->actingAs($actor, 'admin')->put(route('admin.config.admins.update', $target), [
        'first_name' => 'New',
        'last_name'  => 'Name',
        'username'   => $target->username,
        'email'      => $target->email,
        'role_id'    => $target->role_id,
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseHas('admins', ['id' => $target->id, 'first_name' => 'New']);
});

test('update admin skips password if blank', function () {
    $actor = makeStaffAdmin();
    $target = makeStaffAdmin();
    $originalHash = $target->password;

    $this->actingAs($actor, 'admin')->put(route('admin.config.admins.update', $target), [
        'first_name' => $target->first_name,
        'last_name'  => $target->last_name,
        'username'   => $target->username,
        'email'      => $target->email,
        'role_id'    => $target->role_id,
        'password'   => '',
    ]);

    $target->refresh();
    expect($target->password)->toBe($originalHash);
});

test('update admin changes password when provided', function () {
    $actor = makeStaffAdmin();
    $target = makeStaffAdmin();
    $originalHash = $target->password;

    $this->actingAs($actor, 'admin')->put(route('admin.config.admins.update', $target), [
        'first_name' => $target->first_name,
        'last_name'  => $target->last_name,
        'username'   => $target->username,
        'email'      => $target->email,
        'role_id'    => $target->role_id,
        'password'   => 'newpassword123',
    ]);

    $target->refresh();
    expect($target->password)->not->toBe($originalHash);
    expect(\Illuminate\Support\Facades\Hash::check('newpassword123', $target->password))->toBeTrue();
});

// ===== DELETE ADMIN =====

test('admin can delete another admin', function () {
    $actor = makeStaffAdmin();
    $target = makeStaffAdmin();

    $response = $this->actingAs($actor, 'admin')->delete(route('admin.config.admins.destroy', $target));

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseMissing('admins', ['id' => $target->id]);
});

test('admin cannot delete their own account', function () {
    $actor = makeStaffAdmin();

    $response = $this->actingAs($actor, 'admin')->delete(route('admin.config.admins.destroy', $actor));

    $response->assertRedirect()->assertSessionHas('error');
    $this->assertDatabaseHas('admins', ['id' => $actor->id]);
});

// ===== ADMIN ROLES =====

test('admin can view roles page', function () {
    $admin = makeStaffAdmin();
    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.admin-roles'));
    $response->assertStatus(200)->assertSee('Admin Roles');
});

test('admin can create a role', function () {
    $actor = makeStaffAdmin();

    $response = $this->actingAs($actor, 'admin')->post(route('admin.config.admin-roles.store'), [
        'name'        => 'Support Staff',
        'description' => 'Handles support tickets',
        'is_full_admin' => false,
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseHas('admin_roles', ['name' => 'Support Staff']);
});

test('create role requires name', function () {
    $actor = makeStaffAdmin();
    $response = $this->actingAs($actor, 'admin')->post(route('admin.config.admin-roles.store'), ['name' => '']);
    $response->assertSessionHasErrors('name');
});

test('create role enforces unique name', function () {
    $actor = makeStaffAdmin();
    AdminRole::factory()->create(['name' => 'Existing Role']);

    $response = $this->actingAs($actor, 'admin')->post(route('admin.config.admin-roles.store'), [
        'name' => 'Existing Role',
    ]);

    $response->assertSessionHasErrors('name');
});

test('admin can update a role', function () {
    $actor = makeStaffAdmin();
    $role = AdminRole::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($actor, 'admin')->put(route('admin.config.admin-roles.update', $role), [
        'name' => 'New Name',
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseHas('admin_roles', ['id' => $role->id, 'name' => 'New Name']);
});

test('admin can delete an empty role', function () {
    $actor = makeStaffAdmin();
    $role = AdminRole::factory()->create();

    $response = $this->actingAs($actor, 'admin')->delete(route('admin.config.admin-roles.destroy', $role));

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseMissing('admin_roles', ['id' => $role->id]);
});

test('admin cannot delete a role with assigned admins', function () {
    $actor = makeStaffAdmin();
    $role = $actor->role;

    $response = $this->actingAs($actor, 'admin')->delete(route('admin.config.admin-roles.destroy', $role));

    $response->assertRedirect()->assertSessionHas('error');
    $this->assertDatabaseHas('admin_roles', ['id' => $role->id]);
});

// ===== API CREDENTIALS =====

test('admin can view api credentials page', function () {
    $admin = makeStaffAdmin();
    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.api-credentials'));
    $response->assertStatus(200)->assertSee('API Credentials');
});

test('admin can generate an api credential', function () {
    $actor = makeStaffAdmin();

    $response = $this->actingAs($actor, 'admin')->post(route('admin.config.api-credentials.store'), [
        'description' => 'Test Integration',
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseHas('api_credentials', [
        'admin_id'    => $actor->id,
        'description' => 'Test Integration',
        'active'      => true,
    ]);
});

test('admin can revoke an api credential', function () {
    $actor = makeStaffAdmin();
    $cred = ApiCredential::create([
        'admin_id'   => $actor->id,
        'identifier' => str_repeat('a', 32),
        'secret'     => str_repeat('b', 64),
        'active'     => true,
    ]);

    $response = $this->actingAs($actor, 'admin')->delete(route('admin.config.api-credentials.destroy', $cred));

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseMissing('api_credentials', ['id' => $cred->id]);
});

test('unauthenticated user cannot access staff pages', function () {
    $this->get(route('admin.config.admins'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.config.admin-roles'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.config.api-credentials'))->assertRedirect(route('admin.login'));
});
