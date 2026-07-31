<?php

use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Support\Facades\Hash;

/**
 * The install wizard, once there is something to protect.
 *
 * Step three created the administrator with updateOrCreate() keyed on the
 * username, so a request that reached it could set the password of an
 * administrator who already existed. The only thing standing in front of it
 * was a middleware that honoured a session flag before it checked whether the
 * system was already installed — and the flag is handed to whoever visits
 * /install first on a fresh deployment, which on a public server is not
 * necessarily the owner.
 *
 * The lock file that closes the wizard is written with the error operator
 * silenced, so an unwritable storage directory left that session valid for as
 * long as the container lived.
 */
function wizardSession(): array
{
    return ['install.in_progress' => true];
}

/**
 * A storage directory of its own, so the lock file belonging to this
 * installation is not what the test is measuring — and so no test can remove
 * the lock that protects the running site.
 */
beforeEach(function () {
    $path = sys_get_temp_dir().'/pnlcs-install-test-'.uniqid();
    mkdir($path, 0777, true);
    app()->useStoragePath($path);
});

function existingAdmin(string $username = 'owner'): Admin
{
    return Admin::factory()->create([
        'username' => $username,
        'password' => Hash::make('the-owners-password'),
        'role_id' => AdminRole::factory()->create(['name' => 'Super', 'permissions' => ['*']])->id,
    ]);
}

test('the wizard will not overwrite an administrator that already exists', function () {
    $admin = existingAdmin();

    $this->withSession(wizardSession())->post('/install/admin', [
        'username' => 'owner',
        'email' => 'attacker@example.test',
        'first_name' => 'Not',
        'last_name' => 'Owner',
        'password' => 'attacker-password',
        'password_confirmation' => 'attacker-password',
    ])->assertNotFound();

    expect(Hash::check('the-owners-password', $admin->fresh()->password))->toBeTrue();
});

test('the wizard will not add a second administrator to a live system', function () {
    existingAdmin();

    $this->withSession(wizardSession())->post('/install/admin', [
        'username' => 'second',
        'email' => 'second@example.test',
        'first_name' => 'Second',
        'last_name' => 'Admin',
        'password' => 'another-password',
        'password_confirmation' => 'another-password',
    ])->assertNotFound();

    expect(Admin::count())->toBe(1);
});

test('a visitor with no wizard session is refused once an administrator exists', function () {
    existingAdmin();

    $this->get('/install')->assertNotFound();
    $this->get('/install/admin')->assertNotFound();
});

test('the wizard still creates the first administrator', function () {
    expect(Admin::count())->toBe(0);

    $this->withSession(wizardSession())->post('/install/admin', [
        'username' => 'firstowner',
        'email' => 'owner@example.test',
        'first_name' => 'First',
        'last_name' => 'Owner',
        'password' => 'a-good-password',
        'password_confirmation' => 'a-good-password',
    ])->assertRedirect('/install/app');

    expect(Admin::where('username', 'firstowner')->exists())->toBeTrue()
        // The seeder's own account, password and all, used to be left behind
        // whenever the operator chose a username other than "admin".
        ->and(Admin::count())->toBe(1)
        ->and(Hash::check('admin123', Admin::firstOrFail()->password))->toBeFalse();
});

test('the wizard may correct the administrator it just created', function () {
    $this->withSession(wizardSession())->post('/install/admin', [
        'username' => 'typo',
        'email' => 'owner@example.test',
        'first_name' => 'First',
        'last_name' => 'Owner',
        'password' => 'a-good-password',
        'password_confirmation' => 'a-good-password',
    ])->assertRedirect('/install/app');

    $created = Admin::firstOrFail();

    // Going back a step and submitting again is a normal thing to do.
    $this->withSession(array_merge(wizardSession(), ['install.admin_id' => $created->id]))
        ->post('/install/admin', [
            'username' => 'typo',
            'email' => 'corrected@example.test',
            'first_name' => 'First',
            'last_name' => 'Owner',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
        ])->assertRedirect('/install/app');

    expect(Admin::count())->toBe(1)
        ->and(Admin::firstOrFail()->email)->toBe('corrected@example.test');
});
