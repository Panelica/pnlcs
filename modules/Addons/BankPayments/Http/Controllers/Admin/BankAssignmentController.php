<?php

namespace Modules\Addons\BankPayments\Http\Controllers\Admin;

use App\Models\ActivityLog;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Addons\BankPayments\Models\BankImport;
use Modules\Addons\BankPayments\Models\BankMatch;
use Modules\Addons\BankPayments\Models\BankPayerProfile;
use Modules\Addons\BankPayments\Models\BankTransaction;
use Modules\Addons\BankPayments\Services\InvoiceMatcher;

class BankAssignmentController extends BankPaymentsController
{
    public function show(BankTransaction $transaction, Request $request): View
    {
        $this->ensureActive();
        $this->ensureAssignable($transaction);

        $query = trim((string) $request->query('q', ''));

        return view('bank-payments::admin.transactions.assign', [
            'transaction' => $transaction->loadMissing('message.mailbox', 'matches.invoice.client'),
            'invoices' => $this->invoices($query),
            'query' => $query,
        ]);
    }

    public function store(Request $request, BankTransaction $transaction, InvoiceMatcher $matcher): RedirectResponse
    {
        $this->ensureActive();
        $this->ensureAssignable($transaction);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $invoice = Invoice::with('client')->findOrFail((int) $validated['invoice_id']);

        if (! in_array((string) $invoice->status, InvoiceMatcher::CANDIDATE_STATUSES, true)) {
            return back()->with('error', __('bank-payments::matches.assign_invoice_closed'));
        }

        if (strtoupper((string) $transaction->currency) !== 'PLN'
            || (filled($invoice->source_currency) && strtoupper((string) $invoice->source_currency) !== 'PLN')) {
            return back()->with('error', __('bank-payments::matches.currency_not_supported'));
        }

        $balance = round((float) $invoice->amountDue(), 2);

        if ($balance <= 0.009) {
            return back()->with('error', __('bank-payments::matches.assign_invoice_settled'));
        }

        $amount = isset($validated['amount'])
            ? round((float) $validated['amount'], 2)
            : round(min((float) $transaction->amount, $balance), 2);

        if ($amount > $balance + 0.01) {
            return back()->with('error', __('bank-payments::matches.assign_amount_too_high'));
        }

        // Said here as well as at booking time, where it is enforced: the
        // operator finds out while choosing the amount, not after approving it.
        $bookedFromTransfer = (float) BankImport::query()
            ->where('bank_transaction_id', $transaction->id)
            ->where('status', 'imported')
            ->sum('amount');

        if (round($bookedFromTransfer + $amount, 2) > round((float) $transaction->amount, 2) + 0.01) {
            return back()->with('error', __('bank-payments::imports.amount_exceeds_transfer'));
        }

        $candidate = $matcher->scoreInvoice($transaction, $invoice);

        $match = BankMatch::updateOrCreate(
            [
                'bank_transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
            ],
            [
                'rank' => 1,
                'score' => $candidate['score'],
                'amount' => $amount,
                'criteria' => [
                    'number_match' => $candidate['number_match'],
                    'amount_match' => $candidate['amount_match'],
                    'client_match' => $candidate['client_match'],
                    'learned_match' => $candidate['learned_match'],
                    'transaction_amount' => round((float) $transaction->amount, 2),
                    'invoice_balance' => $balance,
                    'invoice_number' => $invoice->invoice_num,
                    'sender_name' => $transaction->sender_name,
                    'client_name' => $candidate['client_name'],
                    'currency' => strtoupper((string) $transaction->currency),
                    'manual' => true,
                ],
                'status' => 'proposed',
                'reason' => null,
            ],
        );

        $transaction->update([
            'matching_status' => 'review',
            'matching_score' => $candidate['score'],
            'matching_reason' => __('bank-payments::matches.manual_assigned'),
            'matched_at' => now(),
        ]);

        $admin = auth('admin')->user();

        ActivityLog::log(
            'Bank payment assigned manually to invoice '.($invoice->invoice_num ?: $invoice->id),
            $admin?->email,
            $invoice->client_id,
            $invoice->id,
        );

        return redirect()
            ->route('admin.bank-payments.matches.index')
            ->with('success', __('bank-payments::matches.assigned'));
    }

    public function payers(): View
    {
        $this->ensureActive();

        return view('bank-payments::admin.payers.index', [
            'profiles' => BankPayerProfile::with('client')->orderByDesc('last_matched_at')->paginate(30),
        ]);
    }

    public function forgetPayer(BankPayerProfile $profile): RedirectResponse
    {
        $this->ensureActive();

        $profile->delete();

        return back()->with('success', __('bank-payments::matches.payer_forgotten'));
    }

    private function ensureAssignable(BankTransaction $transaction): void
    {
        abort_if(in_array((string) $transaction->matching_status, ['imported', 'rejected'], true), 404);
        abort_if($transaction->import()->where('status', 'imported')->exists(), 404);
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function invoices(string $search)
    {
        return Invoice::query()
            ->with('client')
            ->whereIn('status', InvoiceMatcher::CANDIDATE_STATUSES)
            ->whereNotNull('client_id')
            ->where(function ($query): void {
                $query->whereNull('source_currency')
                    ->orWhere('source_currency', '')
                    ->orWhere('source_currency', 'PLN');
            })
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('invoice_num', 'like', $term)
                        ->orWhere('buyer_company_name', 'like', $term)
                        ->orWhere('buyer_first_name', 'like', $term)
                        ->orWhere('buyer_last_name', 'like', $term)
                        ->orWhere('buyer_email', 'like', $term)
                        ->orWhereHas('client', function ($client) use ($term): void {
                            $client->where('company_name', 'like', $term)
                                ->orWhere('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            })
            ->latest('date')
            ->limit(25)
            ->get();
    }
}
