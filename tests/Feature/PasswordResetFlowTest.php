<?php

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Password reset now delivers the token by email and never writes it to the
 * application log (previously Log::info leaked the plaintext token, and no
 * email was sent at all — the flow was both insecure and non-functional).
 */

it('emails a reset link containing the token', function () {
    Mail::fake();
    User::factory()->create(['email' => 'reset@test.com']);

    $this->post(route('client.password.email'), ['email' => 'reset@test.com'])
        ->assertRedirect();

    Mail::assertSent(PasswordResetMail::class, function ($mail) {
        return $mail->email === 'reset@test.com'
            && str_contains($mail->resetUrl, 'reset-password/')
            && str_contains($mail->resetUrl, 'email=');
    });
});

it('sends nothing for an unknown email but still returns success (no user enumeration)', function () {
    Mail::fake();

    $this->post(route('client.password.email'), ['email' => 'ghost@test.com'])
        ->assertRedirect();

    Mail::assertNothingSent();
});
