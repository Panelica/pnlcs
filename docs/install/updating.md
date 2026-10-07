# Updating

PNLCS is updated from **signed releases**, never from the code under
development. An update keeps everything that belongs to your installation,
stops and asks when one of your changes clashes with the new version, and puts
everything back by itself if anything goes wrong.

## Releases and channels

| Channel | You get |
|---|---|
| **Stable** (default) | tested releases only |
| **Beta** | new releases about a week earlier, for operators who want them first |

Choose the channel on **Setup → Updates**. PNLCS looks for a newer release once
a day and tells you (and, if you set it up, notifies you through
**Setup → Notification Channels**: `update.available`). Nothing is installed
until an administrator asks for it. How releases are made and tested:
[RELEASING.md](https://github.com/Panelica/pnlcs/blob/main/RELEASING.md).

## What an update never touches

- `.env`, `storage/` (uploads, invoices, backups, logs) and the database's data
- a theme under its own name, a module or addon under its own name, your own
  hook files in `app/Hooks/`
- email templates you edited, and translations you entered in the admin area

## Updating from the admin area

1. **Setup → Updates → Check this update.** The release is downloaded, its
   signature is verified, and it is compared with your installation. Nothing
   is changed. You see what will be updated, which of your changes are merged
   with the new version or kept as they are, and anything that stands in the way.
2. **Conflicts.** If you changed a file that the new version changes in the
   same place, the update does not start. For each such file choose: use the
   new version (yours is kept in `storage/app/pnlcs-update/set-aside`), keep
   yours, or edit the merged text - right on the page, or download it, edit it
   and upload the result. **Save and update** saves your choices and starts
   the update; a text that still has conflict markers is not accepted.
3. **Update now.** The site goes into maintenance for the few seconds it takes,
   a snapshot of the database is taken, the files are written, migrations run,
   and the new version is checked (database, migrations, the home page and both
   login pages with your theme). If any step fails, every file and the database
   are put back as they were and the site comes back on the version it had.

The work starts right after the click, in a process of its own, and the page
shows each step as it happens. Where the server does not let PHP start one
(SELinux enforcing, some hosting accounts) the scheduler
(`php artisan schedule:run`, the cron line from installation) starts it within
a minute. While the site is in maintenance you keep the admin area; visitors
see the maintenance page.

## Updating from the command line

Run as the web server user (the owner of the PNLCS files):

```bash
php artisan pnlcs:update --check                         # compare, change nothing
php artisan pnlcs:update                                 # update to the newest release on your channel
php artisan pnlcs:update 1.4.2                           # a specific release
php artisan pnlcs:update --resolve public/robots.txt=mine   # a conflict decision: mine or new
```

If an update was cut off (the process was killed, the server lost power), the
site stays in maintenance and no other update runs. Within a minute the
scheduler notices that the update's process is gone and puts every file and
the database back, then brings the site up on the version it had (in Docker,
the container does it when it starts). To do it at once:

```bash
php artisan pnlcs:update-rollback
```

If putting everything back fails too (a full disk, a database that is down),
the site stays in maintenance - a half-restored site is never served - and it
is tried again after 5, 10, 20 and 40 minutes, then every hour. You are
notified once. Everything needed is in `storage/app/pnlcs-update/runs/`.

## Docker

```bash
docker exec pnlcs /usr/local/bin/update.sh            # update
docker exec pnlcs /usr/local/bin/update.sh --check    # compare only
```

`update.sh` runs the same updater, then reloads PHP. A container restarted in
the middle of an update rolls it back by itself before it starts serving.
Images before **1.5** reset the code to the development branch on every update
and discarded changes made inside the container; pull
`panelica/pnlcs-runtime:1.5` and recreate the container with the same volumes
([Docker](docker.md)) before you update.

## Installations made before releases (git clone)

An installation cloned with git and never updated by the updater is compared
with the commit it was cloned at, so your changes to it are kept just the same.
From that update on it is a release installation; keep using the updater, not
`git pull`. The `.git` folder is left where it is.

An installation that has neither a `.pnlcs-release.json` file nor a `.git`
folder (copied from a ZIP download) has no record of the files it was
installed with, so the updater cannot tell your changes from the release's and
does not start. Make it a git installation of the version you downloaded,
without changing a file - only `.git` is added:

```bash
git init && git remote add origin https://github.com/Panelica/pnlcs.git
git fetch --tags origin && git reset v1.3.0      # the version you downloaded
git status                                       # your changes
```

`git status` also lists `docs/`, `tests/` and a few other folders as deleted:
downloads and release packages leave them out, and the updater leaves them out
too.

If your installation is older than the first release with the updater (1.3),
bring it to that release once by hand with the steps below, checking out the
release tag instead of `main`:

```bash
git fetch --tags && git checkout v1.3.0
```

## Updating by hand (before 1.3)

### Self-hosted (without Docker)

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

**2. Check out the release.**
```bash
pn git fetch --tags && pn git checkout v1.3.0
```

**3. Update PHP dependencies.** (AlmaLinux/Rocky: `pn /usr/local/bin/composer …`)
```bash
pn composer install --no-dev --optimize-autoloader --no-interaction
```

**4. Apply new database migrations, then the upgrades of updated addons.**
```bash
pn php artisan migrate --force
pn php artisan pnlcs:addons-upgrade                   # runs upgrade() of addons whose files are newer
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

### Inside a hosting-panel account (Panelica, cPanel, …)

The same in-place update, but with the account's own tools instead of root —
no `sudo`, no `systemctl`. Run everything from the project directory with the
panel's PHP binary (on Panelica that is `php84`):

```bash
cd ~/example.com/pnlcs
php84 artisan down                                        # maintenance page
git fetch --tags && git checkout v1.3.0
php84 /usr/local/bin/composer install --no-dev --optimize-autoloader
php84 artisan migrate --force                             # applies new migrations
php84 artisan pnlcs:addons-upgrade                        # upgrades of updated addons
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

