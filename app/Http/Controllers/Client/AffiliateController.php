<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Transaction;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function index()
    {
        $client = auth()->user()->clients()->first();
        $affiliate = $client ? Affiliate::where('client_id', $client->id)->first() : null;

        $referralHistory = collect();
        if ($affiliate) {
            $referralHistory = Transaction::where('client_id', $client->id)
                ->where('description', 'like', '%affiliate%')
                ->orderBy('date', 'desc')
                ->limit(50)
                ->get();
        }

        return view('client.affiliate.index', compact('affiliate', 'client', 'referralHistory'));
    }

    public function activate()
    {
        $client = auth()->user()->clients()->first();

        if (! $client) {
            return back()->with('error', 'No client account found.');
        }

        $existing = Affiliate::where('client_id', $client->id)->first();
        if ($existing) {
            return back()->with('info', 'You are already an affiliate.');
        }

        Affiliate::create([
            'client_id' => $client->id,
            'visitors'  => 0,
            'pay_type'  => 'percentage',
            'pay_amount'=> 10,
            'onetime'   => false,
            'balance'   => 0,
            'withdrawn' => 0,
        ]);

        return back()->with('success', 'Your affiliate account has been activated!');
    }

    public function withdraw(Request $request)
    {
        $client = auth()->user()->clients()->first();
        $affiliate = $client ? Affiliate::where('client_id', $client->id)->first() : null;

        if (! $affiliate) {
            return back()->with('error', 'No affiliate account found.');
        }

        if ($affiliate->balance <= 0) {
            return back()->with('error', 'You have no balance to withdraw.');
        }

        $request->validate([
            'amount' => 'required|numeric|min:1|max:' . $affiliate->balance,
        ]);

        $amount = (float) $request->amount;

        $affiliate->increment('withdrawn', $amount);
        $affiliate->decrement('balance', $amount);

        return back()->with('success', 'Withdrawal request of $' . number_format($amount, 2) . ' submitted successfully.');
    }
}
