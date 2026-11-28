<?php

namespace Modules\Addons\BankPayments\Http\Controllers\Admin;

use Illuminate\View\View;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Models\BankMessage;
use Modules\Addons\BankPayments\Models\BankSyncLog;
use Modules\Addons\BankPayments\Models\BankTransaction;
use Modules\Addons\BankPayments\Services\BankPaymentsSettings;

class BankPaymentsDashboardController extends BankPaymentsController
{
    public function index(): View
    {
        $this->ensureActive();

        return view('bank-payments::admin.dashboard', [
            'mode' => BankPaymentsSettings::mode(),
            'mailboxCount' => BankMailbox::count(),
            'activeMailboxCount' => BankMailbox::where('active', true)->count(),
            'messageCount' => BankMessage::count(),
            'transactionCount' => BankTransaction::count(),
            'pendingMatchCount' => BankTransaction::whereIn('matching_status', ['pending', 'review', 'ambiguous', 'auto_ready', 'manual_currency', 'error'])->count(),
            'lastSync' => BankSyncLog::with('mailbox')->latest('started_at')->first(),
            'recentMessages' => BankMessage::with('mailbox')->latest('id')->limit(8)->get(),
        ]);
    }

    public function logs(): View
    {
        $this->ensureActive();

        return view('bank-payments::admin.logs.index', [
            'logs' => BankSyncLog::with('mailbox')->latest('started_at')->paginate(30)->withQueryString(),
        ]);
    }
}
