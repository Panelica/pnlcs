# Scheduled Commands

Everything PNLCS does by itself runs from Laravel's scheduler, driven by
**one** cron line for the web server user:

```
* * * * * cd /var/www/pnlcs && php artisan schedule:run >> /dev/null 2>&1
```

The Docker image runs the scheduler inside the container; there is no cron
line to add there.

## What runs, and when

Times are the server's time zone.

### Billing

| Command | When | What it does |
|---|---|---|
| `pnlcs:generate-invoices` | daily 06:00 | Raises renewal invoices for services, addons and domains coming due |
| `pnlcs:mark-overdue` | daily 06:30 | Marks unpaid invoices past their due date as overdue |
| `pnlcs:auto-charge` | daily 06:45 | Charges stored cards for due invoices; does nothing until **Automatic Payment** is switched on (**Setup → General Settings**) |
| `pnlcs:auto-charge --rescue` | every 15 minutes | Finishes card charges a crashed run left halfway; does nothing while automatic payment is off |
| `pnlcs:apply-late-fees` | daily 07:30 | Adds late fees to overdue invoices, if late fees are set up |
| `pnlcs:payment-reminders` | daily 08:00 | Emails reminders for invoices coming due and overdue |
| `pnlcs:unsuspend-on-payment` | every 30 minutes | Unsuspends services whose invoice has been paid |
| `pnlcs:cc-expiry-alerts` | monthly | Tells customers whose stored card is about to expire |
| `pnlcs:detach-payment-methods` | every 5 minutes | Asks the gateway to forget cards customers removed |
| `pnlcs:currency-update` | daily 05:30 | Updates exchange rates (setting `currency_auto_update`; `--force` runs it anyway) |

### Services

| Command | When | What it does |
|---|---|---|
| `pnlcs:auto-suspend` | daily 07:00 | Suspends services whose invoices stayed unpaid past the grace period |
| `pnlcs:auto-terminate` | daily 08:00 | Terminates long-suspended services; does nothing until **auto-termination** is switched on (**Setup → General Settings → Automation**) |
| `pnlcs:process-cancellations` | daily 02:00 | Carries out cancellation requests that are due |
| `pnlcs:module-queue` | every 5 minutes | Retries server actions that failed, with increasing delays |
| `pnlcs:usage-polling` | hourly | Reads disk and bandwidth use from the servers, for overage billing |

### Domains and SSL

| Command | When | What it does |
|---|---|---|
| `pnlcs:domain-sync` | daily 03:00 | Reads expiry, status and nameservers from the registrars |
| `pnlcs:domain-renewal-reminders` | daily 09:15 | Emails customers whose domains are about to expire |
| `pnlcs:registrar-balance` | 08:00 and 20:00 | Warns when the prepaid balance at the watched registrar falls to the floor |
| `pnlcs:ssl-status-poll` | every 5 minutes | Checks pending certificate orders with the SSL provider |
| `pnlcs:ssl-expiry-check` | daily 09:00 | Warns about certificates about to expire |

### Support and upkeep

| Command | When | What it does |
|---|---|---|
| `pnlcs:mail-import` | every 5 minutes | Turns email in support mailboxes into tickets |
| `pnlcs:ticket-escalation` | every 15 minutes | Applies your ticket escalation rules |
| `pnlcs:db-backup` | daily 04:30 | Backs up the database ([Backups](../install/backups.md)) |
| `pnlcs:prune-logs` | daily 03:45 | Deletes old rows from log and history tables (`retention_*_days` settings) |
| `queue:work` | every minute | Sends queued mail and runs queued jobs, then stops |

## The queue

With `QUEUE_CONNECTION=sync` (the default), mail and jobs run immediately and
the `queue:work` line has nothing to do. With `QUEUE_CONNECTION=database`, the
scheduler's `queue:work` sends queued work within a minute: no separate worker
is needed. Run a permanent worker only if you want queued jobs to go out
instantly; see [installation step 14](../install/native.md#14-queue-no-separate-worker-needed).

## Running a command by hand

From the PNLCS directory, as the web server user:

```bash
php artisan pnlcs:db-backup
php artisan pnlcs:currency-update --force
php artisan pnlcs:prune-logs --dry-run
php artisan schedule:list          # everything scheduled, with the next run time
```

**Utilities → Automation Status** in the admin area shows when the scheduled
jobs last ran.
