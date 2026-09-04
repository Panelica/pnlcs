<?php

namespace App\Services;

use App\Mail\AffiliateWelcomeMail;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
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

        // The ledger the admin screens count from. It existed and nothing had
        // ever written a row to it.
        DB::table('affiliate_withdrawals')->insert([
            'affiliate_id' => $affiliate->id,
            'date' => now(),
            'amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Transaction::create([
            'client_id' => $affiliate->client_id,
            'gateway' => 'affiliate_payout',
            'transaction_id' => 'AFF-'.strtoupper(uniqid()),
            'amount_in' => 0,
            'amount_out' => $amount,
            'description' => 'Affiliate withdrawal - '.money_fmt($amount),
            'date' => now(),
        ]);

        return true;
    }

    /**
     * Move money out of the affiliate balance and into the client's account
     * credit. The affiliate keeps what they earned — it is just held as store
     * credit instead of being paid out in cash.
     */
    public function convertToCredit(Affiliate $affiliate, float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        return DB::transaction(function () use ($affiliate, $amount) {
            $affiliate = Affiliate::whereKey($affiliate->id)->lockForUpdate()->first();
            if (! $affiliate || $affiliate->balance < $amount) {
                return false;
            }

            $client = $affiliate->client;
            if (! $client) {
                return false;
            }

            $affiliate->decrement('balance', $amount);
            $affiliate->increment('withdrawn', $amount);
            $client->increment('credit', $amount);

            Transaction::create([
                'client_id' => $affiliate->client_id,
                'gateway' => 'affiliate_payout',
                'transaction_id' => 'AFFCR-'.strtoupper(uniqid()),
                'amount_in' => 0,
                'amount_out' => $amount,
                'description' => 'Affiliate balance added to account credit - '.money_fmt($amount),
                'date' => now(),
            ]);

            return true;
        });
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

        // Paid once per referred customer. Asked of the invoices themselves:
        // matching the description as text made "client#1" a prefix of
        // "client#12" and cancelled commissions that were genuinely owed.
        if ($affiliate->onetime) {
            $alreadyPaid = Transaction::where('gateway', 'affiliate_commission')
                ->where('client_id', $affiliate->client_id)
                ->whereIn('invoice_id', Invoice::where('client_id', $client->id)->select('id'))
                ->exists();

            if ($alreadyPaid) {
                return;
            }
        }

        // What was actually sold. Not the invoice total: that carries tax,
        // which belongs to the tax authority, and it counts an Add Funds line
        // as revenue - so a hundred put on account earned a commission on the
        // way in and another one when it was spent on hosting.
        $base = $this->commissionBase($invoice);

        if ($base <= 0) {
            return;
        }

        $commission = $this->calculateCommission($affiliate, $base);
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
     * Take back the commission on money that has been handed back.
     *
     * A part refund takes back the same part. An affiliate who has already
     * withdrawn the balance is not pushed into the red — the shortfall is
     * logged, the same way an Add Funds refund handles a balance that has
     * already been spent.
     */
    public function reverseCommission(Invoice $invoice, float $refundedAmount): void
    {
        $rows = Transaction::where('gateway', 'affiliate_commission')
            ->where('invoice_id', $invoice->id)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        // Everything earned on this invoice, and everything already taken back.
        // Reading only the latest row meant that after one part refund the
        // reversal itself was mistaken for the earning - its amount_in is zero,
        // so a second part refund took nothing and the affiliate kept the rest.
        $earned = (float) $rows->sum('amount_in');
        $alreadyReversed = (float) $rows->sum('amount_out');
        $outstanding = round($earned - $alreadyReversed, 2);

        if ($outstanding <= 0.009) {
            return;
        }

        $invoiceTotal = (float) $invoice->total;

        $share = $invoiceTotal > 0 ? min(1.0, $refundedAmount / $invoiceTotal) : 1.0;
        $reversal = min(round($earned * $share, 2), $outstanding);

        if ($reversal <= 0.009) {
            return;
        }

        $commission = $rows->firstWhere(fn ($row) => (float) $row->amount_in > 0) ?? $rows->first();

        $affiliate = Affiliate::where('client_id', $commission->client_id)->first();

        if (! $affiliate) {
            return;
        }

        $reclaim = min($reversal, (float) $affiliate->balance);

        if ($reclaim > 0.009) {
            $affiliate->decrement('balance', $reclaim);
        }

        Transaction::create([
            'client_id' => $affiliate->client_id,
            'invoice_id' => $invoice->id,
            'gateway' => 'affiliate_commission',
            'transaction_id' => 'AFFREV-'.strtoupper(uniqid()),
            'amount_in' => 0,
            'amount_out' => $reversal,
            'description' => "Affiliate commission reversed — invoice#{$invoice->id} refunded",
            'date' => now(),
        ]);

        if ($reversal - $reclaim > 0.009) {
            Log::warning('AffiliateService: commission reversed beyond the remaining balance', [
                'affiliate' => $affiliate->id,
                'invoice' => $invoice->id,
                'reversed' => $reversal,
                'reclaimed' => $reclaim,
            ]);
        }
    }

    /**
     * Calculate commission based on affiliate's pay type and optional tiers.
     */
    /**
     * The part of an invoice a commission is owed on.
     *
     * The lines themselves, less anything that only moves money onto the
     * customer's account. Tax is not among them, and neither is credit the
     * customer is buying rather than spending.
     */
    public function commissionBase(Invoice $invoice): float
    {
        if ($invoice->items()->count() === 0) {
            // Nothing to leave out. Refusing a commission on an invoice that
            // carries no lines would be worse than counting its total.
            return (float) $invoice->total;
        }

        return (float) $invoice->items()
            ->where('type', '!=', 'AddFunds')
            ->sum('amount');
    }

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
