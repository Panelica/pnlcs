# Updating

PNLCS updates in place — latest code, database migrations, and rebuilt frontend
assets — without touching your data.

## Docker

One command pulls the latest release into a running container:

```bash
docker exec pnlcs /usr/local/bin/update.sh
```

It runs `git reset --hard origin/main` → `composer install` → `php artisan migrate`
→ `npm run build` → cache rebuild → php-fpm reload. Your database and uploaded
files live on the `pnlcs_app` volume and are left untouched.

Set `AUTO_UPDATE=1` on the container to pull the latest code automatically on
every restart.

### `500 — Composer detected issues in your platform: PHP >= 8.4.0`

The web server's PHP-FPM is older than the PHP that ran `composer install`.
The dependencies are locked against PHP 8.4, so the page dies before Laravel
even boots - and because it dies that early, `storage/logs` stays empty.
Point the site (or pool) at PHP 8.4: on a panel, change the domain's PHP
version; on raw nginx, fix the `fastcgi_pass` socket.

### `fatal: detected dubious ownership in repository`

If `update.sh` stops with:

```
fatal: detected dubious ownership in repository at '/var/www/pnlcs'
```

Git is refusing to run because the code directory is owned by a different user
than the one running the update (a normal effect of the `pnlcs_app` volume).
Mark the directory as trusted once — the exception is permanent, so later
updates run cleanly:

```bash
docker exec pnlcs git config --global --add safe.directory /var/www/pnlcs
docker exec pnlcs /usr/local/bin/update.sh
```

Already inside the container shell (`/var/www/pnlcs #`)? Run it without
`docker exec`:

```bash
git config --global --add safe.directory /var/www/pnlcs
/usr/local/bin/update.sh
```

> The update resets the working tree to `origin/main`, so any manual edits made
> **inside** the container are discarded — all code is served from this
> repository. Keep customisations in your own fork or theme, not in the running
> container.

## Self-hosted (without Docker)

If you installed PNLCS directly on a server (see
[Install on your own server](native.md)),
you update it **in place** — new code, migrations and rebuilt assets, your data
untouched. Run everything from the PNLCS directory **as the web server user**,
with the same `pn` helper as the installation:

```bash
cd /var/www/pnlcs
pn() { sudo -u www-data HOME=/tmp/pnlcs-home COMPOSER_HOME=/tmp/pnlcs-home/composer "$@"; }   # AlmaLinux/Rocky: -u apache
```

**1. Back up and pause the app.**
```bash
pn php artisan down                                   # maintenance page for visitors
pn php artisan pnlcs:db-backup                        # database snapshot → storage/app/backups/db/
```

**2. Pull the latest code.**
```bash
pn git pull origin main
```

**3. Update PHP dependencies.** (AlmaLinux/Rocky: `pn /usr/local/bin/composer …`)
```bash
pn composer install --no-dev --optimize-autoloader --no-interaction
```

**4. Apply new database migrations.**
```bash
pn php artisan migrate --force
```

**5. Rebuild the frontend assets.**
```bash
pn npm ci
pn npm run build
```

**6. Rebuild the cached config, routes and views.**
```bash
pn php artisan optimize
```

**7. Reload PHP so the new code goes live.**
```bash
sudo systemctl reload php8.4-fpm      # AlmaLinux/Rocky: sudo systemctl reload php-fpm
```
Do not skip this one. PHP's opcode cache can keep serving the previous code
for a while after the files change; on one of our own installs that showed up
as `Route [...] not defined` and a 500 on the dashboard until PHP-FPM was
reloaded.

**8. Bring the app back up.**
```bash
pn php artisan up
```

If you run a permanent queue worker ([installation step 14](native.md#14-queue-no-separate-worker-needed)), restart it too:
`pn php artisan queue:restart`.

We ran this exact sequence on a Debian 13 install: maintenance mode on,
pull, composer, migrations, `npm ci`, build, cache, reload, back live — no
errors, site answering 200 afterwards.

**Errors here almost always mean the tree is not owned by the web user.**
`fatal: detected dubious ownership in repository` from `git`, or `EACCES` from
`npm`, means some files belong to `root` — typically from an older
installation that ran commands with plain `sudo`. Fix it once and re-run:

```bash
sudo chown -R www-data:www-data /var/www/pnlcs     # AlmaLinux/Rocky: apache:apache
```

## Inside a hosting-panel account (Panelica, cPanel, …)

The same in-place update, but with the account's own tools instead of root —
no `sudo`, no `systemctl`. Run everything from the project directory with the
panel's PHP binary (on Panelica that is `php84`):

```bash
cd ~/example.com/pnlcs
php84 artisan down                                        # maintenance page
git pull origin main
php84 /usr/local/bin/composer install --no-dev --optimize-autoloader
php84 artisan migrate --force                             # applies new migrations
npm ci && npm run build                                   # the account's Node
php84 artisan optimize                                    # rebuild cached config/routes/views
php84 artisan up
```

There is no PHP-reload step you run yourself: FPM picks the new code up on the
next request, or you restart PHP for the domain from the panel. If MySQL is on
a socket, the `DB_SOCKET` line from installation stays in `.env` and needs
nothing here. Your data, uploads and settings are untouched.

!!! tip "Always back up first"
    Every procedure above changes the database. Take a backup before you start
    ([Backups](backups.md)); the self-hosted procedure does it in its first step.

