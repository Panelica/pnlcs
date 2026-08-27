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

        if (! $client) {
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
                $reasons[] = 'New client (< 24h) with high order amount ('.money_fmt($amount).')';
            }
        }

        // Rule 4: Email in banned list (+80)
        if ($client->email) {
            $emailBanned = BannedEmail::blocks($client->email);

            if ($emailBanned) {
                $score += 80;
                $reasons[] = 'Client email matches banned list';
            }
        }

        // Rule 5: IP in banned list (+80)
        if ($order->ip_address) {
            $ipBanned = BannedIp::where('ip', $order->ip_address)->exists();

            if ($ipBanned) {
                $score += 80;
                $reasons[] = 'Order IP matches banned list';
            }
        }

        // External screening, when the operator has configured it. Both
        // services are advisory the same way the local rules are: an outage or
        // a missing key never blocks an order, it just leaves the local score
        // standing alone. The combined score is the worst signal seen, not a
        // sum - two mild worries must not add up to a rejection.
        $module = 'internal';

        $minFraud = app(Fraud\MaxMindMinFraudClient::class)->score($order);
        if ($minFraud !== null) {
            // minFraud's risk_score shares our 0-100 scale (0.01-99).
            if ($minFraud['score'] > $score) {
                $score = $minFraud['score'];
                $module = 'maxmind';
            }
            $reasons[] = 'MaxMind minFraud risk score '.$minFraud['score']
                .($minFraud['action'] ? ' ('.$minFraud['action'].')' : '');
        }

        $fraudLabs = app(Fraud\FraudLabsProClient::class)->score($order);
        if ($fraudLabs !== null) {
            // The verdict outranks the number: FraudLabs may say REJECT on a
            // pattern its score understates, and a REVIEW should reach the
            // hold threshold rather than slip through at 59.
            $external = match ($fraudLabs['status']) {
                'REJECT' => max($fraudLabs['score'], 90),
                'REVIEW' => max($fraudLabs['score'], 60),
                default => $fraudLabs['score'],
            };
            if ($external > $score) {
                $score = $external;
                $module = 'fraudlabs';
            }
            $reasons[] = 'FraudLabs Pro score '.$fraudLabs['score']
                .($fraudLabs['status'] ? ' ('.$fraudLabs['status'].')' : '');
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
            // Which signal the final score came from - written onto a held
            // order so the admin screen names the right screener.
            'module' => $module,
        ];
    }
}
