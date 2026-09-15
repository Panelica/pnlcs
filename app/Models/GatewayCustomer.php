<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The customer record a client has at a gateway.
 *
 * Stored cards hang off it, so there must be exactly one per client per
 * gateway and it must be written down the moment the gateway hands it over —
 * not later, when the first card is confirmed, because the gap between those
 * two moments is where duplicate customers are made.
 */
class GatewayCustomer extends Model
{
    protected $fillable = ['gateway', 'client_id', 'customer_id'];

    /**
     * The id this client already has at this gateway, if any.
     */
    public static function idFor(string $gateway, int $clientId): ?string
    {
        $stored = static::query()
            ->where('gateway', $gateway)
            ->where('client_id', $clientId)
            ->value('customer_id');

        return $stored === null ? null : (string) $stored;
    }

    /**
     * Write down the customer this client is to use, and say which one won.
     *
     * Two card forms open at once both reach the gateway and both come back
     * with an id. Only one of them can be the client's customer from here on,
     * and the unique key picks it: the loser's insert fails and it is told the
     * winner's id, which is the id its own stored card will then hang off.
     * The spare customer it created at the gateway is left behind unused, which
     * costs nothing and is far cheaper than two live customers each holding
     * half of somebody's cards.
     */
    public static function remember(string $gateway, int $clientId, string $customerId): string
    {
        try {
            static::create([
                'gateway' => $gateway,
                'client_id' => $clientId,
                'customer_id' => $customerId,
            ]);

            return $customerId;
        } catch (UniqueConstraintViolationException) {
            return static::idFor($gateway, $clientId) ?? $customerId;
        }
    }
}
