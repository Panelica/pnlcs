<?php

use App\Models\Admin;
use App\Models\ApiCredential;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The API accepts an admin username and password (WHMCS compatibility), and
 * two things about that were reported privately in October 2026: there was no
 * limit on how often a password could be tried, because the throttle on /api
 * counts per API credential and this request sends none; and an account with a
 * second factor answered a correct password differently from a wrong one,
 * which told a guesser when they had found it.
 *
 * ValidateLogin has the same shape for a client's password.
 */
it('stops guessing an admin password at the API after five tries', function () {
    Admin::factory()->create(['username' => 'apiboss', 'password' => Hash::make('right-pass')]);

    for ($i = 0; $i < 5; $i++) {
        $this->getJson('/api/v1/getstats?username=apiboss&password=wrong')
            ->assertStatus(401);
    }

    // Locked: even the right password is turned away now.
    $this->getJson('/api/v1/getstats?username=apiboss&password=right-pass')
        ->assertStatus(429);
});

it('answers a second-factor account exactly as it answers a wrong password', function () {
    Admin::factory()->create([
        'username' => 'twofa',
        'password' => Hash::make('right-pass'),
        'second_factor_type' => 'totp',
        'second_factor_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    $correct = $this->getJson('/api/v1/getstats?username=twofa&password=right-pass');
    $wrong = $this->getJson('/api/v1/getstats?username=twofa&password=nope');

    $correct->assertStatus(401);
    $wrong->assertStatus(401);
    expect($correct->json('message'))->toBe($wrong->json('message'));
    expect($correct->json('message'))->not->toContain('two-factor');
});

it('still lets an admin without a second factor in with username and password', function () {
    Admin::factory()->create(['username' => 'plain', 'password' => Hash::make('right-pass')]);

    $this->getJson('/api/v1/getstats?username=plain&password=right-pass')
        ->assertOk();
});

it('does not lock a second username guessed from the same address', function () {
    Admin::factory()->create(['username' => 'first', 'password' => Hash::make('pw-one')]);
    Admin::factory()->create(['username' => 'second', 'password' => Hash::make('pw-two')]);

    for ($i = 0; $i < 5; $i++) {
        $this->getJson('/api/v1/getstats?username=first&password=wrong');
    }

    $this->getJson('/api/v1/getstats?username=second&password=pw-two')
        ->assertOk();
});

it('stops guessing a client password through ValidateLogin', function () {
    $admin = Admin::factory()->create();
    ApiCredential::create([
        'admin_id' => $admin->id,
        'identifier' => 'vl_id',
        'secret' => ApiCredential::hashSecret('vl_secret'),
        'active' => true,
    ]);
    User::factory()->create(['email' => 'client@test.com', 'password' => Hash::make('client-pass')]);

    $headers = ['X-API-Key' => 'vl_id', 'X-API-Secret' => 'vl_secret'];

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/validatelogin', ['email' => 'client@test.com', 'password2' => 'wrong'], $headers)
            ->assertStatus(401);
    }

    $this->postJson('/api/v1/validatelogin', ['email' => 'client@test.com', 'password2' => 'client-pass'], $headers)
        ->assertStatus(429);
});
