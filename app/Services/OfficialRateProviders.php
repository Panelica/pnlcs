<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Official rate sources the operator adds, next to the built-in TCMB one.
 *
 * Each entry says where the rate is published, which fields of the JSON answer
 * hold the rate, the publisher's own date and (optionally) a stale flag or a
 * bulletin reference, which way round the rate is quoted, and whether a stale
 * answer is used or refused. Nothing here ships a provider: the list is empty
 * until the operator fills it (OfficialRateProviders), and an entry only
 * applies once it is chosen as the official rate source.
 *
 * The answer is never normalised silently: the quote direction is part of the
 * entry, so an auditor can read off the entry how the stored number was made.
 */
class OfficialRateProviders
{
    public const QUOTES = ['base', 'currency'];

    public const KINDS = ['reference', 'ForexSelling', 'ForexBuying'];

    public const ON_STALE = ['refuse', 'use'];

    private const PATH = '/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)*$/';

    /**
     * The stored entries. A stored list that no longer validates yields none:
     * a half-understood provider must not write a rate onto an invoice.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $raw = trim((string) Setting::get('OfficialRateProviders', ''));

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        [$providers, $errors] = self::normalise($decoded);

        return $errors === [] ? $providers : [];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $provider) {
            if ($provider['key'] === $key) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Check a decoded list and fill in defaults.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<string>} the entries and what is wrong with them
     */
    public static function normalise(mixed $decoded): array
    {
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return [[], ['The list must be a JSON array of provider objects.']];
        }

        $errors = [];
        $providers = [];
        $keys = [];

        foreach ($decoded as $i => $entry) {
            $at = 'Provider '.($i + 1);

            if (! is_array($entry)) {
                $errors[] = "$at: must be an object.";

                continue;
            }

            $key = (string) ($entry['key'] ?? '');
            if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $key) || $key === 'tcmb') {
                $errors[] = "$at: key must be 1-32 lowercase letters, digits, - or _, and not \"tcmb\".";
            } elseif (in_array($key, $keys, true)) {
                $errors[] = "$at: key \"$key\" is used twice.";
            }
            $keys[] = $key;

            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 64) {
                $errors[] = "$at: name (the source printed on invoices) is required, up to 64 characters.";
            }

            $url = (string) ($entry['url'] ?? '');
            if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                $errors[] = "$at: url must be an https URL.";
            }

            $base = strtoupper((string) ($entry['base'] ?? ''));
            $currency = strtoupper((string) ($entry['currency'] ?? ''));
            if (! preg_match('/^[A-Z]{3}$/', $base) || ! preg_match('/^[A-Z]{3}$/', $currency) || $base === $currency) {
                $errors[] = "$at: base and currency must be two different three-letter codes.";
            }

            foreach (['rate_path', 'date_path'] as $field) {
                if (! preg_match(self::PATH, (string) ($entry[$field] ?? ''))) {
                    $errors[] = "$at: $field is required, as a dot path into the JSON answer (e.g. \"rate\" or \"data.0.rate\").";
                }
            }
            foreach (['stale_path', 'ref_path'] as $field) {
                if (isset($entry[$field]) && $entry[$field] !== '' && ! preg_match(self::PATH, (string) $entry[$field])) {
                    $errors[] = "$at: $field must be a dot path into the JSON answer.";
                }
            }

            $quote = (string) ($entry['quote'] ?? '');
            if (! in_array($quote, self::QUOTES, true)) {
                $errors[] = "$at: quote must be \"base\" (1 base = rate currency) or \"currency\" (1 currency = rate base).";
            }

            $kind = (string) ($entry['kind'] ?? 'reference');
            if (! in_array($kind, self::KINDS, true)) {
                $errors[] = "$at: kind must be one of ".implode(', ', self::KINDS).'.';
            }

            $onStale = (string) ($entry['on_stale'] ?? 'refuse');
            if (! in_array($onStale, self::ON_STALE, true)) {
                $errors[] = "$at: on_stale must be \"refuse\" or \"use\".";
            }

            $maxAge = $entry['max_age_days'] ?? null;
            if ($maxAge !== null && (! is_int($maxAge) || $maxAge < 0 || $maxAge > 60)) {
                $errors[] = "$at: max_age_days must be a whole number from 0 to 60.";
            }

            $providers[] = [
                'key' => $key,
                'name' => $name,
                'url' => $url,
                'base' => $base,
                'currency' => $currency,
                'rate_path' => (string) ($entry['rate_path'] ?? ''),
                'date_path' => (string) ($entry['date_path'] ?? ''),
                'stale_path' => (string) ($entry['stale_path'] ?? ''),
                'ref_path' => (string) ($entry['ref_path'] ?? ''),
                'quote' => $quote,
                'kind' => $kind,
                'on_stale' => $onStale,
                'max_age_days' => $maxAge,
                'auth' => (bool) ($entry['auth'] ?? false),
            ];
        }

        return [$providers, $errors];
    }

    /**
     * One unit of the provider's base in its currency, with the provenance an
     * invoice prints.
     *
     * @param  array<string, mixed>  $provider
     * @return array{rate: float, date: string, bulletin: ?string, kind: string, source: string}|string the rate, or why there is none
     */
    public function rateFor(array $provider): array|string
    {
        try {
            $request = Http::timeout(15)->acceptJson();

            $apiKey = trim((string) Setting::get('OfficialRateApiKey', ''));
            if ($provider['auth'] && $apiKey !== '') {
                $request = $request->withToken($apiKey);
            }

            $response = $request->get($provider['url']);
        } catch (\Throwable $e) {
            Log::warning('Official rate fetch failed: '.$e->getMessage(), ['provider' => $provider['key']]);

            return 'unreachable';
        }

        if (! $response->successful() || ! is_array($json = $response->json())) {
            return 'unreachable';
        }

        $value = data_get($json, $provider['rate_path']);
        $date = trim((string) data_get($json, $provider['date_path'], ''));

        if (! is_numeric($value) || (float) $value <= 0) {
            return 'no rate in the answer';
        }
        if ($date === '') {
            return 'no publication date in the answer';
        }

        $stale = $provider['stale_path'] !== '' && data_get($json, $provider['stale_path']) === true;

        if (! $stale && $provider['max_age_days'] !== null) {
            try {
                $stale = Carbon::parse($date)->startOfDay()->diffInDays(Carbon::now()->startOfDay()) > $provider['max_age_days'];
            } catch (\Throwable) {
                $stale = true;            // a date we cannot read is not a date we can vouch for
            }
        }

        if ($stale && $provider['on_stale'] === 'refuse') {
            return 'stale ('.$date.')';
        }

        $rate = $provider['quote'] === 'base' ? (float) $value : 1 / (float) $value;
        $ref = $provider['ref_path'] !== '' ? trim((string) data_get($json, $provider['ref_path'], '')) : '';

        return [
            'rate' => round($rate, 6),
            'date' => $date,
            'bulletin' => $ref !== '' ? $ref : null,
            'kind' => $provider['kind'],
            'source' => $provider['name'],
        ];
    }
}
