<?php

namespace Modules\Addons\BankPayments\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Modules\Addons\BankPayments\Mail\BankPaymentsReviewMail;
use Modules\Addons\BankPayments\Models\BankMailbox;

class BankPaymentsReviewNotifier
{
    public function notify(BankMailbox $mailbox, int $matchCount): void
    {
        if ($matchCount <= 0) {
            return;
        }

        $settings = BankPaymentsSettings::resolve();

        if (! $settings['notify_on_review']) {
            return;
        }

        $to = trim((string) ($settings['notification_email'] ?: Setting::get('SystemEmailAddress')));

        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            Log::warning('BankPayments: no valid review notification address', [
                'mailbox_id' => $mailbox->id,
            ]);

            return;
        }

        $queueUrl = Route::has('admin.bank-payments.matches.index')
            ? route('admin.bank-payments.matches.index')
            : url('/admin/bank-payments/matches');

        Mail::to($to)->queue(new BankPaymentsReviewMail(
            $mailbox->name,
            $matchCount,
            $queueUrl,
        ));
    }
}
