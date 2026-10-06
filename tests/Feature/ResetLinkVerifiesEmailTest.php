<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A reset link is only ever sent to the account's address, so setting a
 * password through it proves the address, as the verification and invitation
 * links do. It did not: a customer whose account staff opened set a password
 * from the reset email and was then asked to verify the address they had just
 * used.
 */
function verifyingResetToken(User $user): string
{
    $token = Str::random(64);
    DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => $user->email],
        ['token' => Hash::make($token), 'created_at' => now()]
    );

    return $token;
}

test('setting a password from the reset link marks the address verified', function () {
    $user = User::factory()->create(['email_verified_at' => null, 'password' => bcrypt('OldPassword1!')]);

    $this->post(route('client.password.update.reset'), [
        'token' => verifyingResetToken($user),
        'email' => $user->email,
        'password' => 'BrandNew123!',
        'password_confirmation' => 'BrandNew123!',
    ])->assertRedirect(route('client.login'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('an address verified earlier keeps its date', function () {
    $when = now()->subYear()->startOfSecond();
    $user = User::factory()->create(['email_verified_at' => $when, 'password' => bcrypt('OldPassword1!')]);

    $this->post(route('client.password.update.reset'), [
        'token' => verifyingResetToken($user),
        'email' => $user->email,
        'password' => 'BrandNew123!',
        'password_confirmation' => 'BrandNew123!',
    ])->assertRedirect(route('client.login'));

    expect($user->fresh()->email_verified_at->equalTo($when))->toBeTrue();
});

test('a wrong token verifies nothing', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    verifyingResetToken($user);

    $this->post(route('client.password.update.reset'), [
        'token' => 'not-the-token',
        'email' => $user->email,
        'password' => 'BrandNew123!',
        'password_confirmation' => 'BrandNew123!',
    ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
