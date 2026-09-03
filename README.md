<p align="center">
  <a href="https://pnlcs.com/"><b>pnlcs.com</b></a>
</p>

<h1 align="center">PNLCS</h1>

<p align="center">
  <b>Open-source, self-hosted hosting billing platform — a free WHMCS alternative.</b><br>
  Client portal · invoicing · domain &amp; SSL management · support tickets · reseller hosting.<br>
  <b>Customers manage their hosting from the billing portal itself</b> — files, mail,
  databases, FTP, subdomains, DNS, cron and backups.
</p>

<p align="center">
  Built with <b>Laravel 13</b> · <b>PHP 8.4+</b> · <b>MySQL 8</b> · <b>Alpine.js</b> · <b>Tailwind CSS 4</b>
</p>

<p align="center">
  <a href="https://github.com/Panelica/pnlcs/blob/main/LICENSE"><img src="https://img.shields.io/github/license/Panelica/pnlcs?color=blue" alt="MIT License"></a>
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.4+">
  <img src="https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql&logoColor=white" alt="MySQL 8.0+">
  <a href="https://github.com/Panelica/pnlcs/stargazers"><img src="https://img.shields.io/github/stars/Panelica/pnlcs?style=social" alt="Stars"></a>
</p>

<p align="center">
  <a href="https://pnlcs.com/"><img src="https://img.shields.io/badge/%F0%9F%8C%90%20Website-pnlcs.com-4051A9?style=for-the-badge&logoColor=white" alt="Website — pnlcs.com"></a>
  <a href="https://hosting.panelica.com/"><img src="https://img.shields.io/badge/%F0%9F%9A%80%20Live%20Demo-hosting.panelica.com-22C55E?style=for-the-badge&logoColor=white" alt="Live Demo — hosting.panelica.com"></a>
</p>

<p align="center">
  <b>👉 Try the live demo: <a href="https://hosting.panelica.com/">hosting.panelica.com</a></b>
</p>

<p align="center">
  <a href="https://pnlcs.com/"><b>Website</b></a> ·
  <a href="https://hosting.panelica.com/"><b>Live Demo</b></a> ·
  <a href="https://panelica.github.io/pnlcs/">Documentation</a> ·
  <a href="#quick-start-with-docker">Docker</a> ·
  <a href="#installation">Installation</a> ·
  <a href="#first-steps-after-installation">First Steps</a> ·
  <a href="#screenshots">Screenshots</a> ·
  <a href="#features">Features</a> ·
  <a href="#modules">Modules</a> ·
  <a href="#contributing">Contributing</a>
</p>

<p align="center">
  <a href="https://hub.docker.com/r/panelica/pnlcs-runtime"><img src="https://img.shields.io/docker/pulls/panelica/pnlcs-runtime?logo=docker&label=Docker%20Pulls" alt="Docker Pulls"></a>
  <a href="https://hub.docker.com/r/panelica/pnlcs-runtime"><img src="https://img.shields.io/badge/Docker%20Hub-panelica%2Fpnlcs--runtime-2496ED?logo=docker&logoColor=white" alt="Docker Hub"></a>
</p>

---

## About — Open-Source WHMCS Alternative

**PNLCS** is a free, open-source, self-hosted **hosting billing platform**
and **client portal** — an open alternative to WHMCS for web hosting
companies, reseller hosts, and infrastructure providers. It covers the full
customer lifecycle: product catalog, checkout, recurring invoicing, domain
registration, SSL certificate management, support tickets, knowledge base,
and affiliate tracking.

If you have used WHMCS, you will feel at home: the data model, workflows,
and module ecosystem (servers, gateways, registrars, SSL providers) are
deliberately familiar. The difference is that PNLCS is **MIT-licensed**,
**self-hosted**, and free to fork, study, and extend.

This project is built and maintained by the **Panelica Server Management
Panel** team in our spare time, alongside our main product. We wanted an
open, self-hosted billing system that integrates natively with Panelica and
plays nicely with other control panels too — so we built one.

**What works well today:**

- Client portal, admin panel, and core billing flows
- Invoicing, orders, services, domains, tickets, knowledge base
- The **Panelica server module** (fully tested against live servers)
- Stripe payment gateway (tested in production)
- Multi-language UI (30 locales, admin-editable translations)

**What needs your help:**

- **cPanel / Plesk / DirectAdmin / Proxmox server modules** — code is in
  place but we have not tested them end-to-end. If you run one of these,
  please try it out and open an issue (or even better, a pull request)
  telling us what you found.
- **PayPal / Authorize.Net / Bank Transfer gateways** — same story.
- **Enom domain registrar** — API integration exists, needs real-world
  verification.
- General bug reports, typos, translation improvements.

We read every issue, but because this is a side project our response time
isn't always same-day. If you can include reproduction steps or a patch,
it helps us enormously.

---

## Built for Panelica — The Modern Hosting Control Panel

PNLCS is developed and maintained by the team behind
**[Panelica](https://panelica.com)**, a modern server management panel for web
hosting — a fresh alternative to cPanel, Plesk, and CyberPanel.

**Why Panelica?**

- **No CloudLinux required.** Per-user isolation — CPU, RAM, I/O, and process
  limits — is built in natively via cgroups v2, Linux namespaces, per-user
  PHP-FPM pools, and SSH chroot. You get CageFS/LVE-style tenant isolation
  without paying for a separate CloudLinux license.
- **Universal migration.** Move whole accounts in from cPanel, Plesk,
  DirectAdmin, and CyberPanel — sites, databases, emails, DNS, and SSL — with
  file and database hashes preserved, so passwords and configs keep working.
- **Affordable.** A modern, fully isolated hosting stack at a fraction of the
  typical cPanel + CloudLinux bill.
- **All-in-one, isolated stack.** Nginx, Apache, multiple PHP versions,
  PostgreSQL, MySQL, Redis, BIND, mail (Postfix/Dovecot), FTP, ClamAV,
  fail2ban, and ModSecurity — each service isolated and managed from one panel.

PNLCS integrates natively with Panelica through the built-in **Panelica server
module**: sell hosting plans and accounts are provisioned on your Panelica
servers automatically — and the customer then manages that hosting (files,
mailboxes, databases, FTP, subdomains, DNS, cron, backups) **from the billing
portal itself**, with every action fenced to their own account. See
[Hosting Management from Inside Billing](#hosting-management-from-inside-billing).

**Not only Panelica.** PNLCS is control-panel agnostic — you can connect and
provision on **cPanel, Plesk, DirectAdmin, HestiaCP, Proxmox, and Vultr** too,
right alongside your Panelica servers. Mix and match panels in a single install;
Panelica is simply where PNLCS feels most at home.

👉 **Learn more at [panelica.com](https://panelica.com)**

---

## Screenshots — Admin Panel & Client Portal

### Admin Panel

![Admin Dashboard](docs/screenshots/admin-dashboard.png)
*Admin dashboard with revenue, orders, and ticket overview*

![Language Manager](docs/screenshots/admin-languages.png)
*Built-in translation editor — 30 locales, 2,232 translation keys, AI-assisted bulk translate*

![Appearance & Themes](docs/screenshots/admin-appearance.png)
*WordPress-style theme system with 15 built-in themes, logo/favicon upload, dark mode toggle, and homepage builder*

![Servers Configuration](docs/screenshots/admin-servers.png)
*Server module management — connect Panelica, cPanel, Plesk, DirectAdmin, Proxmox, or custom servers*

![Support Tickets](docs/screenshots/admin-tickets.png)
*Ticket system with departments, priority routing, internal notes, and escalation rules*

### Client-Facing Site

![Homepage (default theme)](docs/screenshots/homepage-default.png)
*Customer-facing landing page with domain search, hosting plans, VPS servers, and FAQ — fully configurable*

![Homepage (Coral theme)](docs/screenshots/homepage-coral-theme.png)
*Same site with a different built-in theme applied — one click to switch*

![One-click apps on the homepage](docs/screenshots/homepage-apps.png)
*The app showcase: 98 applications a customer can install into their hosting,
with the logos shipped in the repository. Heading, copy, button and how many
apps to show are all editable from the admin Homepage screen*

### Client Portal — Hosting Management

![Hosting management tools](docs/screenshots/client-hosting-tools.png)
*A customer's hosting service: live resource usage from the server and eight
working tools — files, mail, databases, FTP, subdomains, DNS, cron, backups.
[Full detail below](#hosting-management-from-inside-billing)*

![Backups](docs/screenshots/client-backups.png)
*Restore points with size, contents and encryption state — fenced to the
customer's own domains*

![App catalogue](docs/screenshots/client-apps.png)
*Installing an app from the customer's own control panel: searchable, grouped
the way people shop, and every card states the memory the app needs and how
many containers it starts. An app that wants more than the plan allows is
marked before it is chosen, not after it fails*

---

## Hosting Billing Features

### 💼 Client Portal

- **Shop & checkout** — browse plans, configure service, apply coupons, pay
- **Service management** — upgrade, downgrade, cancel, auto-renew toggle
- **Domain management** — register, transfer, renew, EPP code, WHOIS
- **Invoicing** — view, pay online, download PDF, add-funds, credit balance
- **Quotes** — review, accept (auto-converts to invoice) or decline pre-sales quotes
- **Payment methods** — save bank-transfer references, set a default
- **Bank-transfer notifications** — report an offline payment with receipt upload
- **Email history** — read every email the system has sent you
- **Network status** — live view of active incidents and scheduled maintenance
- **Support tickets** — attachments, priority, department routing
- **Knowledge base & announcements** — searchable, categorized
- **SSL certificates** — CSR generation, approver emails, auto-install
- **Affiliate program** — referral tracking, commission payouts
- **Account security** — 2FA (TOTP), login alerts, session history
- **Hosting management** — files, mailboxes, databases, FTP, subdomains,
  cron, DNS and backups, without leaving billing ([details below](#hosting-management-from-inside-billing))

### 🛡️ Admin Panel

- **Dashboard** — revenue, new signups, pending orders, open tickets at a glance
- **Client management** — profiles, impersonation, notes, billing summary
- **Orders & invoices** — manual create, bulk actions, mass mail, PDF export
- **Products & bundles** — configurable options, addons, pricing matrices
- **Ticket system** — internal notes, escalation rules, spam filter
- **Reports** — revenue, conversion funnel, MRR, churn, affiliate stats
- **Bulk operations** — mass email, bulk invoice, bulk service status update
- **Calendar** — events, reminders, scheduled tasks
- **Quotes & projects** — WHMCS-style pre-sales flow

### 🌐 Internationalization

- **30 locales**, English active by default
- **2,232 translation keys** for the core UI
- **In-browser editor** — edit strings without touching files
- **AI-assisted bulk translate** for missing keys
- **Export / import** JSON per locale

### 🎨 Theme System

- **15 built-in themes** (Arctic, Aurora, Coral, Ember, Forest, Midnight,
  Mint, Neon, Ocean, Panelica, Royal, Slate, Starter, Sunset, and more)
- **WordPress-style** install / activate / delete workflow
- **Homepage builder** with reorderable sections
- **Per-site white-label** options (logo, favicon, footer copyright)
- **Dark mode** toggle per theme

### 🔐 Security & Access Control

- **RBAC** with over 45 fine-grained permissions
- **2FA (TOTP)** for both admin and client portals
- **Rate-limited** login, 2FA verify, password reset, email resend
- **IP whitelisting** for admin area (optional)
- **Session timeout** and **force logout** support
- **Activity log** — every admin action recorded
- **Banned IPs & emails** at the application level

### 💳 Billing & Automation

- **Recurring billing** — monthly, quarterly, semi-annually, annually, biennially
- **Auto-suspend** unpaid services after configurable grace period
- **Late fees**, promotions, coupons, tax rules (inclusive/exclusive)
- **Overage billing** — disk / bandwidth metering, opt-in per product
- **Credit balances** & add-funds flow
- **Automated reminders** — invoice, payment, CC expiry, domain renewal
- **Auto-renew** services and domains with billing integration
- **Unified payment engine** — one path for gateways, manual and credit, with partial-payment and overpayment-to-credit handling
- **Reliable provisioning** — a service only activates after the server module succeeds; failures are queued and retried automatically with admin alerts
- **Refunds** — reverse a payment via the gateway API (or offline), full or partial
- **Exchange-rate auto-update**, **automated database backups**, and **log retention** on a schedule

### ⚙️ Developer Features

- **REST API** with API-key auth for external integrations
- **Webhooks** — inbound (gateway callbacks) and outbound (events)
- **Queue workers** for email and background jobs
- **Scheduled commands** — invoice generation, reminders, polling
- **Hook system** — WHMCS-compatible `add_hook()` / `run_hook()` with 20+ hook points; a failing hook can never break billing or provisioning
- **Email piping** — inbound IMAP/POP3 mailboxes turn emails into tickets and replies
- **Modular architecture** — add server / gateway / registrar modules
  without touching core
- **Eloquent everywhere** — no raw SQL, no string concatenation

---

## Hosting Management from Inside Billing

Most billing platforms stop at "here is your control panel password." PNLCS
does the day-to-day hosting work **in the billing portal itself**, so a customer
who wants to add a mailbox or a DNS record never has to learn a second interface.

![Hosting management tools](docs/screenshots/client-hosting-tools.png)
*A hosting service in the client portal — live CPU/memory/disk/bandwidth from the
server, and eight working tools underneath*

Available on services provisioned through the **Panelica server module**
(the module tells the portal which tools that account may use):

| Tool | What the customer can do |
|------|--------------------------|
| **File Manager** | Browse, upload, download, edit, rename, create folders and delete |
| **Email Accounts** | Create and delete mailboxes, change passwords, open webmail |
| **Databases** | Create MySQL databases and users, reset user passwords, open phpMyAdmin |
| **FTP Accounts** | Create accounts, change passwords, delete — with host/port shown |
| **Subdomains** | Create and remove subdomains; the panel provisions the real vhost, document root, PHP-FPM pool, SSL and DNS |
| **DNS Zone** | Add, edit and delete A / AAAA / CNAME / MX / TXT / SRV / CAA records |
| **Cron Jobs** | Schedule commands, run one immediately and read its output, pause/resume, delete |
| **Backups** | Take restore points, see size and contents, delete old ones |

### Everything is fenced to the customer's own account

The server API key a billing platform holds is operator-wide — it can see every
account on the box. That is exactly the mistake this integration does not make:
**every list is filtered against the domains that belong to the service being
viewed**, and every write is checked the same way before it is sent.

- A backup archive that also covers somebody else's domain is not shown, and
  cannot be deleted.
- A cron job, subdomain or DNS record on a foreign domain is rejected before a
  request leaves the billing server.
- Plan limits are read from the customer's hosting plan — `max_subdomains`,
  `max_cron_jobs`, `cron_jobs_enabled`, `backup_enabled` — and the create form
  is gated on them (the panel enforces them again, independently).

### The records that keep a site online stay read-only

![DNS zone editor](docs/screenshots/client-dns-zone.png)
*One zone at a time, records ordered the way an operator reads them, and the
delegation and apex records locked*

A zone editor in a billing panel is a fast way for a customer to take their own
site offline. So the records the hosting itself depends on — `SOA`, `NS`, and the
apex/`www` `A` records pointing at the server — are shown as **Managed** and
cannot be edited, renamed into, or deleted here. Renaming an ordinary record
*into* one of those names is blocked too. The hosting panel remains the place to
override that deliberately.

The same restraint applies elsewhere: **restoring** a backup is not offered in
billing (it silently discards everything written since the archive was taken),
and cron commands run as the account's own unprivileged system user inside the
panel's namespace and cgroup isolation — never as root.

![Cron jobs](docs/screenshots/client-cron.png)
*Common schedules or a full five-field expression, with example commands that
fill in the customer's real domain path*

---

## Requirements

| Component | Minimum |
|-----------|---------|
| PHP       | **8.4** (the locked Symfony 8 dependencies require it — 8.3 installs, then answers every request with a 500) |
| MySQL     | 8.0 (or MariaDB 10.6) |
| Node.js   | 18+ |
| Composer  | 2.x |
| Web server | Nginx or Apache with PHP-FPM |
| Disk | **~130 MB** for the app itself (code + PHP dependencies + built assets); the Docker image is ~410 MB. Allow **at least 2 GB free** for the database, ticket/backup uploads and logs as they grow. `node_modules` (~100 MB) is only needed while building and can be removed afterwards. |
| RAM | 1 GB works for a small install; 2 GB is comfortable with the database on the same box |
| PHP extensions | `bcmath`, `curl`, `dom`, `fileinfo`, `gd`, `mbstring`, `mysqli`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `imap` |

**Optional but recommended:** Redis (session/cache), SMTP server or relay
(email delivery), supervisor (queue worker).

---

## Quick Start with Docker

The fastest way to try PNLCS is the official Docker image
[**`panelica/pnlcs-runtime`**](https://hub.docker.com/r/panelica/pnlcs-runtime).
It bundles PHP-FPM 8.4 + nginx + Node.js 20 + supervisor and clones the latest
code from this repository on first start. No manual `composer install` or
`npm run build` — the entrypoint handles everything.

```bash
docker network create pnlcs-net

docker run -d --name pnlcs-db --network pnlcs-net \
  -e MYSQL_ROOT_PASSWORD=changeme \
  -e MYSQL_DATABASE=pnlcs -e MYSQL_USER=pnlcs -e MYSQL_PASSWORD=changeme \
  mariadb:11

docker run -d --name pnlcs --network pnlcs-net -p 8090:80 \
  -e DB_HOST=pnlcs-db -e DB_DATABASE=pnlcs \
  -e DB_USERNAME=pnlcs -e DB_PASSWORD=changeme \
  -e APP_URL=http://localhost:8090 \
  panelica/pnlcs-runtime:1.4
```

Wait 3–5 minutes for the first start (composer install + npm build), then
visit **http://localhost:8090/install** to run the in-app install wizard.
The wizard guides you through requirements check, admin account creation
(you choose username + password), and application settings — then locks
itself permanently.

To pull the latest code from this repo into a running container:

```bash
docker exec pnlcs /usr/local/bin/update.sh
```

📦 **Full image documentation, environment variables, screenshots, and
production deployment notes:**
👉 https://hub.docker.com/r/panelica/pnlcs-runtime

---

## Self-Hosted Installation

### 0. Prepare the server

Skip this if PHP, MySQL, Node and Composer are already installed (a control
panel such as Panelica gives you all of them).

**Ubuntu 24.04 / Debian 13**

```bash
# PHP 8.4 with the extensions PNLCS needs.
# Ubuntu 24.04 ships 8.3 in its own repos, which is not enough - add the
# ondrej PPA first. Debian 13 carries 8.4 natively; skip the PPA line there.
sudo add-apt-repository -y ppa:ondrej/php   # Ubuntu only
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl php8.4-imap

# Database
sudo apt install -y mysql-server        # or: mariadb-server

# Web server
sudo apt install -y nginx

# Node.js 20 LTS (for building the frontend assets)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**RHEL family (AlmaLinux 9 / Rocky 9)**

```bash
sudo dnf install -y epel-release https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module reset php -y && sudo dnf module enable php:remi-8.4 -y
sudo dnf install -y php php-fpm php-mysqlnd php-mbstring php-xml php-gd \
  php-bcmath php-intl php-imap mysql-server nginx
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs
```

Check what you have:

```bash
php -v          # 8.4 or newer — 8.3 will 500 at runtime
mysql --version # 8.0 / MariaDB 10.6 or newer
node -v         # 18 or newer
composer -V     # 2.x
```

### 1. Clone the repository

```bash
git clone https://github.com/Panelica/pnlcs.git
cd pnlcs
```

### 2. Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Create the database

```sql
CREATE DATABASE pnlcs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pnlcs'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON pnlcs.* TO 'pnlcs'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Configure environment

```bash
cp .env.example .env
```

Open `.env` in your editor and set at least these values:

```ini
APP_NAME="Your Company"
APP_URL=https://billing.your-domain.com
APP_ENV=production
APP_DEBUG=false

DB_DATABASE=pnlcs
DB_USERNAME=pnlcs
DB_PASSWORD=choose-a-strong-password

MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="Your Company"
```

**Note:** `DB_CONNECTION` defaults to `mysql` — do not change it to `sqlite`
unless you know what you're doing (some migrations use MySQL-specific SQL).

### 5. Generate the application key

```bash
php artisan key:generate
```

### 6. Run migrations

```bash
php artisan migrate --force
```

**Do not run `php artisan db:seed` here.** The install wizard you will open
in step 12 runs the seeder itself and renames the seeded administrator to the
username and password *you* choose. Seeding by hand creates an administrator
first - and the wizard, seeing one, locks itself before you ever reach it,
leaving you with a default `admin` / `admin123` account you never chose.

The wizard's seeding provides everything an installation starts with: four
starter currencies (USD, EUR, GBP, TRY), ticket departments and statuses,
25 email templates, 30 languages, the full translation set, the knowledge
base, and default homepage sections.

*Headless installs only:* if you are scripting an installation with no
browser step at all, `php artisan db:seed --force` is how you seed - the
default administrator is then `admin` / `admin123`, the wizard stays closed
by design, and changing that password is your first job.

### 7. Build frontend assets

```bash
npm install
npm run build
```

### 8. Create the public storage symlink

```bash
php artisan storage:link
```

### 9. Cache configuration for production

```bash
php artisan optimize
```

### 10. Set directory permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

*(adjust the user to match your server — `nginx`, `apache`, or your panel user)*

### 11. Point your web server to `public/`

Whatever you use — a control panel, raw Nginx, Apache, Caddy — make sure
**the document root is the `public/` directory**, not the project root.
Pointing it at the project root exposes `.env`, so this is the one step worth
double-checking.

**Nginx example** (`/etc/nginx/sites-available/pnlcs`):

```nginx
server {
    listen 80;
    server_name billing.example.com;
    root /var/www/pnlcs/public;      # note: /public

    index index.php;
    charset utf-8;
    client_max_body_size 64M;        # ticket + backup uploads

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/pnlcs /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Add HTTPS with Certbot (`sudo certbot --nginx -d billing.example.com`) before
taking payments — the checkout and admin login should never run over plain HTTP.

### 12. Open the install wizard

Visit **https://billing.example.com/install** in your browser. The wizard
checks requirements, seeds the database, asks for your administrator
username and password, and takes the application name and URL - then locks
itself permanently. This is where your admin account is created; there is no
default password to change afterwards.

### 13. Schedule the cron runner

Add a single line to the web user's crontab (`crontab -e`):

```
* * * * * cd /path/to/pnlcs && php artisan schedule:run >> /dev/null 2>&1
```

This drives invoice generation, payment reminders, automatic suspensions,
SSL polling, and other background tasks.

### 14. Queue: sync by default, worker only if you switch

`.env.example` ships `QUEUE_CONNECTION=sync`: mail and background jobs run
inline and always happen, which is the right shape for a single-server
install. Switch to `database` only **together with** a running worker - the
database driver without a worker puts every queued email into the jobs table
forever, and nothing sends while everything looks fine. A simple `supervisor`
entry for that setup:

```ini
[program:pnlcs-worker]
command=php /path/to/pnlcs/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
```


### Installing inside a hosting-panel account (Panelica, cPanel, …)

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
   2-6, using the panel's PHP 8.4 binary (on Panelica: `php84`). If MySQL
   listens on a socket, add `DB_SOCKET=` with the panel's socket path.
5. Build the assets with the panel's Node (on Panelica, install one under
   Node.js Versions and use its `npm`).
6. Point the webroot at `pnlcs/public` with a same-owner symlink:
   `mv public_html public_html.default && ln -s pnlcs/public public_html`.
   A symlink owned by the same account passes the panel's
   `disable_symlinks if_not_owner` protection.
7. Add the cron line from step 13 as a panel cron job for the account.
8. Open `https://example.com/install` and finish the wizard.

---

## Updating

PNLCS updates in place — latest code, database migrations, and rebuilt frontend
assets — without touching your data.

### Docker

One command pulls the latest release into a running container:

```bash
docker exec pnlcs /usr/local/bin/update.sh
```

It runs `git reset --hard origin/main` → `composer install` → `php artisan migrate`
→ `npm run build` → cache rebuild → php-fpm reload. Your database and uploaded
files live on the `pnlcs_app` volume and are left untouched.

Set `AUTO_UPDATE=1` on the container to pull the latest code automatically on
every restart.

#### `500 — Composer detected issues in your platform: PHP >= 8.4.0`

The web server's PHP-FPM is older than the PHP that ran `composer install`.
The dependencies are locked against PHP 8.4, so the page dies before Laravel
even boots - and because it dies that early, `storage/logs` stays empty.
Point the site (or pool) at PHP 8.4: on a panel, change the domain's PHP
version; on raw nginx, fix the `fastcgi_pass` socket.

#### `fatal: detected dubious ownership in repository`

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

### Self-hosted (without Docker)

If you installed PNLCS directly on a server (see **Self-Hosted Installation**
below), you update it **in place** — new code, migrations and rebuilt assets,
your data untouched. Run every command from your PNLCS directory
(`cd /path/to/pnlcs`).

**1. Back up and pause the app (recommended on production).**
```bash
php artisan down            # shows a maintenance page to visitors
mysqldump -u pnlcs -p pnlcs > backup-$(date +%F).sql   # database snapshot
```

**2. Pull the latest code from this repository.**
```bash
git pull origin main
```

**3. Update PHP dependencies.**
```bash
composer install --no-dev --optimize-autoloader
```

**4. Apply any new database migrations.**
```bash
php artisan migrate --force
```

**5. Rebuild the frontend assets.**
```bash
npm ci && npm run build
```

**6. Refresh the cached config, routes and views.**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**7. Reload PHP so the new code goes live.**
```bash
sudo systemctl reload php8.4-fpm    # or your process manager / FPM pool
```

**8. Bring the app back up.**
```bash
php artisan up
```

That's it — your installation now runs the latest code with all data intact.
If you use a queue worker (step 13 of installation), restart it too:
`php artisan queue:restart`.

### Inside a hosting-panel account (Panelica, cPanel, …)

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

---

## First Steps After Installation

> 📖 **New to hosting billing?** The full
> **[user guide](https://panelica.github.io/pnlcs/)** walks you through every
> concept and task in plain language — start with
> **[Your First Sale](https://panelica.github.io/pnlcs/getting-started/your-first-sale/)**
> for an end-to-end walkthrough. The steps below are the quick version.

Once the site loads and you can reach `/admin/login`, do these in order:

### 1. Sign in

- URL: `https://billing.your-domain.com/admin/login`
- Use the administrator username and password you chose in the install
  wizard. (Only a headless install that seeded by hand has the default
  `admin` / `admin123` - if that is you, changing it is the first job.)
- (Recommended) Enable **Two-Factor Authentication** under
  **My Account → Security**.

### 2. Configure General Settings

**Settings → General**

- Company name, support email, logo, favicon
- Default language, currency, timezone
- Date format, invoice pay terms, tax behavior

### 3. Configure Email Delivery

**Settings → Email** (or edit `.env` directly)

- Set `MAIL_MAILER` to `smtp` (default is `log` for testing)
- Fill in `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`
- Send a **test email** from the settings page to verify

Until this is done, emails are written to `storage/logs/laravel.log` only.

### 4. Customize Appearance

**Settings → Appearance**

- Pick a theme from the 15 built-in options
- Upload your logo and favicon
- Configure homepage sections (hero, features, pricing, testimonials)
- Set up white-label footer text

### 5. Add Payment Gateways

**Configuration → Gateways**

- **Stripe** — paste your API keys, enable
- **PayPal** — client ID + secret (sandbox or live)
- **Bank Transfer** — set instructions shown to clients
- Test each gateway with a small order before going live

### 6. Add Server Modules (if you sell hosting)

**Configuration → Servers**

- Add your Panelica / cPanel / Plesk / DirectAdmin server
- Test the API connection from the server edit page
- Assign servers to **server groups** if you have multiple

### 7. Create Your First Product

**Products** (main admin menu)

- Create a **product group** (e.g. "Shared Hosting")
- Create a product, link it to a server and a module
- Set pricing for monthly / quarterly / annually
- Enable **auto-setup** if you want provisioning on payment

### 8. Configure Domain Pricing (if you sell domains)

**Configuration → Domain Pricing**

- Add TLDs you sell (`.com`, `.net`, ...)
- Set registration, transfer, and renewal prices
- Link to a registrar module (or use Manual)

### 9. Set Up Tax Rules

**Configuration → Tax**

- Add country-level or state-level tax rules
- Choose inclusive or exclusive tax display
- Assign taxable flag to products individually

### 10. (Optional) Invite Staff and Define Roles

**Configuration → Admin Roles / Admins**

- Create custom roles (e.g. "Billing Manager", "Support Agent")
- Pick permissions per role from the 45+ available
- Add staff members and assign roles

### 11. Enable Email Verification for Signups (recommended)

**Settings → General → Security**

- Toggle **Require email verification** on
- New signups will receive a verification link before they can order

### 12. Test the Full Flow End-to-End

- Open an **incognito browser**
- Visit `/client/register` and create a test client
- Place a test order for one of your products
- Pay with the test mode of your gateway
- Verify the invoice, service, and email flow all work

---

## Upgrading

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm install
npm run build
php artisan optimize:clear
php artisan optimize
```

Always back up your database before pulling new migrations.

---

## Modules — Servers, Payment Gateways & Domain Registrars

PNLCS ships with modular **server**, **gateway**, **registrar**, and **SSL
provider** integrations under the `modules/` directory. Add control-panel
servers (cPanel, Plesk, DirectAdmin, Proxmox, HestiaCP, Vultr, Panelica),
configure payment gateways (Stripe, PayPal, Authorize.Net, Razorpay, Mollie,
Tpay, bank transfer), and connect domain registrars (Enom, Namecheap, ResellerClub)
without touching core code.

> 💡 **Choosing a panel to sell on?** The **[Panelica](https://panelica.com)**
> server module is tested end-to-end and provisions instantly. Panelica is a
> modern **cPanel / Plesk alternative** with built-in per-user isolation (no
> CloudLinux) and universal migration from cPanel, Plesk, DirectAdmin, and
> CyberPanel.

This is every module in `modules/`, and whether the test suite exercises its
own code. "Covered" means there are tests that drive the module and assert on
what it sends and stores, with the provider's HTTP responses faked. It is not
a statement that the integration has been run against a live provider account.

| Module        | Type      | Automated tests |
|---------------|-----------|-----------------|
| Panelica      | Server    | Covered         |
| cPanel        | Server    | Covered         |
| Plesk         | Server    | Covered         |
| DirectAdmin   | Server    | Covered         |
| Proxmox       | Server    | Covered         |
| HestiaCP      | Server    | Covered         |
| Vultr         | Server    | Covered         |
| Custom        | Server    | None yet        |
| Stripe        | Gateway   | Covered         |
| PayPal        | Gateway   | Covered         |
| Authorize.Net | Gateway   | Covered         |
| Razorpay      | Gateway   | Covered         |
| Mollie        | Gateway   | Covered         |
| Tpay          | Gateway   | None yet        |
| BankTransfer  | Gateway   | Covered         |
| Enom          | Registrar | Covered         |
| Namecheap     | Registrar | Covered         |
| ResellerClub  | Registrar | Covered         |
| Manual        | Registrar | None yet        |
| GoGetSSL      | SSL       | Covered         |

If you run one of these against a real provider, please open an issue with
what worked and what didn't — especially anything the faked responses could
not have caught. A short note is enough; we can iterate from there.

Adding a new module? Look at the existing ones as a reference; each module
is a self-contained directory with a handler class and optional config view.

---

## AI Assistants — MCP Server

PNLCS ships a first-party [Model Context Protocol](https://modelcontextprotocol.io)
server, [`pnlcs-mcp` on npm](https://www.npmjs.com/package/pnlcs-mcp). Connect
Claude Code, Claude Desktop, Cursor or VS Code to your install and ask it
things in plain English — *which invoices are overdue*, *any orders held as
fraud*, *open a ticket for this client*. Fifteen read tools are always
available; the seven write tools exist only when you opt in with
`PNLCS_ALLOW_WRITES=1`. Zero dependencies, nothing to install on the PNLCS
side — it speaks to the same admin API your screens use, with an API
credential you create under **Configuration → API Credentials**.

Setup for every client lives in [`mcp/README.md`](mcp/README.md). Claude Code
users need one command:

```bash
claude mcp add pnlcs \
  --env PNLCS_URL=https://billing.example.com \
  --env PNLCS_IDENTIFIER=your_identifier \
  --env PNLCS_SECRET=your_secret \
  -- npx -y pnlcs-mcp
```

---

## Internationalization

All UI strings live in the database (`dynamic_translations` table) and
flat PHP files under `lang/<locale>/`. Translations are editable from the
admin panel under **Configuration → Languages & Translations**. Exporting
to JSON, importing, and AI-assisted batch translation are supported out
of the box.

If your language isn't covered yet, you can either submit a PR against
the seeder or use the admin UI's export/import flow.

---

## Security

- All admin routes require authentication via the `admin.auth` middleware
- Over 250 admin endpoints are gated by fine-grained permissions
- CSRF protection on every form
- Rate limiting on login, 2FA, password reset, and verification email resends
- Webhook routes are CSRF-exempt but validated by HMAC signatures
- Eloquent ORM everywhere (no raw SQL concatenation)

Please report security issues privately to **security@panelica.com** rather
than opening a public GitHub issue.

---

## Contributing

Issues and pull requests are welcome.

Ways to help:

1. **Test a module** from the "Needs testing" list above and file an issue
   with what you found.
2. **Report a bug** with reproduction steps — screenshots help a lot.
3. **Improve a translation** via the admin UI's export, edit a JSON file,
   and send us a PR.
4. **Write documentation** — installation on specific hosting panels, how
   to add a custom module, etc.

Please keep in mind:

- This is a side project for the Panelica team. We'll get to issues as
  quickly as we can but same-day responses are rare.
- PRs that include tests are prioritized.
- Please match the existing coding style (Laravel Pint + conventional
  commits).

---

## Support & Community

Need help, want to report a bug, or have a feature request?

- 🐛 **Bug reports & feature requests** — [GitHub Issues](https://github.com/Panelica/pnlcs/issues)
- 💬 **Community forum** — [forum.panelica.com](https://forum.panelica.com)
- 📧 **Email** — [info@panelica.com](mailto:info@panelica.com)
- 🌐 **Main site** — [panelica.com](https://panelica.com)
- 🔒 **Security disclosures** — [security@panelica.com](mailto:security@panelica.com) *(please do not open public issues for security)*

We're happy to help from the forum or by email, but since this is a side
project your patience is appreciated. For urgent matters, the forum tends
to get the fastest community response.

---

## Credits

- **[Panelica](https://panelica.com)** — the modern hosting control panel and
  a **cPanel / Plesk alternative that needs no CloudLinux**; its team builds
  and maintains PNLCS
- **Laravel** by Taylor Otwell and the Laravel community
- **WHMCS** — for inspiring much of the data model and workflow
- Every contributor who opens an issue or a pull request

---

## License

Released under the **MIT License**. See [`LICENSE`](LICENSE) for details.

---

<p align="center">
  <sub>
    <b>Keywords:</b> WHMCS alternative · open-source hosting billing · self-hosted
    billing platform · Laravel billing · PHP client portal · hosting management
    software · free WHMCS · invoicing system · reseller hosting software ·
    domain management · SSL management · support ticket system · hosting CRM ·
    cPanel alternative · Plesk alternative · CyberPanel alternative ·
    CloudLinux alternative · Panelica hosting control panel
  </sub>
</p>
