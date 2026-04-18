# PNLCS — Panelica License & Customer System

Self-hosted WHMCS alternative for hosting companies. Client portal, billing, licenses,
domain/SSL management, ticketing, and reseller support — built on Laravel 13.

**Live demo:** [hosting.panelica.com](https://hosting.panelica.com)

---

## Requirements

| Software | Version |
|----------|---------|
| PHP      | **8.3** or **8.4** |
| Extensions | `bcmath`, `curl`, `dom`, `fileinfo`, `gd`, `mbstring`, `mysqli`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip` |
| MySQL    | **8.0+** (or MariaDB 10.6+) |
| Node.js  | **18+** (for asset build) |
| Composer | **2.x** |
| Web server | Nginx or Apache with PHP-FPM |

**Optional:** Redis (for faster cache/session), Postfix/SMTP relay (for real email delivery).

---

## Installation

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
CREATE USER 'pnlcs'@'localhost' IDENTIFIED BY 'strong-password-here';
GRANT ALL PRIVILEGES ON pnlcs.* TO 'pnlcs'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Configure `.env`

```bash
cp .env.example .env
```

Edit `.env` and set **at minimum**:

```ini
APP_URL=https://your-domain.com
APP_ENV=production
APP_DEBUG=false

DB_DATABASE=pnlcs
DB_USERNAME=pnlcs
DB_PASSWORD=strong-password-here

# Uncomment if using Unix socket instead of TCP
# DB_SOCKET=/var/run/mysqld/mysqld.sock

MAIL_FROM_ADDRESS="noreply@your-domain.com"
```

### 5. Generate application key

```bash
php artisan key:generate
```

### 6. Run migrations and seed default data

```bash
php artisan migrate --force
php artisan db:seed --force
```

This creates:
- Default admin: **`admin` / `admin123`** — change immediately after first login
- 4 currencies (USD, EUR, GBP, TRY)
- Ticket departments & statuses
- 2,232 translation keys (English)
- 30 language entries (English active by default)
- Default homepage sections, email templates, domain pricing

### 7. Build frontend assets

```bash
npm install
npm run build
```

### 8. Create storage symlink

```bash
php artisan storage:link
```

### 9. Cache configuration (production)

```bash
php artisan optimize
```

### 10. Set file permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache   # adjust user for your server
```

### 11. Configure your web server

Point the document root to **`public/`** (NOT the project root).

**Nginx example:**

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    root /var/www/pnlcs/public;
    index index.php;

    ssl_certificate /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 12. Set up scheduler (cron)

Add this line to your crontab (`crontab -e` as the web user):

```
* * * * * cd /var/www/pnlcs && php artisan schedule:run >> /dev/null 2>&1
```

### 13. (Optional) Set up queue worker via supervisor

For email delivery and background jobs, run a queue worker:

```ini
[program:pnlcs-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pnlcs/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/pnlcs-worker.log
```

---

## First Login

1. Visit `https://your-domain.com/admin/login`
2. Login with: **`admin` / `admin123`**
3. **Change the password immediately** (My Account → Change Password)
4. Configure general settings: Settings → General
5. Configure mail: Settings → Email
6. Add payment gateways: Configuration → Gateways

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

---

## Modules

PNLCS ships with modular **gateways**, **server** integrations, and **domain registrars**.

**Payment Gateways:** Stripe, PayPal, Bank Transfer, Authorize.Net
**Server Modules:** Panelica, cPanel, Plesk, DirectAdmin, Proxmox, Custom
**Domain Registrars:** Enom, Manual, PandiPlus
**SSL Providers:** GoGetSSL, Sectigo, Manual

New modules can be added under `modules/` — see the existing ones as reference.

---

## Features

- **Client Portal** — billing, services, domains, tickets, knowledge base
- **Admin Panel** — 260+ routes across clients, orders, invoices, services, tickets, products, promotions, reports
- **Multi-language** — 30 locales, admin-editable translations via UI
- **Theme system** — 15 built-in themes, WordPress-style installation
- **RBAC** — fine-grained admin permissions with full-admin override
- **2FA** — TOTP for admin and customer portals
- **Invoicing** — PDF generation, tax rules, promotions, coupons, recurring billing
- **Overage billing** — disk/bandwidth usage metering (optional per-product)
- **API** — RESTful API with key-based auth for integration

---

## Security

- All admin routes behind `admin.auth` middleware
- Permission middleware on 250+ admin endpoints
- CSRF tokens on all forms
- Rate limiting on login, 2FA, password reset
- Webhook routes CSRF-excluded but HMAC-signed
- SQL injection protection via Eloquent parameter binding

If you find a security issue, please email **security@panelica.com** — do **not** open a public issue.

---

## License

Released under the [MIT License](LICENSE).

---

## Support

- **Documentation:** [docs.panelica.com/pnlcs](https://docs.panelica.com/pnlcs)
- **Issues:** [GitHub Issues](https://github.com/Panelica/pnlcs/issues)
- **Community:** [forum.panelica.com](https://forum.panelica.com)
- **Commercial support:** [panelica.com/support](https://panelica.com/support)
