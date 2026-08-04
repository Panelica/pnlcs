<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Guessing the second factor.
 *
 * The sign-in form allows ten attempts a minute. The code that follows it -
 * six digits, or a backup code - allowed as many as the server would answer,
 * on both the customer and the admin side. Somebody who already had the
 * password could sit on that form and work through the numbers, which is the
 * one thing the second factor is there to prevent.
 */
it('stops someone working through the codes on the customer side', function () {
    $user = User::factory()->create([
        'password' => Hash::make('Password123'),
        'second_factor_type' => 'totp',
        'second_factor_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    $this->post(route('client.login.submit'), [
        'email' => $user->email,
        'password' => 'Password123',
    ]);

    $tooMany = false;

    foreach (range(1, 15) as $attempt) {
        $response = $this->post(route('client.2fa.verify.submit'), [
            'code' => str_pad((string) $attempt, 6, '0', STR_PAD_LEFT),
        ]);

        if ($response->status() === 429) {
            $tooMany = true;
            break;
        }
    }

    expect($tooMany)->toBeTrue();
});

it('stops someone working through the codes on the admin side', function () {
    $admin = Admin::factory()->create([
        'password' => Hash::make('Password123'),
        'second_factor_type' => 'totp',
        'second_factor_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    $this->post(route('admin.login.submit'), [
        'username' => $admin->username,
        'password' => 'Password123',
    ]);

    $tooMany = false;

    foreach (range(1, 15) as $attempt) {
        $response = $this->post(route('admin.2fa.verify.submit'), [
            'code' => str_pad((string) $attempt, 6, '0', STR_PAD_LEFT),
        ]);

        if ($response->status() === 429) {
            $tooMany = true;
            break;
        }
    }

    expect($tooMany)->toBeTrue();
});
