<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function index(Request $request): View
    {
        $query = Affiliate::with('client');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('client', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $affiliates = $query->orderBy('balance', 'desc')->paginate(25);

        // Stats
        $totalAffiliates = Affiliate::count();
        $totalEarnings = Affiliate::sum('balance') + Affiliate::sum('withdrawn');
        $totalWithdrawn = Affiliate::sum('withdrawn');
        $totalVisitors = Affiliate::sum('visitors');

        return view('admin.affiliates.index', compact('affiliates', 'totalAffiliates', 'totalEarnings', 'totalWithdrawn', 'totalVisitors'));
    }

    public function show(Affiliate $affiliate): View
    {
        $affiliate->load('client');

        // Get referral transactions
        $transactions = \App\Models\Transaction::where('client_id', $affiliate->client_id)
            ->where('description', 'like', '%affiliate%')
            ->orderBy('date', 'desc')
            ->paginate(25);

        return view('admin.affiliates.show', compact('affiliate', 'transactions'));
    }

    public function update(Request $request, Affiliate $affiliate)
    {
        $validated = $request->validate([
            'pay_type' => 'required|in:percentage,flat',
            'pay_amount' => 'required|numeric|min:0',
            'onetime' => 'nullable|boolean',
        ]);

        $affiliate->update([
            'pay_type' => $validated['pay_type'],
            'pay_amount' => $validated['pay_amount'],
            'onetime' => $request->boolean('onetime'),
        ]);

        return back()->with('success', 'Affiliate updated.');
    }

    public function payout(Request $request, Affiliate $affiliate)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $affiliate->balance,
        ]);

        $amount = (float) $request->amount;

        $affiliate->decrement('balance', $amount);
        $affiliate->increment('withdrawn', $amount);

        \App\Models\Transaction::create([
            'client_id' => $affiliate->client_id,
            'invoice_id' => null,
            'gateway' => 'affiliate_payout',
            'transaction_id' => 'AFF-' . strtoupper(uniqid()),
            'amount_in' => 0,
            'amount_out' => $amount,
            'description' => "Affiliate payout - \${$amount}",
            'date' => now(),
        ]);

        return back()->with('success', "Payout of \${$amount} processed.");
    }
}
