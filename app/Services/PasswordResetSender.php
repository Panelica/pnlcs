<?php

namespace App\Services;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Send a customer login a link to choose a new password.
 *
 * The forgot-password form and the API's resetpassword both do this, so it is
 * done here once: the token is stored only as a hash, and a delivery failure is
 * logged without the token in it.
 */
class PasswordResetSender
{
    /** Returns false when no login has that address; nothing is sent then. */
    public function send(string $email): bool
    {
        if (! User::where('email', $email)->exists()) {
            return false;
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );
        $resetUrl = route('client.password.reset', ['token' => $token]).'?email='.urlencode($email);

        try {
            Mail::to($email)->send(new PasswordResetMail($resetUrl, $email));
        } catch (\Throwable $e) {
            // Never log the token; only the delivery failure.
            Log::error('Password reset email failed for '.$email.': '.$e->getMessage());
        }

        return true;
    }
}
