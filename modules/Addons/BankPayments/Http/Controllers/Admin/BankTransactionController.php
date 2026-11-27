<?php

namespace Modules\Addons\BankPayments\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Models\BankTransaction;

class BankTransactionController extends BankPaymentsController
{
    public function index(Request $request): View
    {
        $this->ensureActive();

        $filters = $request->validate([
            'mailbox' => ['nullable', 'integer', 'exists:bank_mailboxes,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'q' => ['nullable', 'string', 'max:100'],
            'matching' => ['nullable', 'string', 'in:pending,review,ambiguous,auto_ready,unmatched,manual_currency,error'],
        ]);

        $transactions = BankTransaction::with('message.mailbox')
            ->when($filters['mailbox'] ?? null, fn ($query, int $id) => $query->whereHas(
                'message',
                fn ($message) => $message->where('bank_mailbox_id', $id)
            ))
            ->when($filters['currency'] ?? null, fn ($query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('reference', 'like', '%'.$term.'%')
                        ->orWhere('sender_name', 'like', '%'.$term.'%');
                });
            })
            ->when($filters['matching'] ?? null, fn ($query, string $status) => $query->where('matching_status', $status))
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('bank-payments::admin.transactions.index', [
            'transactions' => $transactions,
            'mailboxes' => BankMailbox::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(BankTransaction $transaction): View
    {
        $this->ensureActive();

        $transaction->load(['message.mailbox', 'matches.invoice.client', 'import']);

        return view('bank-payments::admin.transactions.show', compact('transaction'));
    }
}
