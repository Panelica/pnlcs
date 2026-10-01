<?php

namespace App\Services;

use App\Mail\ConfirmationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * A second check before the client area hands out something that moves an
 * account's assets: a domain's transfer (EPP) code, or its registrar lock.
 *
 * A session alone was enough. Whoever had a signed-in browser - a shared
 * computer, a stolen session cookie - could read the transfer code and take
 * the domain to another registrar. A code sent to the login's address proves
 * the person also holds the mailbox. Logins opened through Google have no
 * password, so the check is the mailbox for everyone.
 *
 * Once confirmed, the session stays confirmed for WINDOW_MINUTES.
 */
class SensitiveActionConfirmation
{
    public const WINDOW_MINUTES = 15;

    public const CODE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function confirmed(User $user): bool
    {
        $at = session('sensitive_confirmed');

        return is_array($at)
            && (int) ($at['user'] ?? 0) === (int) $user->id
            && (int) ($at['at'] ?? 0) >= now()->subMinutes(self::WINDOW_MINUTES)->getTimestamp();
    }

    public function sendCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        session(['sensitive_code' => [
            'user' => $user->id,
            'hash' => hash('sha256', $code),
            'expires' => now()->addMinutes(self::CODE_MINUTES)->getTimestamp(),
            'attempts' => 0,
        ]]);

        Mail::to($user->email)->send(new ConfirmationCodeMail($code, (string) $user->email, self::CODE_MINUTES));
    }

    /** True when the code matches; a wrong one counts towards MAX_ATTEMPTS. */
    public function check(User $user, string $code): bool
    {
        $pending = session('sensitive_code');
        if (! is_array($pending) || (int) $pending['user'] !== (int) $user->id
            || $pending['expires'] < now()->getTimestamp() || $pending['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! hash_equals($pending['hash'], hash('sha256', trim($code)))) {
            $pending['attempts']++;
            session(['sensitive_code' => $pending]);

            return false;
        }

        session()->forget('sensitive_code');
        session(['sensitive_confirmed' => ['user' => $user->id, 'at' => now()->getTimestamp()]]);

        return true;
    }

    public function hasPendingCode(User $user): bool
    {
        $pending = session('sensitive_code');

        return is_array($pending) && (int) $pending['user'] === (int) $user->id
            && $pending['expires'] >= now()->getTimestamp() && $pending['attempts'] < self::MAX_ATTEMPTS;
    }
}
