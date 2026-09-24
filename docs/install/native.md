# Install on your own server

A plain Laravel installation: PHP-FPM 8.4, MySQL or MariaDB, and Nginx. Every
command below was run, in this order, on freshly created servers:

| System | PHP | Database | Result |
|---|---|---|---|
| Ubuntu 24.04 LTS | 8.4 (ondrej PPA) | MySQL 8.0 | installed, wizard completed, scheduler and update tested |
| Debian 13 | 8.4 (Debian) | MariaDB 11.8 | installed, wizard completed, scheduler and update tested |
| AlmaLinux 9 | 8.4 (Remi) | MySQL 8.0 | installed with SELinux enforcing (no denials), wizard completed, scheduler and update tested |

Rocky Linux 9 uses the same commands as AlmaLinux 9.

!!! warning "The one rule that prevents most install problems"
    The whole PNLCS directory belongs to the **web server user**, and every
    `composer`, `php artisan` and `npm` command runs **as that user**. The
    install wizard writes `.env`, the application writes `storage/`, and
    updates run `git`, `composer` and `npm` inside the tree. When the tree is
    owned by `root` you get a 500 on the last step of the wizard (`.env` not
    writable), `fatal: detected dubious ownership` from `git pull`, and
    `EACCES` from `npm`.

The values that differ between the two families of systems:

| | Ubuntu / Debian | AlmaLinux / Rocky |
|---|---|---|
| Web server user | `www-data` | `apache` |
| PHP-FPM socket | `/run/php/php8.4-fpm.sock` | `/run/php-fpm/www.sock` |
| PHP-FPM service | `php8.4-fpm` | `php-fpm` |
| Database service | `mysql` (Ubuntu), `mariadb` (Debian) | `mysqld` |
| Nginx site file | `/etc/nginx/sites-available/pnlcs` | `/etc/nginx/conf.d/pnlcs.conf` |
| SELinux | not used | **enforcing**: step 10 is required |

The guide installs into `/var/www/pnlcs` and uses `example.com` as the
address. Replace both with your own.

## 0. Prepare the server

Skip this if PHP 8.4, a database, Node and Composer are already installed (a
control panel such as Panelica gives you all of them — see
[Installing inside a hosting-panel account](hosting-panel.md)).

**Ubuntu 24.04**

Ubuntu 24.04 ships PHP 8.3, which is not enough, so PHP 8.4 comes from the
ondrej PPA.

```bash
sudo apt update
sudo apt install -y software-properties-common git unzip curl cron
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl php8.4-imap
sudo apt install -y mysql-server nginx

# Node.js 20 LTS (only for building the frontend assets)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Debian 13**

Debian 13 carries PHP 8.4 itself — no extra repository. Two differences from
Ubuntu, both found on a clean Debian 13 server:

- **There is no `php8.4-imap` package.** Leave it out: if you put it in the
  list, `apt` refuses the whole line and installs no PHP at all. `imap` is
  optional (mailbox → ticket import only).
- **There is no `mysql-server` package.** Debian ships MariaDB, which PNLCS
  supports.
- **`cron` is not installed** on the Debian 13 cloud image — without it the
  scheduler in step 13 never runs. It is in the list below.

```bash
sudo apt update
sudo apt install -y git unzip curl cron
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl
sudo apt install -y mariadb-server nginx

curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**AlmaLinux 9 / Rocky Linux 9**

PHP 8.4 comes from the Remi repository. The `zip` extension is a separate
package here (`php-pecl-zip`), and unlike Debian-based systems the services
are **not** started for you.

```bash
sudo dnf install -y epel-release https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.4 -y
sudo dnf install -y php php-fpm php-mysqlnd php-mbstring php-xml php-gd \
  php-bcmath php-intl php-imap php-pecl-zip \
  mysql-server nginx git unzip cronie policycoreutils-python-utils

curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Start the services now and on every boot.
sudo systemctl enable --now php-fpm mysqld nginx
```

**Check what you have:**

```bash
php -v          # PHP 8.4.x — 8.3 will 500 at runtime
php -m | grep -E '^(bcmath|curl|dom|fileinfo|gd|intl|mbstring|openssl|pdo_mysql|tokenizer|xml|zip)$' | wc -l   # 12
mysql --version # MySQL 8.0 / MariaDB 10.6 or newer
node -v         # v18 or newer
composer -V     # Composer version 2.x
```

**Firewall.** The cloud images we tested had no firewall enabled. If yours
does, open HTTP and HTTPS — `sudo ufw allow 'Nginx Full'` on Ubuntu, or
`sudo firewall-cmd --permanent --add-service=http --add-service=https && sudo firewall-cmd --reload`
on AlmaLinux/Rocky.

## 1. Get the code and hand it to the web server user

```bash
sudo git clone https://github.com/Panelica/pnlcs.git /var/www/pnlcs
sudo chown -R www-data:www-data /var/www/pnlcs     # AlmaLinux/Rocky: apache:apache
cd /var/www/pnlcs
```

Define a short helper for the rest of this guide. It runs a command as the web
server user, with a writable home for the Composer and npm caches (the web
user's own home, `/var/www`, belongs to root):

```bash
# Ubuntu / Debian
pn() { sudo -u www-data HOME=/tmp/pnlcs-home COMPOSER_HOME=/tmp/pnlcs-home/composer "$@"; }

# AlmaLinux / Rocky
pn() { sudo -u apache HOME=/tmp/pnlcs-home COMPOSER_HOME=/tmp/pnlcs-home/composer "$@"; }
```

The helper only lives in your current shell — define it again if you log in
later.

## 2. Install PHP dependencies

```bash
pn composer install --no-dev --optimize-autoloader --no-interaction
```

> **AlmaLinux/Rocky:** `sudo` there does not search `/usr/local/bin`, so the
> line above answers `sudo: composer: command not found`. Use the full path:
> `pn /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction`

## 3. Create the database

Open the database console as root — on a fresh server `root` signs in through
the system account, so there is no password to type:

```bash
sudo mysql
```

Then create the database and a user for PNLCS (MySQL and MariaDB accept the
same statements):

```sql
CREATE DATABASE pnlcs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pnlcs'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON pnlcs.* TO 'pnlcs'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Configure the environment

```bash
pn cp .env.example .env
sudo nano .env
```

Set at least these values (editing the existing file keeps its owner):

```ini
APP_NAME="Your Company"
APP_URL=https://example.com
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pnlcs
DB_USERNAME=pnlcs
DB_PASSWORD=choose-a-strong-password

MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="Your Company"
```

Then make `.env` readable by the web user only — it holds your database
password and application key, and the install wizard must be able to write it:

```bash
sudo chown www-data:www-data .env     # AlmaLinux/Rocky: apache:apache
sudo chmod 640 .env
```

**Notes**
- Keep `DB_CONNECTION=mysql` for MariaDB too; do not switch to `sqlite` (some
  migrations use MySQL-specific SQL).
- If the site starts on plain HTTP while you set up TLS, use `http://` in
  `APP_URL` for now and change it in step 15.
- Mail server settings (SMTP host, user, password) are entered in the admin
  panel later, not here.

## 5. Generate the application key

```bash
pn php artisan key:generate
```

## 6. Run the database migrations

```bash
pn php artisan migrate --force
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

## 7. Build the frontend assets

```bash
pn npm ci
pn npm run build
```

## 8. Link public storage

```bash
pn php artisan storage:link
```

## 9. Cache configuration, routes and views

```bash
pn php artisan optimize
```

## 10. SELinux (AlmaLinux / Rocky only)

SELinux is enforcing on AlmaLinux and Rocky. Without these lines every page
answers **500**: PHP-FPM may not write to `storage/`, and may not connect to
the database. We measured exactly those two denials in the audit log of a
clean AlmaLinux 9 server.

```bash
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/pnlcs/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/pnlcs/bootstrap/cache(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/pnlcs/\.env"
sudo restorecon -R /var/www/pnlcs

sudo setsebool -P httpd_can_network_connect_db 1   # PHP → MySQL/MariaDB
sudo setsebool -P httpd_can_network_connect 1      # PHP → payment gateways, server and registrar APIs, SMTP
```

Do not switch SELinux off to make the errors go away — these rules give the
application exactly what it needs and nothing more.

## 11. Point Nginx at `public/`

The document root must be the **`public/`** directory, never the project root —
pointing it at the project root exposes `.env`.

**Ubuntu / Debian** — create `/etc/nginx/sites-available/pnlcs`:

```nginx
server {
    listen 80;
    server_name example.com;
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

Enable it and **remove the default site** — otherwise a request to the
server's IP address keeps landing on the "Welcome to nginx" page:

```bash
sudo ln -s /etc/nginx/sites-available/pnlcs /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

**AlmaLinux / Rocky** — create `/etc/nginx/conf.d/pnlcs.conf` with the same
block, changing only the PHP-FPM socket:

```nginx
        fastcgi_pass unix:/run/php-fpm/www.sock;
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

**Check it before you open a browser:**

```bash
curl -sI http://example.com/install | head -1      # HTTP/1.1 302 Found  (→ the wizard)
curl -sI http://example.com/.env | head -1         # 403 or 404 — never 200
```

Using Apache or Caddy instead? The same rule applies: document root =
`public/`, PHP handled by PHP-FPM 8.4, and every request that is not a file
rewritten to `index.php`.

## 12. Run the install wizard

Open **http://example.com/install** in your browser. The wizard walks
through:

1. **Requirements** — PHP version, every required extension, and whether
   `storage/`, `bootstrap/cache/` and `.env` are writable. Everything must be
   green before **Continue** appears; a red line names exactly what to fix.
2. **Database** — skipped automatically, because you already ran the
   migrations in step 6.
3. **Administrator** — the username, email and password *you* choose. There
   is no default password to change afterwards.
4. **Application** — the public URL, the company name and the default
   language.
5. **Finish** — the wizard writes a lock file and closes itself permanently;
   `/install` answers 404 from then on.

Sign in at **http://example.com/admin/login**.

## 13. Schedule the cron runner

Everything that happens by itself — invoice generation, payment reminders,
suspensions, SSL polling, backups, queued mail — is driven by one cron line
for the web server user:

```bash
sudo crontab -u www-data -e     # AlmaLinux/Rocky: -u apache
```

```
* * * * * cd /var/www/pnlcs && php artisan schedule:run >> /dev/null 2>&1
```

Check that the schedule is there and runs cleanly:

```bash
pn php artisan schedule:list
pn php artisan schedule:run
```

## 14. Queue: no separate worker needed

`.env.example` ships `QUEUE_CONNECTION=sync`: mail and background jobs run
inline, which is the right shape for a single server.

If you switch to `QUEUE_CONNECTION=database`, **the cron line from step 13
already processes the queue**: the scheduler starts a worker every minute
that drains the queue and stops. We verified this on a clean server — a
queued email was in the `jobs` table before `schedule:run`, and sent after
it, with no supervisor installed. Jobs therefore wait **up to a minute**.

Add a permanent worker only if you want queued jobs to run instantly. A
`supervisor` entry for that:

```ini
[program:pnlcs-worker]
command=php /var/www/pnlcs/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
```

## 15. Turn on HTTPS

Checkout and the admin login must never run over plain HTTP. With Certbot
(Ubuntu/Debian: `sudo apt install -y certbot python3-certbot-nginx`):

```bash
sudo certbot --nginx -d example.com
```

Then make sure `APP_URL` in `.env` starts with `https://` and refresh the
cache:

```bash
pn php artisan optimize
```

## 16. Final check

```bash
curl -sI https://example.com/install | head -1   # 404 — the wizard is closed
curl -sI https://example.com/.env | head -1      # 403 or 404
ls -l /var/www/pnlcs/.env                                  # owned by the web user, mode 640
pn php artisan schedule:run                                # finishes without errors
```

Then continue with [First Login](../getting-started/first-login.md) and the [Setup Checklist](../getting-started/setup-checklist.md).
