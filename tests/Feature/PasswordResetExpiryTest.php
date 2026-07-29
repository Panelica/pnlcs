<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Password reset links are meant to last an hour.
 *
 * The check read `now()->diffInMinutes($created) > 60`. That difference comes
 * back negative for a time in the past — minus ninety for a link an hour and a
 * half old — so the comparison was never true and every link stayed valid for
 * good, wherever it ended up: an inbox, a mail archive, a shared machine's
 * browser history.
 */
function resetTokenFor(User $user, int $minutesOld): string
{
    $token = Str::random(64);

    DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => $user->email],
        ['token' => Hash::make($token), 'created_at' => now()->subMinutes($minutesOld)]
    );

    return $token;
}

test('a fresh reset link works', function () {
    $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);
    $token = resetTokenFor($user, 5);

    $this->post(route('client.password.update.reset'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'BrandNew123!',
        'password_confirmation' => 'BrandNew123!',
    ])->assertRedirect(route('client.login'));

    expect(Hash::check('BrandNew123!', $user->fresh()->password))->toBeTrue();
});

test('a reset link older than an hour is refused', function () {
    $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);
    $token = resetTokenFor($user, 90);

    $this->post(route('client.password.update.reset'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Hijacked123!',
        'password_confirmation' => 'Hijacked123!',
    ])->assertSessionHasErrors('email');

    // The old password still works, which is the whole point.
    expect(Hash::check('OldPassword1!', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('Hijacked123!', $user->fresh()->password))->toBeFalse();
});

test('a link found in an old inbox a week later is refused', function () {
    $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);
    $token = resetTokenFor($user, 60 * 24 * 7);

    $this->post(route('client.password.update.reset'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Hijacked123!',
        'password_confirmation' => 'Hijacked123!',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('OldPassword1!', $user->fresh()->password))->toBeTrue();
});

test('a link right on the hour boundary still works', function () {
    $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);
    $token = resetTokenFor($user, 59);

    $this->post(route('client.password.update.reset'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'JustInTime1!',
        'password_confirmation' => 'JustInTime1!',
    ])->assertRedirect(route('client.login'));

    expect(Hash::check('JustInTime1!', $user->fresh()->password))->toBeTrue();
});

test('a used link cannot be used twice', function () {
    $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);
    $token = resetTokenFor($user, 1);

    $this->post(route('client.password.update.reset'), [
        'token' => $token, 'email' => $user->email,
        'password' => 'FirstUse123!', 'password_confirmation' => 'FirstUse123!',
    ])->assertRedirect(route('client.login'));

    $this->post(route('client.password.update.reset'), [
        'token' => $token, 'email' => $user->email,
        'password' => 'SecondUse123!', 'password_confirmation' => 'SecondUse123!',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('FirstUse123!', $user->fresh()->password))->toBeTrue();
});
