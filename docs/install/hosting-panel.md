# Install inside a hosting-panel account

## Without a command line: upload the release package

A panel's file manager is enough:

1. Create the domain and set its **PHP version to 8.4**. Create a MySQL
   database and a user for it in the panel.
2. Download `pnlcs-X.Y.Z.tar.gz` from the
   [latest release](https://github.com/Panelica/pnlcs/releases/latest). It
   carries everything PNLCS needs to run, `vendor/` and the built assets: no
   Composer and no Node.
3. Upload it **next to** the webroot (`~/example.com/`, not inside
   `public_html`) and extract it there: you get a `pnlcs` folder.
4. Point the domain's document root at `pnlcs/public`, in the panel's domain
   settings or with the same-owner symlink from the command-line steps below.
5. Open `https://example.com/`: the install wizard starts by itself. It
   writes `.env`, asks for the database, creates the tables with a progress
   bar (a minute or two), then your administrator account and the site
   settings.
6. The wizard's last page shows one cron line: add it as a cron job for the
   account.

The `pnlcs` folder must be writable by the user the site's PHP runs as; on
panels that run PHP as the account user, an upload already is.

## With a command line

If the server runs a control panel, you do not need root or any of step 0 -
the panel already carries PHP, MySQL and (on Panelica) Node. This is the
exact shape our own production installs use:

1. Create the hosting account and its domain in the panel, and set the
   domain's **PHP version to 8.4** - this is the step that bites: if the
   site's PHP-FPM stays at 8.3 while you ran composer with a 8.4 CLI, every
   request answers `500 - Composer detected issues in your platform`.
2. Create the MySQL database and user from the panel. Panels prefix names
   (`account_pnlcs`) - put the prefixed name in `.env`.
3. As the account user, clone into the site directory - **next to** the
   webroot, not inside it:
   `cd ~/example.com && git clone https://github.com/Panelica/pnlcs.git pnlcs`
4. `composer install`, `.env`, `key:generate`, `migrate --force` as in steps
   2-6 of the [server installation](native.md), using the panel's PHP 8.4 binary (on Panelica: `php84`). If MySQL
   listens on a socket, add `DB_SOCKET=` with the panel's socket path.
5. Build the assets with the panel's Node (on Panelica, install one under
   Node.js Versions and use its `npm`).
6. Point the webroot at `pnlcs/public` with a same-owner symlink:
   `mv public_html public_html.default && ln -s pnlcs/public public_html`.
   A symlink owned by the same account passes the panel's
   `disable_symlinks if_not_owner` protection.
7. Add the cron line from [step 13](native.md#13-schedule-the-cron-runner) as a panel cron job for the account.
8. Open `https://example.com/install` and finish the wizard.
