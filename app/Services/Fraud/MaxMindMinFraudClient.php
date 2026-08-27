<?php

namespace App\Services\Fraud;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MaxMind minFraud Score, written against the published API
 * (dev.maxmind.com/minfraud/api-documentation): POST
 * https://minfraud.maxmind.com/minfraud/v2.0/score with HTTP Basic auth
 * (account id / license key), a JSON body whose only required field is
 * device.ip_address, and a response carrying risk_score (0.01-99), id and
 * disposition.action (accept / reject / manual_review / test).
 *
 * Screening is advisory: every failure path returns null and the order flow
 * carries on with the local rules alone.
 */
class MaxMindMinFraudClient
{
    public const ENDPOINT = 'https://minfraud.maxmind.com/minfraud/v2.0/score';

    public function enabled(): bool
    {
        return (bool) Setting::get('MaxMindEnabled', 0)
            && trim((string) Setting::get('MaxMindAccountId', '')) !== ''
            && trim((string) Setting::get('MaxMindLicenseKey', '')) !== '';
    }

    /** @return array{score: int, id: ?string, action: ?string}|null */
    public function score(Order $order): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        // device.ip_address is the one required field; without a usable IP
        // there is no request to make.
        $ip = (string) $order->ip_address;
        if ($ip === '' || $ip === '0.0.0.0' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $client = $order->client;
        $body = ['device' => ['ip_address' => $ip]];
        if ($client?->email) {
            $body['email'] = ['address' => $client->email];
        }
        if ($client) {
            $billing = array_filter([
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                // The spec takes only a two-character ISO 3166-1 code here; a
                // spelled-out country name would fail the whole request.
                'country' => strlen((string) $client->country) === 2 ? strtoupper($client->country) : null,
            ]);
            if ($billing !== []) {
                $body['billing'] = $billing;
            }
        }
        if ((float) $order->amount > 0) {
            $body['order'] = ['amount' => (float) $order->amount, 'currency' => strtoupper((string) ($order->currency ?? 'USD'))];
        }

        try {
            $response = Http::withBasicAuth(
                trim((string) Setting::get('MaxMindAccountId')),
                trim((string) Setting::get('MaxMindLicenseKey'))
            )->timeout(10)->asJson()->post(self::ENDPOINT, $body);

            if (! $response->successful()) {
                // Errors arrive as {code, error} per the spec.
                Log::warning('minFraud screening failed for order #'.$order->id, [
                    'status' => $response->status(),
                    'code' => $response->json('code'),
                    'error' => $response->json('error'),
                ]);

                return null;
            }

            $riskScore = $response->json('risk_score');
            if (! is_numeric($riskScore)) {
                return null;
            }

            return [
                'score' => (int) round((float) $riskScore),
                'id' => $response->json('id'),
                'action' => $response->json('disposition.action'),
            ];
        } catch (\Throwable $e) {
            Log::warning('minFraud screening unreachable for order #'.$order->id.': '.$e->getMessage());

            return null;
        }
    }
}
