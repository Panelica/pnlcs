<?php

namespace Modules\Addons\BankPayments\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Services\BankSyncService;

class BankSyncController extends BankPaymentsController
{
    public function store(Request $request, BankSyncService $sync): RedirectResponse
    {
        $this->ensureActive();

        $validated = $request->validate([
            'mailbox' => ['nullable', 'integer', 'exists:bank_mailboxes,id'],
        ]);

        $mailboxes = BankMailbox::query()
            ->where('active', true)
            ->when($validated['mailbox'] ?? null, fn ($query, int $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        if ($mailboxes->isEmpty()) {
            return back()->with('error', __('bank-payments::messages.no_active_mailboxes'));
        }

        $errors = [];

        foreach ($mailboxes as $mailbox) {
            $result = $sync->sync($mailbox);

            if ($result['status'] === 'error') {
                $errors[] = $mailbox->name.': '.$result['message'];
            }
        }

        if ($errors !== []) {
            return back()->with('error', implode(' | ', $errors));
        }

        return back()->with('success', __('bank-payments::messages.sync_ok'));
    }
}
