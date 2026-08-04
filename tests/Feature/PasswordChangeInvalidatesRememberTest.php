<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * What a password change is for.
 *
 * Signing in with "remember me" leaves a cookie that carries the account id
 * and the remember token, and that cookie signs the holder in on its own for
 * as long as the token in the database stays the same. Neither changing the
 * password nor resetting it touched that token - so somebody who had taken the
 * cookie stayed signed in, and the one thing a worried customer does about it
 * did nothing at all. Resetting a password is what somebody does precisely
 * because they think another person is in their account.
 */
it('stops a remembered session when the password is changed', function () {
    $user = User::factory()->create([
        'email' => 'remembered@test.local',
        'password' => Hash::make('OldPassword1'),
        'remember_token' => 'the-stolen-token',
    ]);

    $this->actingAs($user)->put(route('client.account.password.update'), [
        'current_password' => 'OldPassword1',
        'password' => 'BrandNewPass1',
        'password_confirmation' => 'BrandNewPass1',
    ])->assertRedirect();

    expect($user->fresh()->remember_token)->not->toBe('the-stolen-token');
});

it('stops a remembered session when the password is reset', function () {
    $user = User::factory()->create([
        'email' => 'forgetful@test.local',
        'password' => Hash::make('OldPassword1'),
        'remember_token' => 'the-stolen-token',
    ]);

    $token = 'reset-token-'.uniqid();

    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => Hash::make($token),
        'created_at' => now(),
    ]);

    $this->post(route('client.password.update.reset'), [
        'email' => $user->email,
        'token' => $token,
        'password' => 'BrandNewPass1',
        'password_confirmation' => 'BrandNewPass1',
    ])->assertRedirect();

    expect(Hash::check('BrandNewPass1', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe('the-stolen-token');
});
