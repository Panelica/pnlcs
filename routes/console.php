<?php

use Illuminate\Support\Facades\Schedule;

// Queue worker — process redis jobs, restart every 55s to avoid overlap
Schedule::command("queue:work redis --stop-when-empty --max-time=55 --sleep=3 --tries=3")->everyMinute()->withoutOverlapping();

Schedule::command("pnlcs:generate-invoices")->daily()->at("06:00");
Schedule::command("pnlcs:mark-overdue")->daily()->at("06:30");
Schedule::command("pnlcs:auto-suspend")->daily()->at("07:00");
Schedule::command("pnlcs:domain-sync")->daily()->at("03:00");
Schedule::command("pnlcs:payment-reminders")->daily()->at("08:00");
Schedule::command("pnlcs:apply-late-fees")->daily()->at("07:30");
Schedule::command("pnlcs:process-cancellations")->daily()->at("02:00");
Schedule::command("pnlcs:unsuspend-on-payment")->everyThirtyMinutes();
Schedule::command("pnlcs:cc-expiry-alerts")->monthly();

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
