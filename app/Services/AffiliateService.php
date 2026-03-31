<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Client;

class AffiliateService
{
    public function activateAffiliate(Client $client): Affiliate
    {
        return Affiliate::firstOrCreate(
            ['client_id' => $client->id],
            ['date' => now(), 'payment_type' => 'percentage', 'payment_amount' => 10.00, 'visitors' => 0, 'balance' => 0, 'withdrawn' => 0]
        );
    }

    public function recordVisit(Affiliate $affiliate): void
    {
        $affiliate->increment('visitors');
    }

    public function recordReferral(Affiliate $affiliate, float $commission): void
    {
        $affiliate->increment('balance', $commission);
    }

    public function requestWithdrawal(Affiliate $affiliate, float $amount): bool
    {
        if ($affiliate->balance < $amount) return false;
        $affiliate->decrement('balance', $amount);
        $affiliate->increment('withdrawn', $amount);
        return true;
    }
}
