# Common Issues

## Installing and updating

### `500 — Composer detected issues in your platform: PHP >= 8.4.0`

The site's PHP-FPM is older than the PHP that ran `composer install`. Because
the dependencies are locked against PHP 8.4 the page dies before Laravel even
boots, so `storage/logs` stays empty. Point the site (or FPM pool) at **PHP
8.4** — on a control panel, change the domain's PHP version; on raw nginx, fix
the `fastcgi_pass` socket.

### `Application encryption key has not been specified` / `MissingAppKeyException`

You skipped the key step. Run `php artisan key:generate` (it writes `APP_KEY`
into `.env`), then reload.

### `SQLSTATE… Base table or view not found`

Migrations have not run, or `.env` points at the wrong database. Check the
`DB_*` values, make sure the database exists, then run
`php artisan migrate --force`.

### Composer stops with `… does not exist and could not be created` (writing to `vendor/`)

Something under `vendor/` is owned by another user (usually a past
`sudo composer`), so the site user cannot write there. Fix ownership and retry:
`chown -R <site-user>:<site-user> vendor && composer install --no-dev`.

### `fatal: detected dubious ownership in repository`

Git refuses a repo owned by a different user. Mark it safe:
`git config --global --add safe.directory /path/to/pnlcs`.

### Blank or unstyled page, or the old UI after an update

The compiled assets or cached views are stale. Run `npm run build`, then
`php artisan optimize` (or `php artisan view:clear`).

### `.env` is reachable in the browser

The web root points at the project folder instead of `public/`. Point the
document root at `pnlcs/public` — nothing above it should be web-served.

### Invoices/emails never send, but nothing errors

Three causes, in order of likelihood:
1. **Mail is not configured.** Until SMTP is set under **Setup → General
   Settings → Mail Configuration**, `MAIL_MAILER=log` is in effect and nothing
   leaves the server.
2. **The cron line is missing.** With `QUEUE_CONNECTION=database`, queued mail
   is sent by the scheduler ([installation step 13](../install/native.md#13-schedule-the-cron-runner)), which starts a worker
   every minute. No cron, no worker: jobs stay in the `jobs` table. Check with
   `crontab -u www-data -l`.
3. **Mail is switched off** in the panel settings — the log then says
   `Outgoing mail suppressed: mail is disabled in the panel settings.`

### The last step of the install wizard answers 500

`storage/logs/laravel-*.log` says `file_put_contents(/var/www/pnlcs/.env):
Failed to open stream: Permission denied`. The wizard writes `.env`, and the
file belongs to `root`. Give it to the web user and reload the page:
`sudo chown www-data:www-data .env && sudo chmod 640 .env` (AlmaLinux/Rocky:
`apache:apache`). Current versions of the wizard show this on the requirements
page (".env ✗ not writable") before you start.

### AlmaLinux / Rocky: every page is a 500, and `storage/logs` is empty

SELinux is blocking PHP-FPM. `sudo grep denied /var/log/audit/audit.log | tail`
shows `{ write } … name="logs"` (cannot write `storage/`) and
`{ name_connect } … dest=3306` (cannot reach the database). Run the commands
in [installation step 10](../install/native.md#10-selinux-almalinux-rocky-only).

### AlmaLinux / Rocky: `sudo: composer: command not found`

`sudo` there does not search `/usr/local/bin`. Call Composer by its full path:
`sudo -u apache /usr/local/bin/composer install --no-dev --optimize-autoloader`.

### `git pull` says `detected dubious ownership`, or `npm` fails with `EACCES`

Part of the tree belongs to `root` while the command runs as the web user
(usually an older install that ran `sudo git clone` / `sudo npm install` and
only handed `storage/` over). Give the whole directory to the web user once:
`sudo chown -R www-data:www-data /var/www/pnlcs`, then re-run.

### The server's IP address shows "Welcome to nginx!" instead of PNLCS

The distribution's default site is still enabled and answers every request
that does not match your `server_name`. Remove it:
`sudo rm /etc/nginx/sites-enabled/default && sudo systemctl reload nginx`.

### `apt` installs no PHP at all on Debian 13 (`Unable to locate package php8.4-imap`)

Debian 13 does not package the `imap` extension; one missing package makes
`apt` refuse the whole line. Install the list without `php8.4-imap` — see
[installation step 0](../install/native.md#0-prepare-the-server).

### Mailbox import does nothing, and the log says `the PHP imap extension is not installed`

Ticket import from a mailbox (**Setup → Ticket Departments**) needs PHP's
`imap` extension, which PHP 8.4 no longer bundles. Install `php8.4-imap`
(Ubuntu, ondrej PPA) or `php-imap` (AlmaLinux/Rocky, Remi) and reload PHP-FPM.
The rest of PNLCS does not need it.

### A page shows `Route [...] not defined` right after an update

PHP-FPM is still serving the previous code from its opcode cache. Reload it:
`sudo systemctl reload php8.4-fpm` (AlmaLinux/Rocky: `php-fpm`).

### The `/install` wizard is already locked, or you never got to choose a password

You ran `php artisan db:seed` by hand before opening the wizard; seeing an
administrator, it locks itself. Sign in with `admin` / `admin123` and change
the password from your profile, or reinstall without seeding by hand.

## Running

### Emails are not being sent

Customers get no invoices, welcome emails or ticket replies.

1. **Is sending configured?** **Setup → General Settings → Mail
   Configuration**. Until a mail type is chosen, `.env` decides, and a fresh
   `.env` writes mail to the log instead of sending it
   ([Configure Email](../guides/configure-email.md)).
2. **Is sending switched on?** With **Enable outgoing emails** off, the log
   says `Outgoing mail suppressed: mail is disabled in the panel settings.`
3. **Press Send Test Email** on the same screen and read the error it shows.
4. **Is the scheduler running?** With `QUEUE_CONNECTION=database` mail waits in
   the queue until the scheduler's worker sends it (below).
5. **Read the log**: `storage/logs/laravel-YYYY-MM-DD.log`.
6. **In the spam folder?** Set up SPF, DKIM and DMARC for the sending domain,
   or use a transactional mail service.

### Provisioning did not happen

An invoice is paid, but no hosting account was created: the service stays
*Pending*.

1. **Does the product have a server module and a server?** A product without
   one never provisions: set them on the product.
2. **What is its auto-setup?** With *manual*, you accept each order yourself
   (**Orders → Pending**). With *on payment*, paying starts it.
3. **Does the server answer?** Press **Test** next to it on **Setup →
   Servers**. Wrong credentials or a firewall on the panel port are the usual
   causes.
4. **Did the server refuse?** A refused action is retried every five minutes
   with growing delays, and you are notified. The API lists what is waiting,
   with the last error: [getmodulequeue](../api/system.md#getmodulequeue).
5. **Is the scheduler running?** Retries only happen when it runs.

### Nothing happens by itself

Invoices are not raised, reminders do not go out, services are never suspended,
provisioning is not retried: the scheduler is not running.

1. The cron line must exist for the **web server user**:
   `sudo crontab -u www-data -l` (AlmaLinux/Rocky: `-u apache`).

    ```
    * * * * * cd /var/www/pnlcs && php artisan schedule:run >> /dev/null 2>&1
    ```

2. **Utilities → Automation Status** shows when each job last ran.
3. Run it by hand: `php artisan schedule:run`.
4. On Docker the scheduler runs inside the container: check the container is
   up.

### A customer paid by bank transfer but the invoice is unpaid

Bank transfers are confirmed by hand: approve the customer's payment
notification under **Billing → Payment Notifications**
([Bank Transfer](../guides/payment-gateways.md#bank-transfer)).

### A domain stayed pending after it was paid for

Its registrar module could not be loaded (switched off on **Setup → Modules**,
or not installed), so nothing was sent to the registry; you were notified.
Switch the registrar on and register the domain from its page in the admin
area, or set the right registrar on the domain.

### I forgot the admin password

There is no reset email for staff accounts. Set a new password from the
server, in the PNLCS directory:

```bash
php artisan tinker
>>> $a = App\Models\Admin::where('username', 'your-username')->first();
>>> $a->password = 'a-new-strong-password'; $a->save();
```

The password is hashed when it is saved.

### A page shows a 500 error

1. Read the newest file in `storage/logs/`.
2. Make sure the migrations ran: `php artisan migrate --force`.
3. Clear the caches: `php artisan optimize:clear`, then `php artisan optimize`.
4. Only if the log says nothing, set `APP_DEBUG=true` in `.env` for a moment to
   see the error, and set it back to `false` straight away.

## Still stuck?

- Search or ask on the [community forum](https://forum.panelica.com).
- Open a [GitHub issue](https://github.com/Panelica/pnlcs/issues) with the
  exact error from `storage/logs/` and the steps to reproduce it.
- See also the [FAQ](faq.md).
