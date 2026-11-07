<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The registry's creation date for a domain, read from RDAP (RFC 9083).
 *
 * RDAP is the modern replacement for port-43 WHOIS and is served over HTTPS as
 * JSON. A domain object carries an "events" array; the entry whose eventAction
 * is "registration" holds the original registration date. That is the date the
 * domain was first created at the registry — unaffected by later transfers —
 * which is exactly what the panel wants when it does not already know it.
 *
 * The lookup is deliberately lenient: a TLD without RDAP, a redacted answer or
 * a network blip all come back null ("unknown"), never an exception, so one
 * domain can never stop the daily sync.
 */
class RdapRegistrationDate
{
    public function forDomain(string $domain): ?string
    {
        $base = rtrim((string) config('services.rdap.base', 'https://rdap.org'), '/');
        $name = strtolower(trim($domain));

        if ($name === '') {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(4)
                ->withHeaders([
                    'Accept' => 'application/rdap+json',
                    'User-Agent' => 'PNLCS',
                ])
                ->get($base.'/domain/'.$name);

            if (! $response->successful()) {
                return null;
            }

            foreach ((array) $response->json('events', []) as $event) {
                if (($event['eventAction'] ?? null) !== 'registration') {
                    continue;
                }

                $date = $event['eventDate'] ?? null;
                if (! $date) {
                    continue;
                }

                return Carbon::parse($date)->toDateString();
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning("RDAP registration date lookup failed for {$name}: {$e->getMessage()}");

            return null;
        }
    }
}
