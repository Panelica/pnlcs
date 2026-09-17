<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A vaulting session this installation opened, and the client it was opened for.
 *
 * Written before the session id is handed to a browser, and read when the
 * browser brings one back. The reasoning for keeping the binding here rather
 * than reading it back out of the gateway is in the migration that creates the
 * table.
 */
class GatewayVaultSession extends Model
{
    protected $fillable = ['gateway', 'client_id', 'session_id'];

    /**
     * Write down that this client opened this session.
     *
     * Idempotent on purpose: a module that retries its own create would
     * otherwise fail on the unique key over a session it already owns. A
     * session id that already belongs to somebody else is NOT overwritten —
     * that is the whole point of the row — and is reported as false so the
     * caller can refuse rather than carry on.
     */
    public static function remember(string $gateway, int $clientId, string $sessionId): bool
    {
        try {
            static::create([
                'gateway' => $gateway,
                'client_id' => $clientId,
                'session_id' => $sessionId,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return static::wasOpenedBy($gateway, $clientId, $sessionId);
        }
    }

    /**
     * Did this client open this session?
     *
     * The answer for a session nobody opened is no. A session id this
     * installation has no record of cannot be proved to belong to the client
     * presenting it, and an unprovable claim about whose payment method is
     * about to be stored is refused.
     */
    public static function wasOpenedBy(string $gateway, int $clientId, string $sessionId): bool
    {
        return static::query()
            ->where('gateway', $gateway)
            ->where('session_id', $sessionId)
            ->where('client_id', $clientId)
            ->exists();
    }
}
