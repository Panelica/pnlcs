# Deploy on Plesk

PNLCS runs well on a Plesk server, either through Plesk's **Laravel Toolkit**
extension (recommended — handles most of the build steps for you) or fully
manually over SSH. This guide covers both paths and the gotchas that are easy
to miss on Plesk specifically.

## Prerequisites

- A Plesk subscription/domain already created for the app (e.g.
  `app.example.com`)
- PHP **8.4 required** in Plesk (check **Domain → PHP Settings**) — see note
  below
- MySQL 8 (or MariaDB 10.11+)
- Root or SSH access to the server for the manual steps below

!!! warning "Use PHP 8.4, not 8.3"
    PNLCS's `composer.json` states `^8.3`, but the shipped `composer.lock`
    resolves to package versions (Symfony 8.x in particular) that require PHP
    `>=8.4`. `composer install` will fail on PHP 8.3 with errors like
    `symfony/xyz requires php >=8.4`. Set the domain's PHP version to **8.4**
    in Plesk before installing — this avoids the issue entirely. If 8.4 isn't
    available on your server, the alternative is running
    `composer update --no-dev --optimize-autoloader` once to re-resolve an
    8.3-compatible lock file, but starting on 8.4 is simpler and is what this
    guide assumes from here on.

## Option A — Using Plesk's Laravel Toolkit (recommended)

1. Install the **Laravel Toolkit** extension in Plesk if it isn't already.
2. Go to **Domains → your domain → Laravel Toolkit**.
3. Point it at your repository (HTTPS Git URL) and branch.
4. Set **Deployment mode** to **Manual** (safer for a first install — you
   control exactly when it deploys) or **Automatic** if you want it to
   redeploy on every push.
5. Under **Deployment steps**, leave the defaults checked: fetch source,
   install Composer dependencies, install npm dependencies, disable
   maintenance mode. The "Run deployment script" step is only needed if you
   add a custom `.deploy` script later.
6. Click **Deploy**.
7. Once deployed, edit the app's **environment variables (.env)** from the
   toolkit's dashboard tab (see [Environment](#environment) below).
8. Run the [Artisan commands](#artisan-commands) from the toolkit's **Artisan**
   tab — no SSH needed for this part.
9. Enable the **Scheduled Tasks** and **Queue** toggles on the toolkit
   dashboard. This replaces the manual cron/systemd steps in Option B
   entirely — Plesk manages the worker process and the `schedule:run` cron
   entry for you.

!!! note "Node.js version"
    PNLCS pins a Node version in `.node-version` (check the file — it may be
    ahead of what your distro's package manager ships, e.g. Node 26). The
    Laravel Toolkit's own **Node.js** tab lets you pick a Node version for the
    *build step* independently of what's installed system-wide, so this
    usually isn't a manual concern under Option A.

## Option B — Manual deployment over SSH

1. **Clone outside the web root**, then point the document root at `public/`
   rather than the repo root — Laravel apps must never serve their own
   project root:

   ```bash
   cd /var/www/vhosts/example.com
   git clone https://github.com/Panelica/pnlcs.git pnlcs
   ```

   In Plesk: **Domain → Hosting Settings → Document Root** → `pnlcs/public`.

2. **Install PHP extensions.** PNLCS needs: `pdo_mysql, mbstring, bcmath,
   ctype, fileinfo, json, openssl, tokenizer, xml, curl, gd, zip, intl`. On
   Plesk/Debian-based systems these come as `plesk-php84-<extension>`
   packages — check what's active first:

   ```bash
   /opt/plesk/php/8.4/bin/php -m | sort
   ```

3. **Install Composer dependencies** using the Plesk PHP binary explicitly,
   so you don't accidentally use a different system PHP:

   ```bash
   /opt/plesk/php/8.4/bin/php $(which composer) install --no-dev --optimize-autoloader
   ```

4. **Install Node and build assets.** If the version in `.node-version` isn't
   available via your distro's package manager (common for very recent
   versions), use [nvm](https://github.com/nvm-sh/nvm) rather than fighting
   distro packages:

   ```bash
   curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
   export NVM_DIR="$HOME/.nvm"
   [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
   nvm install "$(cat .node-version)"
   npm ci
   npm run build
   ```

5. **Configure `.env`** — see [Environment](#environment) below.

6. **Run the Artisan commands** — see [below](#artisan-commands).

7. **Fix ownership/permissions.** Anything run as `root` during setup (e.g. an
   interactive `su` session that didn't fully take, or a manual `chown` that
   Plesk's ACL model disagrees with) can leave files owned incorrectly for
   the subscription's system user. Rather than hand-crafting `chown`/`chmod`,
   let Plesk reconcile it against its own conventions:

   ```bash
   plesk repair fs example.com -y
   ```

8. **Queue worker.** Plesk doesn't run long-lived processes on its own —
   create a systemd unit:

   ```ini
   # /etc/systemd/system/pnlcs-queue.service
   [Unit]
   Description=PNLCS Queue Worker
   After=network.target mysql.service

   [Service]
   User=your_subscription_system_user
   WorkingDirectory=/var/www/vhosts/example.com/pnlcs
   ExecStart=/opt/plesk/php/8.4/bin/php artisan queue:work --tries=3 --timeout=90
   Restart=always

   [Install]
   WantedBy=multi-user.target
   ```

   ```bash
   systemctl enable --now pnlcs-queue
   ```

9. **Scheduler cron.** Add to the subscription's crontab (Plesk → Scheduled
   Tasks, or `crontab -u your_subscription_system_user -e`):

   ```
   * * * * * php /var/www/vhosts/example.com/pnlcs/artisan schedule:run >> /dev/null 2>&1
   ```

## Environment

Set at minimum:

```
APP_NAME=PNLCS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

QUEUE_CONNECTION=database
```

Leave `APP_KEY` blank if you haven't generated one yet — the Artisan step
below handles it. If you're deploying through the Laravel Toolkit, it may
already have generated a key for you during the first deploy; check before
overwriting it.

## Artisan commands

Run these once, in order, after dependencies are installed and `.env` is set:

```bash
php artisan config:clear
php artisan key:generate   # skip if APP_KEY is already set
php artisan migrate --force --seed
php artisan storage:link
```

`migrate --seed` creates the default administrator account
(`admin` / `admin123`). **Log in and change this immediately** — see
[First Login](../getting-started/first-login.md).

## Verify

1. Visit `https://app.example.com/admin/login` and confirm the login page
   loads (not a 500 or blank page — if so, check
   `storage/logs/laravel.log`).
2. Confirm SSL is active (Plesk → SSL/TLS Certificates → Let's Encrypt) with
   HTTP→HTTPS redirect enabled.
3. Confirm the queue worker and scheduler are actually running — see
   [Scheduled Commands](../reference/scheduled-commands.md).
4. Continue with the [Setup Checklist](../getting-started/setup-checklist.md).
