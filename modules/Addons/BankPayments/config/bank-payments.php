<?php

use Modules\Addons\BankPayments\Services\Parsers\NestBankParser;

return [
    /*
    |--------------------------------------------------------------------------
    | BankPayments defaults
    |--------------------------------------------------------------------------
    |
    | Operator values live in the generic addon settings store under
    | addon = "bank_payments". These values are only the defaults used when a
    | setting has never been saved.
    |
    */

    'mode' => 'test',

    // Where to tell staff that the matching queue needs a decision. Empty
    // falls back to the panel's SystemEmailAddress.
    'notification_email' => null,
    'notify_on_review' => true,

    // The first import must not read an entire mailbox. Later imports continue
    // from the last UID when UIDVALIDITY has not changed.
    'initial_sync_days' => 7,
    'sync_limit' => 100,

    // Bank notification messages are marked as read after being stored. They
    // are never deleted by the addon.
    'mark_seen' => true,

    // Kept for the later matching/import stages; the first stage stores the
    // value but does not delete anything yet.
    'retention_days' => 180,

    'banks' => [
        'nest_bank' => [
            'label' => 'bank-payments::banks.nest_bank',
            'parser' => 'nest_bank',

            // Official Nest Bank incoming Elixir sessions. The addon adds
            // booking_delay_minutes so the mailbox has time to receive the
            // notification after the bank posts the transfer.
            'sessions' => ['10:30', '14:15', '17:00'],
            'booking_delay_minutes' => 30,
            'booking_grace_minutes' => 10,

            // Only weekdays for now. Holidays can be added later without
            // changing the scheduling code.
            'weekdays_only' => true,
            'holidays' => [],
        ],
    ],

    'parsers' => [
        'nest_bank' => NestBankParser::class,
    ],

    'scheduler' => [
        'frequency_minutes' => 5,
    ],
];
