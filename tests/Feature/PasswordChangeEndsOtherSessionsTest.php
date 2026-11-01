<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
 * A password change ends the login's other open sessions.
 *
 * Changing or resetting the password already stopped "remember me" cookies,
 * but a session simply open on another computer carried on. Each session now
 * remembers the password it was opened with; when that no longer matches, the
 * session is signed out. The session that made the change stays in.
 */
function sessionsCustomer(?string $password = 'OldPassword1'): User
{
    $user = User::factory()->create(['password' => $password === null ? null : Hash::make($password)]);
    $user->clients()->attach(Client::factory()->create()->id, ['owner' => true]);

    return $user;
}

test('a customer session elsewhere is signed out when the password changes', function () {
    $user = sessionsCustomer();
    $this->actingAs($user)->get(route('client.home'))->assertOk();

    // Changed from another device (or reset from the mail link).
    $user->forceFill(['password' => Hash::make('BrandNewPass1')])->save();

    $this->get(route('client.home'))->assertRedirect(route('client.login'));
    expect(auth('web')->check())->toBeFalse();
});

test('a staff session elsewhere is signed out when the password changes', function () {
    $admin = Admin::factory()->create([
        'password' => Hash::make('OldPassword1'),
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk();

    $admin->forceFill(['password' => Hash::make('BrandNewPass1')])->save();

    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    expect(auth('admin')->check())->toBeFalse();
});

test('the session that changes the password stays signed in', function () {
    $user = sessionsCustomer();
    $this->actingAs($user)->get(route('client.home'))->assertOk();

    $this->put(route('client.account.password.update'), [
        'current_password' => 'OldPassword1',
        'password' => 'BrandNewPass1',
        'password_confirmation' => 'BrandNewPass1',
    ])->assertRedirect();

    $this->get(route('client.home'))->assertOk();
});

test('a login without a password is left alone', function () {
    $user = sessionsCustomer(null);

    $this->actingAs($user)->get(route('client.home'))->assertOk();
    $this->get(route('client.home'))->assertOk();
});
