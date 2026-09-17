<?php

use Illuminate\Support\Facades\Schedule;

// Queue worker — whatever connection this installation is configured for,
// restarted every 55s to avoid overlap. Naming redis here meant a worker
// watching a queue nothing wrote to wherever the setting said otherwise, and
// a connection error in the log every minute.
Schedule::command('queue:work --stop-when-empty --max-time=55 --sleep=3 --tries=3')->everyMinute()->withoutOverlapping();

Schedule::command('pnlcs:generate-invoices')->daily()->at('06:00');
Schedule::command('pnlcs:mark-overdue')->daily()->at('06:30');
// Collect with stored cards before anything punishes the customer for not
// having paid. No-op until AutoChargeEnabled is switched on in Settings ->
// General; the command returns before it reads an invoice. 06:45 is after the
// invoices exist (06:00) and their statuses have settled (06:30), and ahead of
// suspension (07:00), late fees (07:30) and the reminder emails (08:00) —
// AutoChargeCommand's docblock has the reasoning for each of those.
// withoutOverlapping() is belt: the braces are the unique key on
// invoice_charge_attempts.invoice_id, which is what actually stops two runs
// charging one invoice.
Schedule::command('pnlcs:auto-charge')->daily()->at('06:45')->withoutOverlapping(60);
// The rescue sweep, and it is not collection: no candidate query, no new
// invoice, nothing that was not already sent to a gateway by a run that died.
// It has to be frequent because the recovery has a deadline — Stripe keeps an
// idempotency key for at least 24 hours, so a replay is only provably the same
// request for 23, while two daily runs are 24 hours apart. Once a day, the
// rescue was always exactly one hour too late and every interrupted charge was
// parked for a human. Fifteen minutes is inside the 900-second lease and ahead
// of the 07:30 late fee, which would change the amount and make the replay
// unprovable. It returns before it reads anything while the feature is off.
// An explicit ten-minute mutex expiry, because Laravel's default is 1440
// minutes: a run killed with SIGKILL leaves a mutex behind, and on a daily job
// that lands within seconds of the next morning's start — which would skip a
// whole day's collection silently.
//
// IN THE BACKGROUND, because it shares three minutes with the jobs that punish
// a customer for not having paid. ScheduleRunCommand walks due events in
// registration order and runs each foreground event to completion, so at 07:00,
// 07:30 and 08:00 this sweep runs BEFORE auto-suspend, the late fee and the
// reminders — and it paces itself at two requests a second, so a sweep with
// five hundred stuck rows is four minutes of sleep standing in front of the
// suspension job. Nothing here reports through the command's output (the
// operator's channels are the error log, the email to SystemEmailAddress, the
// notification event and the dashboard count), so there is nothing for the
// background to swallow.
Schedule::command('pnlcs:auto-charge --rescue')->everyFifteenMinutes()->withoutOverlapping(10)->runInBackground();
Schedule::command('pnlcs:auto-suspend')->daily()->at('07:00');
// No-op until AutoTerminationEnabled is switched on in Settings -> Automation.
Schedule::command('pnlcs:auto-terminate')->daily()->at('08:00');
Schedule::command('pnlcs:domain-sync')->daily()->at('03:00');
Schedule::command('pnlcs:payment-reminders')->daily()->at('08:00');
Schedule::command('pnlcs:domain-renewal-reminders')->daily()->at('09:15');
// The registrar float, twice a day. An account that runs dry refuses renewals
// one customer at a time without ever raising an error.
Schedule::command('pnlcs:registrar-balance')->twiceDaily(8, 20);
Schedule::command('pnlcs:apply-late-fees')->daily()->at('07:30');
Schedule::command('pnlcs:process-cancellations')->daily()->at('02:00');
Schedule::command('pnlcs:unsuspend-on-payment')->everyThirtyMinutes();
Schedule::command('pnlcs:cc-expiry-alerts')->monthly();

// Cards customers have removed that the gateway is still holding. The click
// that removed one wrote down the request; this is the half that cannot be
// done inside a customer's own page load. Every five minutes because the
// promise is "we have stopped keeping your card" and an hour is a long time to
// keep it after saying so; it returns on one indexed SELECT when there is
// nothing waiting, which on an installation that has never stored a card is
// always. withoutOverlapping so that a sweep waiting on a slow gateway is not
// joined by the next one — with an explicit expiry, because Laravel's default
// is 1440 minutes and a process killed with SIGKILL would then leave a mutex
// that blocks every detach for the next twenty-four hours. That is the defect
// the charger's two entries above were given explicit expiries for; this one
// said 'no mutex expiry to guess at' and had the same hole. Ten minutes is well
// past a gateway round-trip and well short of a day.
Schedule::command('pnlcs:detach-payment-methods')->everyFiveMinutes()->withoutOverlapping(10);

// SSL Certificate Status Polling - every 5 minutes
Schedule::command('pnlcs:ssl-status-poll')->everyFiveMinutes();

// SSL Expiry Check - daily at 09:00
Schedule::command('pnlcs:ssl-expiry-check')->dailyAt('09:00');

// Ticket Escalation - every 15 minutes
Schedule::command('pnlcs:ticket-escalation')->everyFifteenMinutes();

// Usage Polling - hourly
Schedule::command('pnlcs:usage-polling')->hourly();

// Module queue — retry failed provisioning actions every 5 minutes
Schedule::command('pnlcs:module-queue')->everyFiveMinutes()->withoutOverlapping();

// Support mailbox import (IMAP/POP3 → tickets) — every 5 minutes
Schedule::command('pnlcs:mail-import')->everyFiveMinutes()->withoutOverlapping();

// Exchange rates — daily before invoice generation
Schedule::command('pnlcs:currency-update')->daily()->at('05:30');

// Database backup — daily, before the billing crons
Schedule::command('pnlcs:db-backup')->daily()->at('04:30')->withoutOverlapping();

// Prune high-volume log/history tables — daily, off-peak
Schedule::command('pnlcs:prune-logs')->daily()->at('03:45')->withoutOverlapping();
