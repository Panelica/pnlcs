<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/**
 * Whether an account agreed to marketing email, and the proof: when, where
 * (signup, account, admin, unsubscribe) and from which address.
 *
 * No row means no consent. Service email (invoices, renewals, tickets) never
 * depends on this; only messages the operator marks as marketing do.
 */
class MarketingConsent extends Model
{
    protected $fillable = ['client_id', 'email_opt_in', 'source', 'ip_address', 'consented_at', 'withdrawn_at'];

    protected function casts(): array
    {
        return ['email_opt_in' => 'boolean', 'consented_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public static function optedIn(Client $client): bool
    {
        return (bool) static::where('client_id', $client->id)->value('email_opt_in');
    }

    /** Records a change; the same answer again changes nothing, so the dates keep their meaning. */
    public static function record(Client $client, bool $optIn, string $source, ?string $ip = null): self
    {
        $consent = static::firstOrNew(['client_id' => $client->id]);

        if ($consent->exists && $consent->email_opt_in === $optIn) {
            return $consent;
        }

        $consent->fill([
            'email_opt_in' => $optIn,
            'source' => $source,
            'ip_address' => $ip,
        ]);
        $optIn ? $consent->consented_at = now() : $consent->withdrawn_at = now();
        $consent->save();

        ActivityLog::log('Marketing email consent '.($optIn ? 'given' : 'withdrawn').' ('.$source.')', null, $client->id);

        return $consent;
    }

    /** A link that withdraws consent without signing in, for the foot of a marketing email. */
    public static function unsubscribeUrl(Client $client): string
    {
        return URL::signedRoute('client.unsubscribe', ['client' => $client->id]);
    }
}
