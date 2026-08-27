<?php

namespace App\Services\Fraud;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FraudLabs Pro Screen Order, written against the published API
 * (fraudlabspro.com/developer/api/screen-order): POST
 * https://api.fraudlabspro.com/v2/order/screen, the key passed as a body
 * parameter, ip required, and a response carrying fraudlabspro_score (1-100),
 * fraudlabspro_status (APPROVE / REVIEW / REJECT) and fraudlabspro_id.
 *
 * Screening is advisory: every failure path returns null and the order flow
 * carries on with the local rules alone.
 */
class FraudLabsProClient
{
    public const ENDPOINT = 'https://api.fraudlabspro.com/v2/order/screen';

    public function enabled(): bool
    {
        return (bool) Setting::get('FraudLabsEnabled', 0)
            && trim((string) Setting::get('FraudLabsApiKey', '')) !== '';
    }

    /** @return array{score: int, status: ?string, id: ?string}|null */
    public function score(Order $order): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $ip = (string) $order->ip_address;
        if ($ip === '' || $ip === '0.0.0.0' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $body = [
            'key' => trim((string) Setting::get('FraudLabsApiKey')),
            'format' => 'json',
            'ip' => $ip,
        ];
        if ($order->client?->email) {
            $body['email'] = $order->client->email;
        }
        if ((float) $order->amount > 0) {
            $body['amount'] = (float) $order->amount;
            $body['currency'] = strtoupper((string) ($order->currency ?? 'USD'));
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::ENDPOINT, $body);

            if (! $response->successful()) {
                Log::warning('FraudLabs screening failed for order #'.$order->id, [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $score = $response->json('fraudlabspro_score');
            if (! is_numeric($score)) {
                return null;
            }

            return [
                'score' => (int) $score,
                'status' => $response->json('fraudlabspro_status'),
                'id' => $response->json('fraudlabspro_id'),
            ];
        } catch (\Throwable $e) {
            Log::warning('FraudLabs screening unreachable for order #'.$order->id.': '.$e->getMessage());

            return null;
        }
    }
}
