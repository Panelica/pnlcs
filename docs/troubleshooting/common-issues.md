# Common Issues

The most frequent "why isn't this working?" moments and how to fix them.

## Emails are not being sent

**Symptom:** customers don't get invoices, welcome emails or ticket replies.

Checklist:

1. **Is SMTP configured?** Until you set it up, the mailer defaults to `log` and
   nothing is actually sent. See [Configure Email](../guides/configure-email.md).
2. **Send a test email** from **Settings → Email**. Read the error it shows.
3. **Check the log** — failed sends are recorded in `storage/logs/laravel.log`.
4. **Is the queue worker running?** Emails are queued. If no worker is
   processing the queue, they pile up unsent. See
   [Scheduled Commands](../reference/scheduled-commands.md).
5. **Landing in spam?** Set up SPF, DKIM and DMARC for your sending domain, or
   use a transactional email provider.

## Provisioning did not happen

**Symptom:** an invoice is paid but no hosting account was created (service
stays *Pending*, or shows *Active* with no real account).

Checklist:

1. **Does the product have a server + module?** A product set to *Custom* /
   *None* will never auto-provision. Edit the product and assign a server.
2. **What is the product's auto-setup?** If it's *manual*, you must accept each
   order under **Orders**. If it's *on payment*, paying triggers provisioning.
3. **Test the server connection** — **Configuration → Servers → (your server) →
   Test Connection**. Bad credentials or a blocked API port is the usual cause.
4. **Check the retry queue** — failed provisioning jobs are retried
   automatically every few minutes, and permanent failures raise an admin
   alert. Look at the module log for the exact API error.
5. **Cron running?** The retry runner only fires if the scheduler is running.

## The cron / scheduler isn't running

**Symptom:** invoices aren't generated, reminders don't go out, suspensions
never happen, provisioning isn't retried.

1. Confirm the cron line exists for the web user (`crontab -e`):

    ```
    * * * * * cd /path/to/pnlcs && php artisan schedule:run >> /dev/null 2>&1
    ```

2. On Docker, the scheduler runs inside the container automatically — check the
   container is up.
3. Test it by hand: `php artisan schedule:run` should list tasks it ran.

## A customer paid by bank transfer but the invoice is still unpaid

Bank-transfer payments are **manual**. The customer submits a payment
notification; you approve it under **Billing → Payment Notifications**, which
marks the invoice paid and provisions the service. See
[Payment Gateways → Bank Transfer](../guides/payment-gateways.md#bank-transfer).

## I forgot the admin password

Reset it from the server with:

```bash
php artisan tinker
>>> $a = App\Models\Admin::where('username','admin')->first();
>>> $a->password = Hash::make('a-new-strong-password'); $a->save();
```

(Adjust the username if yours differs.)

## The customer portal shows a 500 error

1. Set `APP_DEBUG=true` temporarily in `.env` to see the real error, then
   **turn it off again** — never leave debug on in production.
2. Check `storage/logs/laravel.log`.
3. Make sure migrations ran: `php artisan migrate --force`.
4. Clear caches: `php artisan optimize:clear`.

## Still stuck?

- Search or ask on the [community forum](https://forum.panelica.com)
- Open a [GitHub issue](https://github.com/Panelica/pnlcs/issues) with the exact
  error from `storage/logs/laravel.log` and steps to reproduce
