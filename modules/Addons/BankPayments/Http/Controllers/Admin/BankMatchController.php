<?php

namespace Modules\Addons\BankPayments\Http\Controllers\Admin;

use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Addons\BankPayments\Models\BankMatch;
use Modules\Addons\BankPayments\Models\BankTransaction;
use Modules\Addons\BankPayments\Services\BankPaymentImporter;
use Modules\Addons\BankPayments\Services\BankPaymentsSettings;
use Modules\Addons\BankPayments\Services\InvoiceMatcher;

class BankMatchController extends BankPaymentsController
{
    public function index(Request $request): View
    {
        $this->ensureActive();

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,review,ambiguous,auto_ready,unmatched,manual_currency,error'],
        ]);

        $statuses = [
            'pending', 'review', 'ambiguous', 'auto_ready',
            'unmatched', 'manual_currency', 'error',
        ];

        $transactions = BankTransaction::query()
            ->with(['message.mailbox', 'matches.invoice.client'])
            ->whereIn('matching_status', $validated['status'] ?? $statuses)
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('bank-payments::admin.matches.index', [
            'transactions' => $transactions,
            'statuses' => $statuses,
            'selectedStatus' => $validated['status'] ?? null,
            'mode' => BankPaymentsSettings::mode(),
        ]);
    }

    public function approve(Request $request, BankMatch $match, BankPaymentImporter $importer): RedirectResponse
    {
        $this->ensureActive();

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        /** @var Admin|null $admin */
        $admin = auth('admin')->user();
        $result = $importer->import($match, $admin, isset($validated['amount']) ? (float) $validated['amount'] : null, 'manual');

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function rematch(BankTransaction $transaction, InvoiceMatcher $matcher): RedirectResponse
    {
        $this->ensureActive();

        abort_if(in_array((string) $transaction->matching_status, ['imported', 'rejected'], true), 404);
        abort_if($transaction->import()->where('status', 'imported')->exists(), 404);

        $result = $matcher->match($transaction);

        return back()->with(
            $result['status'] === 'unmatched' ? 'info' : 'success',
            __('bank-payments::matches.rematched', ['status' => __('bank-payments::matches.status_'.$result['status'])])
        );
    }

    public function reject(BankMatch $match): RedirectResponse
    {
        $this->ensureActive();

        $match->update([
            'status' => 'rejected',
            'reviewed_by' => auth('admin')->id(),
            'reviewed_at' => now(),
        ]);

        $match->transaction?->update([
            'matching_status' => 'rejected',
            'matching_reason' => __('bank-payments::imports.rejected'),
            'matched_at' => now(),
        ]);

        return back()->with('success', __('bank-payments::imports.rejected'));
    }
}
