<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * "You have used this password before."
 *
 * How far back to look lives in one place, because there are three separate
 * ways to change a password - the profile page, the password page and the
 * reset link in an email - and all three have to apply the same rule or the
 * rule is only a suggestion.
 */
class PasswordHistory
{
    /**
     * How many passwords back to check, counting the one in use.
     *
     * 3 means: the current password and the two before it are refused. If the
     * current one were not counted, "the last 3" would really be four.
     */
    public const KEEP = 3;

    /** Rows kept in the history table; the current hash lives on the user. */
    private const HISTORY = self::KEEP - 1;

    /**
     * Is this one of the last KEEP passwords?
     *
     * The password in use counts too, so even an old account with no history
     * rows cannot simply set the same password again.
     */
    public static function isReused(User $user, string $plain): bool
    {
        if (Hash::check($plain, (string) $user->password)) {
            return true;
        }

        $previous = DB::table('password_histories')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY)
            ->pluck('password');

        foreach ($previous as $hash) {
            if (Hash::check($plain, (string) $hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write the password being replaced into the history.
     *
     * The old hash, not the new one: the new one sits on the user row and
     * isReused() checks it there.
     */
    public static function remember(User $user, ?string $previousHash): void
    {
        $previousHash = trim((string) $previousHash);

        if ($previousHash === '') {
            return;
        }

        DB::table('password_histories')->insert([
            'user_id' => $user->id,
            'password' => $previousHash,
            'created_at' => now(),
        ]);

        // Drop whatever fell outside the window: a hash we no longer ask about
        // is a hash we have no business holding.
        $keep = DB::table('password_histories')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY)
            ->pluck('id');

        DB::table('password_histories')
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }
}
