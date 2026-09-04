<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\AffiliateService;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $client = $this->currentClient();
        $affiliate = $client ? Affiliate::where('client_id', $client->id)->first() : null;

        $stats = [
            'referrals' => $affiliate?->visitors ?? 0,
            // The customers who arrived through this affiliate's link. It used
            // to be a hardcoded zero, however many they had brought in.
            'signups' => $affiliate
                ? Client::where('affiliate_id', $affiliate->id)->count()
                : 0,
            'earnings' => ($affiliate?->balance ?? 0) + ($affiliate?->withdrawn ?? 0),
            'pending' => $affiliate?->balance ?? 0,
        ];

        $referralLink = url('/').'?ref='.($affiliate?->id ?? '');

        $commissions = collect();
        if ($affiliate) {
            $commissions = Transaction::where('client_id', $client->id)
                ->where('gateway', 'affiliate_commission')
                ->with('invoice.client')
                ->orderBy('date', 'desc')
                ->limit(50)
                ->get();
        }

        return view('client.affiliate.index', compact('affiliate', 'client', 'stats', 'referralLink', 'commissions'));
    }

    public function activate()
    {
        $client = $this->currentClient();

        if (! $client) {
            return back()->with('error', __('messages.error.no_client_account_found'));
        }

        $existing = Affiliate::where('client_id', $client->id)->first();
        if ($existing) {
            return back()->with('info', __('messages.info.already_an_affiliate'));
        }

        Affiliate::create([
            'client_id' => $client->id,
            'visitors' => 0,
            'pay_type' => 'percentage',
            'pay_amount' => 10,
            'onetime' => false,
            'balance' => 0,
            'withdrawn' => 0,
        ]);

        return back()->with('success', __('messages.success.your_affiliate_account_has_been_activated'));
    }

    public function withdraw(Request $request)
    {
        $client = $this->currentClient();
        $affiliate = $client ? Affiliate::where('client_id', $client->id)->first() : null;

        if (! $affiliate) {
            return back()->with('error', __('messages.error.no_affiliate_account_found'));
        }

        if ($affiliate->balance <= 0) {
            return back()->with('error', __('messages.error.you_have_no_balance_to_withdraw'));
        }

        $minimum = (float) Setting::get('AffiliateMinPayout', 25);

        $request->validate([
            'amount' => 'required|numeric|min:'.$minimum.'|max:'.$affiliate->balance,
        ], [], ['amount' => __('client.affiliates.amount')]);

        $amount = (float) $request->amount;

        // Through the service, so the movement is recorded: a transaction, a
        // row in the withdrawals ledger, and the minimum payout honoured. This
        // used to move the balance and leave no trace of it anywhere.
        if (! app(AffiliateService::class)->requestWithdrawal($affiliate, $amount)) {
            return back()->withErrors([
                'amount' => __('messages.error.withdrawal_not_possible', ['minimum' => number_format($minimum, 2)]),
            ]);
        }

        return back()->with('success', __('messages.success.withdrawal_request_submitted', ['amount' => number_format($amount, 2)]));
    }

    public function toBalance(Request $request)
    {
        $client = $this->currentClient();
        $affiliate = $client ? Affiliate::where('client_id', $client->id)->first() : null;

        if (! $affiliate) {
            return back()->with('error', __('messages.error.no_affiliate_account_found'));
        }

        if ($affiliate->balance <= 0) {
            return back()->with('error', __('messages.error.you_have_no_balance_to_withdraw'));
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:'.$affiliate->balance,
        ], [], ['amount' => __('client.affiliates.amount')]);

        $amount = (float) $request->amount;

        if (! app(AffiliateService::class)->convertToCredit($affiliate, $amount)) {
            return back()->withErrors([
                'amount' => __('messages.error.withdrawal_not_possible', ['minimum' => '0.01']),
            ]);
        }

        return back()->with('success', __('messages.success.affiliate_credited', ['amount' => number_format($amount, 2)]));
    }
}
