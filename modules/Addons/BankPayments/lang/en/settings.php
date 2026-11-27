<?php

return [
    'mode' => 'Mode',
    'mode_test' => 'Test — no booking',
    'mode_semi_auto' => 'Semi-automatic — booking after approval',
    'mode_auto' => 'Automatic — unambiguous matches only',
    'mode_hint' => 'Automatic mode books only unambiguous matches: number, full amount and client. Partial payments, overpayments and multiple invoices require approval.',
    'initial_sync_days' => 'Initial sync history (days)',
    'initial_sync_days_hint' => 'The first import is limited to this range. Default: 7 days.',
    'sync_limit' => 'Messages per sync',
    'sync_limit_hint' => 'Maximum number of messages read in one run (1–500).',
    'mark_seen' => 'Mark processed messages as read',
    'mark_seen_hint' => 'Messages are never deleted from the mailbox.',
    'retention_days' => 'Data retention (days)',
    'retention_days_hint' => 'Stored for later cleanup.',
];
