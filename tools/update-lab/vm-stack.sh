#!/usr/bin/env bash
# Runs ON a fresh test machine (as root): installs what docs/install/native.md
# step 0 installs, on the systems it covers, plus Debian 12 and Ubuntu 22.04
# (PHP 8.4 from the sury repository / ondrej PPA, as those guides do), and
# prints the facts the other lab scripts need:
#   WEB_USER=... FPM_SOCK=... FPM_SERVICE=... CRON_SERVICE=... DB_CLIENT=...
# Idempotent: a second run only prints the facts.
set -euo pipefail
. /etc/os-release
FAMILY=debian; case "$ID" in almalinux|rocky|rhel|centos) FAMILY=rhel ;; esac

if [ ! -f /root/.pnlcs-lab-stack ]; then
  if [ "$FAMILY" = debian ]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -q
    apt-get install -y -q git unzip curl cron ca-certificates lsb-release gnupg software-properties-common >/dev/null 2>&1 || apt-get install -y -q git unzip curl cron ca-certificates lsb-release gnupg >/dev/null
    if ! apt-cache show php8.4-fpm >/dev/null 2>&1; then
      if [ "$ID" = ubuntu ]; then
        add-apt-repository -y ppa:ondrej/php >/dev/null
      else
        curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg
        echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $VERSION_CODENAME main" > /etc/apt/sources.list.d/sury-php.list
      fi
      apt-get update -q
    fi
    apt-get install -y -q php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl >/dev/null
    if apt-cache show mysql-server >/dev/null 2>&1 && [ "$ID" = ubuntu ]; then apt-get install -y -q mysql-server nginx >/dev/null; else apt-get install -y -q mariadb-server nginx >/dev/null; fi
    # Node and Composer: only the git-installed scenario builds anything.
    command -v node >/dev/null || { curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null 2>&1; apt-get install -y -q nodejs >/dev/null; }
  else
    dnf install -y -q epel-release "https://rpms.remirepo.net/enterprise/remi-release-${VERSION_ID%%.*}.rpm" >/dev/null
    dnf module reset php -y -q >/dev/null
    dnf module enable php:remi-8.4 -y -q >/dev/null
    dnf install -y -q php php-fpm php-mysqlnd php-mbstring php-xml php-gd php-bcmath php-intl php-pecl-zip \
      mysql-server nginx git unzip cronie policycoreutils-python-utils tar >/dev/null
    command -v node >/dev/null || { curl -fsSL https://rpm.nodesource.com/setup_20.x | bash - >/dev/null 2>&1; dnf install -y -q nodejs >/dev/null; }
    systemctl enable --now php-fpm mysqld nginx crond >/dev/null 2>&1
  fi
  command -v composer >/dev/null || { curl -sS https://getcomposer.org/installer | php -- --quiet && mv composer.phar /usr/local/bin/composer; }
  touch /root/.pnlcs-lab-stack
fi

if [ "$FAMILY" = debian ]; then
  echo "WEB_USER=www-data FPM_SOCK=/run/php/php8.4-fpm.sock FPM_SERVICE=php8.4-fpm CRON_SERVICE=cron NGINX_CONF=/etc/nginx/sites-available/pnlcs"
else
  echo "WEB_USER=apache FPM_SOCK=/run/php-fpm/www.sock FPM_SERVICE=php-fpm CRON_SERVICE=crond NGINX_CONF=/etc/nginx/conf.d/pnlcs.conf"
fi
