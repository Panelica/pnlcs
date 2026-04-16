<?php

namespace App\Services;

use App\Models\BannedEmail;
use App\Models\BannedIp;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class FraudDetectionService
{
    /**
     * Calculate fraud risk score for an order (0-100).
     * This is advisory only — no automatic blocking.
     *
     * @return array{score: int, reasons: string[], risk_level: string}
     */
    public function evaluate(Order $order): array
    {
        $score = 0;
        $reasons = [];

        $order->loadMissing('client');
        $client = $order->client;

        if (!$client) {
            return ['score' => 0, 'reasons' => ['No client associated'], 'risk_level' => 'low'];
        }

        // Rule 1: Client has previous fraud orders (+50)
        $fraudCount = Order::where('client_id', $client->id)
            ->where('status', 'fraud')
            ->where('id', '!=', $order->id)
            ->count();

        if ($fraudCount > 0) {
            $score += 50;
            $reasons[] = "Client has {$fraudCount} previous fraud order(s)";
        }

        // Rule 2: Same IP placed 3+ orders in 24h (+30)
        if ($order->ip_address && $order->ip_address !== '0.0.0.0') {
            $recentIpOrders = Order::where('ip_address', $order->ip_address)
                ->where('created_at', '>=', now()->subDay())
                ->where('id', '!=', $order->id)
                ->count();

            if ($recentIpOrders >= 3) {
                $score += 30;
                $reasons[] = "{$recentIpOrders} orders from same IP in 24h";
            }
        }

        // Rule 3: New client (<24h) + high amount (>$100) (+20)
        if ($client->created_at && $client->created_at->diffInHours(now()) < 24) {
            $amount = (float) ($order->amount ?? 0);
            if ($amount > 100) {
                $score += 20;
                $reasons[] = "New client (< 24h) with high order amount (\${$amount})";
            }
        }

        // Rule 4: Email in banned list (+80)
        if ($client->email) {
            $domain = explode('@', $client->email)[1] ?? '';
            $emailBanned = BannedEmail::where(function ($q) use ($client, $domain) {
                $q->where('email', $client->email);
                if ($domain) {
                    $q->orWhere('email', '%@' . $domain);
                }
            })->exists();

            if ($emailBanned) {
                $score += 80;
                $reasons[] = "Client email matches banned list";
            }
        }

        // Rule 5: IP in banned list (+80)
        if ($order->ip_address) {
            $ipBanned = BannedIp::where('ip', $order->ip_address)->exists();

            if ($ipBanned) {
                $score += 80;
                $reasons[] = "Order IP matches banned list";
            }
        }

        // Cap at 100
        $score = min($score, 100);

        $riskLevel = match (true) {
            $score >= 60 => 'high',
            $score >= 30 => 'medium',
            default => 'low',
        };

        if ($score > 0) {
            Log::info("Fraud check for order #{$order->id}: score={$score}, risk={$riskLevel}", [
                'reasons' => $reasons,
            ]);
        }

        return [
            'score' => $score,
            'reasons' => $reasons,
            'risk_level' => $riskLevel,
        ];
    }
}
