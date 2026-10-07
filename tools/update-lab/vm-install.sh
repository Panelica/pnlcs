#!/usr/bin/env bash
# Runs ON a Debian, Ubuntu, AlmaLinux or Rocky test machine (as root): installs
# release A natively, the way docs/install/native.md does (nginx + PHP-FPM 8.4
# + MySQL/MariaDB + cron, SELinux rules on RHEL), with the lab's index and key.
#
#   vm-install.sh <index-variant> [<host-for-APP_URL>] [package|git]
#
# package: from the release package (how installations are made from now on)
# git:     cloned and built as the guide had operators do it before releases
#          (composer install, npm ci, npm run build) - today's installations
set -euo pipefail
VARIANT="${1:?variant}"; HOST="${2:-$(hostname -I | awk '{print $1}')}"; MODE="${3:-package}"
LAB=/opt/lab; APP=/var/www/pnlcs
FACTS="$(/opt/lab/vm-stack.sh | tail -1)"; case "$FACTS" in WEB_USER=*) eval "$FACTS" ;; *) echo "the stack could not be installed" >&2; exit 1 ;; esac
as_web() { runuser -u "$WEB_USER" -- env HOME=/tmp/pnlcs-home COMPOSER_HOME=/tmp/pnlcs-home/composer "$@"; }
DB="$(command -v mariadb || command -v mysql)"

systemctl stop "$CRON_SERVICE"
if [ -d "$APP" ]; then mv "$APP" "/var/www/.pnlcs-old-$(date +%s%N)"; fi
find /var/www -maxdepth 1 -name '.pnlcs-old-*' -exec rm -r {} + 2>/dev/null || true
mkdir -p /var/www /tmp/pnlcs-home && chown "$WEB_USER" /tmp/pnlcs-home
"$DB" -e "DROP DATABASE IF EXISTS pnlcs; CREATE DATABASE pnlcs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
          CREATE USER IF NOT EXISTS 'pnlcs'@'localhost' IDENTIFIED BY 'Lab-password-1'; ALTER USER 'pnlcs'@'localhost' IDENTIFIED BY 'Lab-password-1'; GRANT ALL ON pnlcs.* TO 'pnlcs'@'localhost'; FLUSH PRIVILEGES;"

if [ "$MODE" = git ]; then
  git clone -q --branch lab-main "$LAB/repo.bundle" "$APP"
  git -C "$APP" remote set-url origin https://github.com/Panelica/pnlcs.git
  chown -R "$WEB_USER:$WEB_USER" "$APP"
  cd "$APP"
  as_web composer install --no-dev --optimize-autoloader --no-interaction --quiet
  as_web npm ci --no-audit --no-fund --silent
  as_web npm run build --silent >/dev/null
else
  tar -xzf "$LAB/releases/A/pnlcs-1.3.0.tar.gz" -C /var/www
  chown -R "$WEB_USER:$WEB_USER" "$APP"
  cd "$APP"
fi

as_web cp .env.example .env
python3 - "$APP/.env" "$HOST" "$VARIANT" <<'PY'
import re, sys, base64
p, host, variant = sys.argv[1:]
s = open(p).read()
vals = {'APP_ENV': 'production', 'APP_DEBUG': 'false', 'APP_URL': f'http://{host}', 'DB_HOST': '127.0.0.1', 'DB_PORT': '3306',
        'DB_DATABASE': 'pnlcs', 'DB_USERNAME': 'pnlcs', 'DB_PASSWORD': 'Lab-password-1', 'SESSION_DRIVER': 'file', 'CACHE_STORE': 'file',
        'QUEUE_CONNECTION': 'sync', 'MAIL_MAILER': 'log', 'PNLCS_UPDATE_INDEX_URL': f'file:///opt/lab/index-{variant}.json',
        'PNLCS_UPDATE_PUBLIC_KEY': base64.b64encode(open('/opt/lab/lab-key.pub', 'rb').read()).decode()}
for k, v in vals.items():
    s = re.sub(rf'^{k}=.*$', f'{k}={v}', s, flags=re.M) if re.search(rf'^{k}=', s, re.M) else s + f'\n{k}={v}'
open(p, 'w').write(s + '\n')
PY
as_web php artisan key:generate --force >/dev/null
as_web php artisan migrate --force >/dev/null
as_web php artisan db:seed --force >/dev/null
as_web sh -c 'date -Iseconds > storage/installed.lock'
as_web php artisan storage:link >/dev/null 2>&1 || true
as_web php artisan config:cache >/dev/null && as_web php artisan route:cache >/dev/null && as_web php artisan view:cache >/dev/null

if command -v semanage >/dev/null && [ "$(getenforce 2>/dev/null)" = Enforcing ]; then
  semanage fcontext -a -t httpd_sys_rw_content_t "$APP/storage(/.*)?" 2>/dev/null || true
  semanage fcontext -a -t httpd_sys_rw_content_t "$APP/bootstrap/cache(/.*)?" 2>/dev/null || true
  semanage fcontext -a -t httpd_sys_rw_content_t "$APP/\.env" 2>/dev/null || true
  restorecon -R "$APP"
  setsebool -P httpd_can_network_connect_db 1
  setsebool -P httpd_can_network_connect 1
fi

cat > "$NGINX_CONF" <<NGINX
server {
    listen 80 default_server;
    server_name _;
    root $APP/public;
    index index.php;
    client_max_body_size 64M;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_pass unix:$FPM_SOCK;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
if [ -d /etc/nginx/sites-enabled ]; then ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/pnlcs; rm -f /etc/nginx/sites-enabled/default; fi
# RHEL's nginx.conf has its own default server on :80.
[ -f /etc/nginx/nginx.conf ] && sed -i 's/listen       80 default_server;/listen       80;/; s/listen       \[::\]:80 default_server;/listen       [::]:80;/' /etc/nginx/nginx.conf
nginx -t 2>/dev/null && systemctl reload nginx
echo "* * * * * cd $APP && php artisan schedule:run >> /dev/null 2>&1" | crontab -u "$WEB_USER" -
systemctl restart "$FPM_SERVICE"
systemctl start "$CRON_SERVICE"
echo "installed $(cat $APP/VERSION 2>/dev/null) ($MODE) on $HOST as $WEB_USER"
