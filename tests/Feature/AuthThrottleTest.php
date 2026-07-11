<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Login throttling: after 5 failed attempts for the same email/username + IP,
 * further attempts are locked out (even with the correct password) until the
 * window expires. Only failed attempts count; a success clears the counter.
 */

it('locks out client login after 5 failures, even with the correct password', function () {
    User::factory()->create(['email' => 'victim@test.com', 'password' => Hash::make('right-pass')]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('client.login.submit'), ['email' => 'victim@test.com', 'password' => 'wrong']);
    }

    // Now locked — the correct password is rejected too.
    $this->post(route('client.login.submit'), ['email' => 'victim@test.com', 'password' => 'right-pass'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('lets a client log in within 5 attempts and clears the counter on success', function () {
    User::factory()->create(['email' => 'ok@test.com', 'password' => Hash::make('right-pass')]);

    $this->post(route('client.login.submit'), ['email' => 'ok@test.com', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');
    $this->post(route('client.login.submit'), ['email' => 'ok@test.com', 'password' => 'right-pass']);

    $this->assertAuthenticated();
});

it('does not throttle a different email from the same IP', function () {
    User::factory()->create(['email' => 'a@test.com', 'password' => Hash::make('pw')]);
    User::factory()->create(['email' => 'b@test.com', 'password' => Hash::make('pw-b')]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('client.login.submit'), ['email' => 'a@test.com', 'password' => 'wrong']);
    }
    // b is a different throttle key → still allowed
    $this->post(route('client.login.submit'), ['email' => 'b@test.com', 'password' => 'pw-b']);
    $this->assertAuthenticated();
});

it('locks out admin login after 5 failures', function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    Admin::factory()->create(['username' => 'adminuser', 'password' => Hash::make('admin-pass'), 'role_id' => $role->id]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('admin.login.submit'), ['username' => 'adminuser', 'password' => 'wrong']);
    }

    $this->post(route('admin.login.submit'), ['username' => 'adminuser', 'password' => 'admin-pass'])
        ->assertSessionHasErrors('username');
    $this->assertGuest('admin');
});
