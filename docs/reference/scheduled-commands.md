# Scheduled Commands

PNLCS automates recurring work through Laravel's scheduler. **One** cron line
drives all of it:

```
* * * * * cd /path/to/pnlcs && php artisan schedule:run >> /dev/null 2>&1
```

On the official Docker image this runs inside the container automatically.

## What the scheduler runs

| Command | Schedule | What it does |
|---------|----------|--------------|
| `pnlcs:generate-invoices` | Daily 06:00 | Create renewal invoices for upcoming due dates |
| `pnlcs:mark-overdue` | Daily 06:30 | Flag unpaid invoices past due as overdue |
| `pnlcs:auto-suspend` | Daily 07:00 | Suspend services with long-unpaid invoices |
| `pnlcs:apply-late-fees` | Daily 07:30 | Add late fees to overdue invoices |
| `pnlcs:payment-reminders` | Daily 08:00 | Email upcoming/overdue payment reminders |
| `pnlcs:process-cancellations` | Daily 02:00 | Action scheduled cancellations |
| `pnlcs:domain-sync` | Daily 03:00 | Sync domain statuses with registrars |
| `pnlcs:unsuspend-on-payment` | Every 30 min | Unsuspend services whose invoice was just paid |
| `pnlcs:module-queue` | Every 5 min | Retry failed provisioning jobs (backoff) |
| `pnlcs:mail-import` | Every 5 min | Import support mailboxes into tickets |
| `pnlcs:currency-update` | Daily 05:30 | Refresh exchange rates |
| `pnlcs:db-backup` | Daily 04:30 | Gzip database backup with rotation |
| `pnlcs:prune-logs` | Daily 03:45 | Trim old log/history rows |
| `pnlcs:ssl-status-poll` | Every 5 min | Poll SSL order status |
| `pnlcs:ssl-expiry-check` | Daily 09:00 | Warn about expiring certificates |
| `pnlcs:ticket-escalation` | Every 15 min | Escalate tickets per your rules |
| `pnlcs:usage-polling` | Hourly | Pull disk/bandwidth usage for overage billing |

## Running a command manually

You can run any of these by hand from the project directory, e.g.:

```bash
php artisan pnlcs:db-backup
php artisan pnlcs:currency-update --force
php artisan pnlcs:prune-logs --dry-run
```

## The queue worker

Emails and background jobs run through a queue. Keep a worker running (via
supervisor) so they process promptly:

```ini
[program:pnlcs-worker]
command=php /path/to/pnlcs/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
```

## Relevant settings

| Setting | Effect |
|---------|--------|
| `db_backup_enabled` / `db_backup_retention` | Toggle backups, how many to keep |
| `currency_auto_update` | Toggle exchange-rate updates |
| `retention_*_days` | How long each log/history table is kept |
