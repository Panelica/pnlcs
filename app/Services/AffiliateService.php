<?php

namespace App\Services;

use App\Mail\AffiliateWelcomeMail;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AffiliateService
{
    public function activateAffiliate(Client $client): Affiliate
    {
        $affiliate = Affiliate::firstOrCreate(
            ['client_id' => $client->id],
            ['visitors' => 0, 'pay_type' => 'percentage', 'pay_amount' => 10.00, 'onetime' => false, 'balance' => 0, 'withdrawn' => 0]
        );

        if ($affiliate->wasRecentlyCreated && $client->email) {
            Mail::to($client->email)->queue(new AffiliateWelcomeMail($client, $affiliate));
        }

        return $affiliate;
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
        $minPayout = (float) Setting::get('AffiliateMinPayout', 25);
        if ($affiliate->balance < $amount || $amount < $minPayout) {
            return false;
        }

        $affiliate->decrement('balance', $amount);
        $affiliate->increment('withdrawn', $amount);

        Transaction::create([
            'client_id' => $affiliate->client_id,
            'gateway' => 'affiliate_payout',
            'transaction_id' => 'AFF-'.strtoupper(uniqid()),
            'amount_in' => 0,
            'amount_out' => $amount,
            'description' => "Affiliate withdrawal - \${$amount}",
            'date' => now(),
        ]);

        return true;
    }

    /**
     * Process affiliate commission when an invoice is paid.
     * Call this from the invoice payment handler.
     */
    public function processCommission(Invoice $invoice): void
    {
        $client = $invoice->client;
        if (! $client) {
            return;
        }

        // Check if the client was referred via cookie (stored on client record or via referral tracking)
        $affiliateId = $client->affiliate_id ?? null;
        if (! $affiliateId) {
            return;
        }

        $affiliate = Affiliate::find($affiliateId);
        if (! $affiliate) {
            return;
        }

        // Check one-time: if already paid commission for this client, skip
        if ($affiliate->onetime) {
            $existingCommission = Transaction::where('client_id', $affiliate->client_id)
                ->where('description', 'like', "%referral%client#{$client->id}%")
                ->exists();

            if ($existingCommission) {
                return;
            }
        }

        // Calculate commission
        $commission = $this->calculateCommission($affiliate, (float) $invoice->total);
        if ($commission <= 0) {
            return;
        }

        $affiliate->increment('balance', $commission);

        Transaction::create([
            'client_id' => $affiliate->client_id,
            'invoice_id' => $invoice->id,
            'gateway' => 'affiliate_commission',
            'transaction_id' => 'AFFCOM-'.strtoupper(uniqid()),
            'amount_in' => $commission,
            'amount_out' => 0,
            'description' => "Affiliate referral commission - client#{$client->id} invoice#{$invoice->id}",
            'date' => now(),
        ]);

        Log::info("Affiliate commission: affiliate#{$affiliate->id} earned \${$commission} from invoice#{$invoice->id}");
    }

    /**
     * Calculate commission based on affiliate's pay type and optional tiers.
     */
    public function calculateCommission(Affiliate $affiliate, float $invoiceTotal): float
    {
        if ($affiliate->pay_type === 'percentage') {
            return round($invoiceTotal * ($affiliate->pay_amount / 100), 2);
        }

        // Flat amount
        return round($affiliate->pay_amount, 2);
    }

    /**
     * Link a new client to an affiliate (from cookie tracking).
     */
    public function linkClientToAffiliate(Client $client, int $affiliateId): void
    {
        $affiliate = Affiliate::find($affiliateId);
        if (! $affiliate) {
            return;
        }

        // Don't let affiliates refer themselves.
        if ($affiliate->client_id === $client->id) {
            return;
        }

        // First referral wins — a later cookie must not steal an existing one.
        if ($client->affiliate_id) {
            return;
        }

        $client->update(['affiliate_id' => $affiliateId]);

        Log::info("Affiliate referral linked: affiliate#{$affiliate->id} -> client#{$client->id}");
    }
}
