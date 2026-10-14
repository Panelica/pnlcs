#!/usr/bin/env bash
# PNLCS installer - one command on a fresh server:
#
#   curl -fsSL https://raw.githubusercontent.com/Panelica/pnlcs/main/install.sh | sudo bash
#   curl -fsSL https://raw.githubusercontent.com/Panelica/pnlcs/main/install.sh | sudo bash -s -- --domain billing.example.com
#
# Supported: Ubuntu 22.04 and 24.04, Debian 12 and 13, AlmaLinux 9 and Rocky
# Linux 9. It follows docs/install/native.md: nginx, PHP-FPM 8.4 and MySQL or
# MariaDB from the system's packages (PHP from the ondrej PPA, the sury
# repository or Remi where the system has no 8.4), the latest PNLCS release
# checked against the PNLCS release key, the database, nginx, the scheduler,
# the firewall and, on AlmaLinux/Rocky, SELinux. Your administrator account is
# the last step, in the install wizard in your browser.
#
# It never touches an existing installation: it stops before changing anything
# if the directory, the database or the nginx site is already there.
#
# Options:
#   --domain <name>      the site's domain (default: the server's IP address)
#   --dir <path>         where PNLCS goes (default: /var/www/pnlcs)
#   --version <x.y.z>    a release other than the latest
#   --db-name <name>     database name (default: pnlcs)
#   --db-user <name>     database user (default: pnlcs)
#   --db-host <host>     use an existing database server instead of installing
#   --db-port <port>       one; with --db-password, the database and user must
#   --db-password <pw>     already exist (default host 127.0.0.1, port 3306)
set -Eeuo pipefail

# The release key's public half (config/updates.php): a package whose signed
# statement does not verify against it is not installed.
RELEASE_KEY='-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEUrkakoxq0WUZp7MMCIJ+R93AtcMD
6Ft8c0cym8M6UkVSWGHZ3Rx+WOPZXrAqHiIbiuBTpcC5f+Yur4M74bY2rA==
-----END PUBLIC KEY-----'
RELEASES="https://github.com/Panelica/pnlcs/releases"
REPO_API="https://api.github.com/repos/Panelica/pnlcs/releases"
LOG=/var/log/pnlcs-install.log

say()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; echo "==> $*" >>"$LOG"; }
warn() { printf '\033[1;33m!!\033[0m %s\n' "$*"; echo "!! $*" >>"$LOG"; }
die()  { printf '\033[1;31mxx\033[0m %s\n' "$*" >&2; echo "xx $*" >>"$LOG"; exit 1; }
# Output goes to the log; stdin is closed so nothing can read the rest of a
# script piped into bash.
run()  { echo "+ $*" >>"$LOG"; "$@" >>"$LOG" 2>&1 </dev/null; }

usage() {
    cat <<'USAGE'
PNLCS installer for a fresh Ubuntu 22.04/24.04, Debian 12/13, AlmaLinux 9 or Rocky Linux 9 server.

  curl -fsSL https://raw.githubusercontent.com/Panelica/pnlcs/main/install.sh | sudo bash -s -- [options]

  --domain <name>      the site's domain (default: the server's IP address)
  --dir <path>         where PNLCS goes (default: /var/www/pnlcs)
  --version <x.y.z>    a release other than the latest
  --db-name <name>     database name (default: pnlcs)
  --db-user <name>     database user (default: pnlcs)
  --db-host <host>     with --db-password: use an existing database and user on
  --db-port <port>     this server (default 127.0.0.1:3306) instead of
  --db-password <pw>   installing a database server
USAGE
}

main() {
    DOMAIN=""; APP_DIR=/var/www/pnlcs; PNLCS_VERSION=""; DB_NAME=pnlcs; DB_USER=pnlcs
    DB_HOST=127.0.0.1; DB_PORT=3306; DB_PASSWORD=""; EXISTING_DB=0
    while [ $# -gt 0 ]; do
        case "$1" in
            --domain) DOMAIN="${2:?--domain needs a value}"; shift 2 ;;
            --dir) APP_DIR="${2:?--dir needs a value}"; shift 2 ;;
            --version) PNLCS_VERSION="${2:?--version needs a value}"; PNLCS_VERSION="${PNLCS_VERSION#v}"; shift 2 ;;
            --db-name) DB_NAME="${2:?--db-name needs a value}"; shift 2 ;;
            --db-user) DB_USER="${2:?--db-user needs a value}"; shift 2 ;;
            --db-host) DB_HOST="${2:?--db-host needs a value}"; shift 2 ;;
            --db-port) DB_PORT="${2:?--db-port needs a value}"; shift 2 ;;
            --db-password) DB_PASSWORD="${2:?--db-password needs a value}"; EXISTING_DB=1; shift 2 ;;
            -h|--help) usage; exit 0 ;;
            *) die "unknown option: $1 (see --help)" ;;
        esac
    done

    [ "$(id -u)" -eq 0 ] || die "run it as root (sudo bash)"
    mkdir -p "$(dirname "$LOG")"; : >>"$LOG"; chmod 600 "$LOG"
    echo "----- $(date -Is) pnlcs install.sh $*" >>"$LOG"
    trap 'echo; warn "stopped at line $LINENO. The last lines of $LOG:"; tail -n 15 "$LOG" >&2' ERR

    detect_system
    check_free

    say "Installing nginx, PHP 8.4$( [ "$EXISTING_DB" = 1 ] || echo ' and the database server') (a few minutes)"
    install_packages
    PHP_BIN="$(command -v php8.4 || command -v php)"
    "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' || die "PHP 8.4 did not install ($("$PHP_BIN" -v | head -1))"
    configure_php

    download_release
    setup_database
    install_app
    configure_nginx
    configure_selinux
    configure_cron
    open_firewall
    verify
    summary
}

# Read, not sourced: the file also sets VERSION, NAME and ID, and sourcing it
# replaced the release to install with the system's own version.
os_release() { sed -n "s/^$1=//p" /etc/os-release | tr -d "\"'"; }

detect_system() {
    [ -r /etc/os-release ] || die "this system has no /etc/os-release"
    OS="$(os_release ID)"; OS_VERSION="$(os_release VERSION_ID)"; OS_CODENAME="$(os_release VERSION_CODENAME)"
    OS_NAME="$(os_release PRETTY_NAME)"; MAJOR="${OS_VERSION%%.*}"
    case "$OS:$MAJOR" in
        ubuntu:22|ubuntu:24|debian:12|debian:13) FAMILY=debian ;;
        almalinux:9|rocky:9) FAMILY=rhel ;;
        *) die "$OS_NAME is not supported. Supported: Ubuntu 22.04/24.04, Debian 12/13, AlmaLinux 9, Rocky Linux 9" ;;
    esac
    if [ "$OS" = ubuntu ] && [ "$OS_VERSION" != 22.04 ] && [ "$OS_VERSION" != 24.04 ]; then
        die "$OS_NAME is not supported. Supported: Ubuntu 22.04/24.04, Debian 12/13, AlmaLinux 9, Rocky Linux 9"
    fi
    command -v systemctl >/dev/null || die "systemd is needed"
    if [ "$FAMILY" = debian ]; then
        WEB_USER=www-data; FPM_SOCK=/run/php/php8.4-fpm.sock; FPM_SERVICE=php8.4-fpm; CRON_SERVICE=cron
        NGINX_SITE=/etc/nginx/sites-available/pnlcs; PHP_INI=/etc/php/8.4/fpm/conf.d/99-pnlcs.ini
    else
        WEB_USER=apache; FPM_SOCK=/run/php-fpm/www.sock; FPM_SERVICE=php-fpm; CRON_SERVICE=crond
        NGINX_SITE=/etc/nginx/conf.d/pnlcs.conf; PHP_INI=/etc/php.d/99-pnlcs.ini
    fi
    say "$OS_NAME"
}

# Nothing is changed before every one of these is known to be free.
check_free() {
    if [ -e "$APP_DIR" ] && [ -n "$(ls -A "$APP_DIR" 2>/dev/null)" ]; then
        die "$APP_DIR already exists and is not empty - PNLCS seems to be installed. Nothing was changed."
    fi
    [ -e "$NGINX_SITE" ] && die "$NGINX_SITE already exists. Nothing was changed."
    if command -v ss >/dev/null && ss -ltnp 2>/dev/null | grep -E '[:.]80\s' | grep -qv nginx; then
        die "port 80 is used by another program: $(ss -ltnp | grep -E '[:.]80\s' | grep -o 'users:(("[^"]*' | head -1 | cut -d'"' -f2). Nothing was changed."
    fi
    if [ "$EXISTING_DB" = 0 ] && db_client >/dev/null 2>&1; then
        if root_sql "SELECT 1" >/dev/null 2>&1; then
            [ -n "$(root_sql "SHOW DATABASES LIKE '$DB_NAME'")" ] && die "the database '$DB_NAME' already exists. Nothing was changed."
        else
            die "a database server is installed but root cannot sign in to it without a password. Create a database and user for PNLCS and run again with --db-password (and --db-name/--db-user). Nothing was changed."
        fi
    fi
    return 0
}

db_client() { command -v mariadb || command -v mysql; }
root_sql() { "$(db_client)" -N -B -e "$1" </dev/null; }

install_packages() {
    if [ "$FAMILY" = debian ]; then
        export DEBIAN_FRONTEND=noninteractive
        run apt-get update -q
        run apt-get install -y -q curl ca-certificates gnupg lsb-release unzip tar cron openssl
        if ! apt-cache show php8.4-fpm >/dev/null 2>&1; then
            if [ "$OS" = ubuntu ]; then
                run apt-get install -y -q software-properties-common
                run add-apt-repository -y ppa:ondrej/php
            else
                run curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg
                echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $OS_CODENAME main" >/etc/apt/sources.list.d/sury-php.list
            fi
            run apt-get update -q
        fi
        run apt-get install -y -q php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
            php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl nginx
        # Its own package on some systems, part of PHP on others.
        run apt-get install -y -q php8.4-opcache || true
        if [ "$EXISTING_DB" = 0 ] && ! db_client >/dev/null; then
            if [ "$OS" = ubuntu ]; then run apt-get install -y -q mysql-server; else run apt-get install -y -q mariadb-server; fi
        fi
        run systemctl enable --now "$FPM_SERVICE" nginx "$CRON_SERVICE"
    else
        run dnf install -y -q epel-release
        rpm -q remi-release >/dev/null 2>&1 || run dnf install -y -q "https://rpms.remirepo.net/enterprise/remi-release-${MAJOR}.rpm"
        run dnf module reset -y -q php
        run dnf module enable -y -q php:remi-8.4
        run dnf install -y -q php php-fpm php-cli php-mysqlnd php-mbstring php-xml php-gd php-bcmath php-intl \
            php-pecl-zip php-opcache php-process nginx tar unzip cronie openssl policycoreutils-python-utils
        if [ "$EXISTING_DB" = 0 ] && ! db_client >/dev/null; then run dnf install -y -q mysql-server; fi
        run systemctl enable --now php-fpm nginx crond
    fi
    if [ "$EXISTING_DB" = 0 ]; then
        local svc
        for svc in mysql mysqld mariadb; do
            if systemctl list-unit-files "$svc.service" >/dev/null 2>&1 && systemctl cat "$svc.service" >/dev/null 2>&1; then
                run systemctl enable --now "$svc"; break
            fi
        done
    fi
}

# nginx takes 64 MB (ticket attachments, backups); PHP took 2 MB.
configure_php() {
    cat >"$PHP_INI" <<'INI'
; PNLCS (install.sh)
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 256M
INI
    run systemctl restart "$FPM_SERVICE"
}

download_release() {
    WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
    if [ -z "$PNLCS_VERSION" ]; then
        PNLCS_VERSION="$(latest_release)"
        [ -n "$PNLCS_VERSION" ] || die "could not find the latest release on GitHub - name one with --version"
    fi
    say "Downloading PNLCS $PNLCS_VERSION"
    local base="$RELEASES/download/v$PNLCS_VERSION" pkg="pnlcs-$PNLCS_VERSION.tar.gz"
    run curl -fsSL -o "$WORK/$pkg" "$base/$pkg" || die "could not download $base/$pkg"
    run curl -fsSL -o "$WORK/release.json" "$base/pnlcs-$PNLCS_VERSION.release.json" || die "could not download the release's signed statement"
    run curl -fsSL -o "$WORK/release.sig" "$base/pnlcs-$PNLCS_VERSION.release.json.sig" || die "could not download the release's signature"

    printf '%s\n' "$RELEASE_KEY" >"$WORK/key.pem"
    base64 -d "$WORK/release.sig" >"$WORK/release.sig.bin" 2>/dev/null || die "the release signature is not readable"
    openssl dgst -sha256 -verify "$WORK/key.pem" -signature "$WORK/release.sig.bin" "$WORK/release.json" >>"$LOG" 2>&1 \
        || die "the signature of PNLCS $PNLCS_VERSION does not match the PNLCS release key - not installed"
    local want got
    want="$("$PHP_BIN" -r '$r = json_decode(file_get_contents($argv[1]), true); echo ($r["version"] ?? "")." ".($r["package"] ?? "")." ".($r["sha256"] ?? "");' "$WORK/release.json")"
    got="$PNLCS_VERSION $pkg $(sha256sum "$WORK/$pkg" | cut -d' ' -f1)"
    [ "$want" = "$got" ] || die "the package does not match its signed statement - not installed"
    say "Signature and checksum verified"

    # The release names the PHP extensions it needs.
    local missing
    missing="$("$PHP_BIN" -r '$r = json_decode(file_get_contents($argv[1]), true); echo implode(" ", array_filter($r["requires"]["extensions"] ?? [], fn ($e) => !extension_loaded($e)));' "$WORK/release.json")"
    [ -z "$missing" ] || die "PHP is missing extensions PNLCS $PNLCS_VERSION needs: $missing"
}

# The newest stable release: from the API, or from the page releases/latest
# redirects to once the API's hourly limit for this address is used up.
latest_release() {
    local tag
    tag="$(curl -fsSL "$REPO_API/latest" 2>>"$LOG" | "$PHP_BIN" -r 'echo json_decode(stream_get_contents(STDIN), true)["tag_name"] ?? "";' 2>>"$LOG" || true)"
    [ -n "$tag" ] || tag="$(curl -fsSL -o /dev/null -w '%{url_effective}' "$RELEASES/latest" 2>>"$LOG" | sed -n 's|.*/releases/tag/||p' || true)"
    echo "${tag#v}"
}

setup_database() {
    if [ "$EXISTING_DB" = 1 ]; then
        "$PHP_BIN" -r 'try { new PDO("mysql:host=".$argv[1].";port=".$argv[2].";dbname=".$argv[3], $argv[4], $argv[5]); } catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }' \
            "$DB_HOST" "$DB_PORT" "$DB_NAME" "$DB_USER" "$DB_PASSWORD" 2>>"$LOG" || die "cannot sign in to the database $DB_NAME as $DB_USER on $DB_HOST:$DB_PORT"
        say "Using the existing database $DB_NAME"
        return
    fi
    root_sql "SELECT 1" >/dev/null 2>&1 || die "root cannot sign in to the new database server"
    [ -n "$(root_sql "SHOW DATABASES LIKE '$DB_NAME'")" ] && die "the database '$DB_NAME' already exists"
    DB_PASSWORD="$(openssl rand -hex 16)"
    root_sql "CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
              CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
              CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
              ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
              ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
              GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
              GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
              FLUSH PRIVILEGES;" >>"$LOG" 2>&1 || die "could not create the database"
    say "Created the database $DB_NAME and its user"
}

as_web() { runuser -u "$WEB_USER" -- env HOME="$APP_DIR/storage" "$@" </dev/null; }

install_app() {
    say "Installing PNLCS into $APP_DIR"
    run tar -xzf "$WORK/pnlcs-$PNLCS_VERSION.tar.gz" -C "$WORK"
    mkdir -p "$(dirname "$APP_DIR")"
    [ -e "$APP_DIR" ] && rmdir "$APP_DIR"
    mv "$WORK/pnlcs" "$APP_DIR"
    chown -R "$WEB_USER:$WEB_USER" "$APP_DIR"
    cd "$APP_DIR"

    if [ -n "$DOMAIN" ]; then APP_URL="http://$DOMAIN"; else APP_URL="http://$(server_ip)"; fi
    as_web cp .env.example .env
    # Written as the install wizard writes them (app/Support/EnvWriter.php): a
    # value that is not a plain word is quoted, so a --db-password with a space
    # or a # in it survives.
    as_web "$PHP_BIN" -r '
        require "vendor/autoload.php";
        $values = [];
        foreach (array_slice($argv, 1) as $pair) {
            [$key, $value] = explode("=", $pair, 2);
            $values[$key] = $value;
        }
        file_put_contents(".env", App\Support\EnvWriter::apply(file_get_contents(".env"), $values));' \
        APP_ENV=production APP_DEBUG=false "APP_URL=$APP_URL" DB_CONNECTION=mysql "DB_HOST=$DB_HOST" "DB_PORT=$DB_PORT" \
        "DB_DATABASE=$DB_NAME" "DB_USERNAME=$DB_USER" "DB_PASSWORD=$DB_PASSWORD"
    chmod 640 .env

    say "Creating the database tables (a minute or two)"
    as_web "$PHP_BIN" artisan key:generate --force >>"$LOG" 2>&1
    as_web "$PHP_BIN" artisan migrate --force >>"$LOG" 2>&1 || die "the database tables could not be created"
    as_web "$PHP_BIN" artisan storage:link >>"$LOG" 2>&1 || true
    as_web "$PHP_BIN" artisan optimize >>"$LOG" 2>&1
}

server_ip() {
    local ip
    ip="$(ip -4 route get 1.1.1.1 2>/dev/null | sed -n 's/.* src \([0-9.]*\).*/\1/p' | head -1)"
    [ -n "$ip" ] || ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
    echo "${ip:-127.0.0.1}"
}

configure_nginx() {
    local default="" name="$DOMAIN" listen
    # Without a domain: the server's IP as the name, and every other name as
    # the default server ("_" is taken by RHEL's own default site).
    if [ -z "$DOMAIN" ]; then default=" default_server"; name="$(server_ip)"; fi
    listen="listen 80$default;"
    # IPv6 too, where the system has it (nginx -t fails on a v6 address otherwise).
    [ -f /proc/net/if_inet6 ] && listen="$listen
    listen [::]:80$default;"
    cat >"$NGINX_SITE" <<NGINX
# PNLCS (install.sh) - see docs/install/native.md
server {
    $listen
    server_name $name;
    root $APP_DIR/public;

    index index.php;
    charset utf-8;
    client_max_body_size 64M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:$FPM_SOCK;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
    if [ "$FAMILY" = debian ]; then
        ln -sf "$NGINX_SITE" /etc/nginx/sites-enabled/pnlcs
        # Without a domain PNLCS answers the server's IP, which the stock
        # "Welcome to nginx" site claims too (nginx -t: duplicate default server).
        if [ -z "$DOMAIN" ] && [ -L /etc/nginx/sites-enabled/default ]; then rm -f /etc/nginx/sites-enabled/default; fi
    elif [ -z "$DOMAIN" ]; then
        # RHEL's nginx.conf has its own default server on port 80.
        sed -i 's/listen\(\s*\)80 default_server;/listen\180;/; s/listen\(\s*\)\[::\]:80 default_server;/listen\1[::]:80;/' /etc/nginx/nginx.conf
    fi
    nginx -t >>"$LOG" 2>&1 || die "the nginx configuration does not pass nginx -t"
    run systemctl reload nginx
}

configure_selinux() {
    [ "$FAMILY" = rhel ] && command -v getenforce >/dev/null && [ "$(getenforce)" = Enforcing ] || return 0
    say "SELinux: letting PHP-FPM write storage/ and reach the database and the network"
    run semanage fcontext -a -t httpd_sys_rw_content_t "$APP_DIR/storage(/.*)?" || true
    run semanage fcontext -a -t httpd_sys_rw_content_t "$APP_DIR/bootstrap/cache(/.*)?" || true
    run semanage fcontext -a -t httpd_sys_rw_content_t "$APP_DIR/\.env" || true
    run restorecon -R "$APP_DIR"
    run setsebool -P httpd_can_network_connect_db 1
    run setsebool -P httpd_can_network_connect 1
}

# Invoices, reminders, suspensions, queued mail: all started by the scheduler.
configure_cron() {
    local line="* * * * * cd $APP_DIR && $PHP_BIN artisan schedule:run >> /dev/null 2>&1"
    { crontab -u "$WEB_USER" -l 2>/dev/null | grep -vF "$APP_DIR && " || true; echo "$line"; } | crontab -u "$WEB_USER" -
    say "Scheduler: cron for $WEB_USER"
}

open_firewall() {
    if command -v ufw >/dev/null && ufw status 2>/dev/null | grep -q "Status: active"; then
        run ufw allow 80/tcp; run ufw allow 443/tcp
        say "Firewall (ufw): opened ports 80 and 443"
    elif command -v firewall-cmd >/dev/null && firewall-cmd --state >/dev/null 2>&1; then
        run firewall-cmd --permanent --add-service=http --add-service=https; run firewall-cmd --reload
        say "Firewall (firewalld): opened http and https"
    fi
}

verify() {
    local host="${DOMAIN:-localhost}" code="" try
    # nginx takes up a reload a moment after the command returns: a request
    # sent straight after it reached the old configuration 19 times out of 20.
    for try in $(seq 1 15); do
        code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $host" http://127.0.0.1/install/requirements || true)"
        [ "$code" = 200 ] && break
        sleep 1
    done
    if [ "$code" != 200 ]; then
        curl -s -i -H "Host: $host" http://127.0.0.1/install/requirements 2>&1 | head -n 20 >>"$LOG" || true
        die "the install wizard does not answer (HTTP $code). See $LOG and /var/log/nginx/error.log"
    fi
    code="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $host" http://127.0.0.1/.env || true)"
    [ "$code" = 403 ] || [ "$code" = 404 ] || die ".env is reachable over HTTP ($code) - check $NGINX_SITE"
}

summary() {
    local creds=/root/pnlcs-install.txt
    umask 077
    cat >"$creds" <<EOF
PNLCS $PNLCS_VERSION - installed $(date -Is)
Directory:  $APP_DIR
Site:       $APP_URL
Database:   $DB_NAME on $DB_HOST:$DB_PORT, user $DB_USER, password $DB_PASSWORD
Log:        $LOG
EOF
    echo
    say "PNLCS $PNLCS_VERSION is installed."
    echo "    Open $APP_URL/install in your browser to create your administrator account."
    [ -z "$DOMAIN" ] || echo "    For HTTPS: install certbot and run: certbot --nginx -d $DOMAIN (then set APP_URL to https:// in $APP_DIR/.env and run: cd $APP_DIR && sudo -u $WEB_USER php artisan optimize)"
    echo "    The database password and these details are in $creds (readable by root only)."
}

main "$@"
