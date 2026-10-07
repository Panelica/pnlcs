#!/usr/bin/env bash
# Runs ON a Debian/Ubuntu test machine (as root): installs release A natively
# the way docs/install/native.md does (nginx + PHP-FPM 8.4 + MariaDB + cron),
# from the release package, with the lab's index and key.
#
#   vm-install.sh <index-variant> [<host-name-for-APP_URL>]
set -euo pipefail
VARIANT="${1:?variant}"; HOST="${2:-$(hostname -I | awk '{print $1}')}"
LAB=/opt/lab; APP=/var/www/pnlcs
as_www() { runuser -u www-data -- "$@"; }

systemctl stop cron
[ -d "$APP" ] && mv "$APP" "/var/www/pnlcs.old.$(date +%s)" && find /var/www -maxdepth 1 -name 'pnlcs.old.*' -mmin +0 -exec rm -r {} +
mkdir -p /var/www
mariadb -e "DROP DATABASE IF EXISTS pnlcs; CREATE DATABASE pnlcs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            CREATE USER IF NOT EXISTS 'pnlcs'@'localhost' IDENTIFIED BY 'lab-password'; GRANT ALL ON pnlcs.* TO 'pnlcs'@'localhost'; FLUSH PRIVILEGES;"
tar -xzf "$LAB/releases/A/pnlcs-1.3.0.tar.gz" -C /var/www
chown -R www-data:www-data "$APP"
cd "$APP"
as_www cp .env.example .env
python3 - "$APP/.env" "$HOST" "$VARIANT" <<'PY'
import re, sys, base64
p, host, variant = sys.argv[1:]
s = open(p).read()
vals = {'APP_ENV': 'production', 'APP_DEBUG': 'false', 'APP_URL': f'http://{host}', 'DB_HOST': '127.0.0.1', 'DB_PORT': '3306',
        'DB_DATABASE': 'pnlcs', 'DB_USERNAME': 'pnlcs', 'DB_PASSWORD': 'lab-password', 'SESSION_DRIVER': 'file', 'CACHE_STORE': 'file',
        'QUEUE_CONNECTION': 'sync', 'MAIL_MAILER': 'log', 'PNLCS_UPDATE_INDEX_URL': f'file:///opt/lab/index-{variant}.json',
        'PNLCS_UPDATE_PUBLIC_KEY': base64.b64encode(open('/opt/lab/lab-key.pub', 'rb').read()).decode()}
for k, v in vals.items():
    s = re.sub(rf'^{k}=.*$', f'{k}={v}', s, flags=re.M) if re.search(rf'^{k}=', s, re.M) else s + f'\n{k}={v}'
open(p, 'w').write(s + '\n')
PY
as_www php artisan key:generate --force >/dev/null
as_www php artisan migrate --force >/dev/null
as_www php artisan db:seed --force >/dev/null
as_www sh -c 'date -Iseconds > storage/installed.lock'
as_www php artisan storage:link >/dev/null 2>&1 || true
as_www php artisan config:cache >/dev/null && as_www php artisan route:cache >/dev/null && as_www php artisan view:cache >/dev/null

cat > /etc/nginx/sites-available/pnlcs <<'NGINX'
server {
    listen 80 default_server;
    server_name _;
    root /var/www/pnlcs/public;
    index index.php;
    client_max_body_size 64M;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/pnlcs /etc/nginx/sites-enabled/pnlcs
rm -f /etc/nginx/sites-enabled/default
nginx -t 2>/dev/null && systemctl reload nginx
echo '* * * * * cd /var/www/pnlcs && php artisan schedule:run >> /dev/null 2>&1' | crontab -u www-data -
systemctl restart php8.4-fpm
systemctl start cron
echo "installed $(cat $APP/VERSION) on $HOST"
