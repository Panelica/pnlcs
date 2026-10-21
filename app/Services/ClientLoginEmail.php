<?php

namespace App\Services;

use App\Http\Controllers\Client\EmailVerificationController;
use App\Mail\LoginEmailChangedMail;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Staff changing an account's email address, and the customer who signs in
 * with it.
 *
 * The client record (billing) and the login are separate rows. Staff changing
 * a client's address changed the client record only: the customer kept
 * signing in with the old address and saw it on their profile, and there was
 * no screen where staff could change the login (#210). Now a login of the
 * account that signed in with the old address moves with it. A login on
 * another address is a different person's (an invited user, an imported
 * WHMCS user) and is left alone.
 *
 * A moved login is treated as the customer's own change is: the new address
 * proves itself before it is trusted, and the address losing the account is
 * told, here with "changed by staff" instead of "confirmed with the password".
 */
final class ClientLoginEmail
{
    /** The login of this account that signs in with $address, if any. */
    public static function loginOn(Client $client, string $address): ?User
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        return $client->users()->get()
            ->first(fn (User $user) => strcasecmp((string) $user->email, $address) === 0);
    }

    /**
     * The login that has to move when the account's address goes from $old to
     * $new, or null when none does (no change, or nobody signs in with $old).
     */
    public static function loginToMove(Client $client, string $old, string $new): ?User
    {
        if (strcasecmp(trim($old), trim($new)) === 0) {
            return null;
        }

        return self::loginOn($client, $old);
    }

    /** Somebody else already signs in with $address: the login cannot move there. */
    public static function takenByAnother(User $login, string $address): bool
    {
        return User::where('email', trim($address))->whereKeyNot($login->id)->exists();
    }

    /** Move the login to $new: verification starts again, the old address is told. */
    public static function move(User $login, string $new): void
    {
        $previous = (string) $login->email;

        $login->forceFill(['email' => trim($new), 'email_verified_at' => null])->save();
        EmailVerificationController::send($login->fresh());

        try {
            Mail::to($previous)->send(new LoginEmailChangedMail($previous, trim($new), byStaff: true));
        } catch (\Throwable $e) {
            Log::warning('Could not tell the previous address its account moved', [
                'user_id' => $login->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
