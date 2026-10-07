#!/usr/bin/env bash
# The update on a real server: a Debian/Ubuntu machine set up as
# docs/install/native.md describes (nginx + PHP-FPM + MariaDB + cron), driven
# from here over SSH and HTTP.
#
#   LAB_VM_IP=192.168.1.140 LAB_VM_PASS=... scenario-vm.sh
#
# 1. command line: the conflict is found; "keep mine"; updated; every
#    operator file kept; nginx/PHP-FPM serve the new version
# 2. admin area + cron: a release whose migration fails is requested from
#    Setup -> Updates; cron runs it; it is rolled back: files and tables as before
# 3. crash: the update dies during the migrations; the site shows maintenance;
#    updates refuse; the rollback command restores everything
set -uo pipefail
LAB="$(cd "$(dirname "$0")" && pwd)"; WORK="$LAB/.work"
IP="${LAB_VM_IP:?LAB_VM_IP}"; PASSWD="${LAB_VM_PASS:?LAB_VM_PASS}"
DIR="$WORK/vm"; mkdir -p "$DIR"; APP=/var/www/pnlcs
PASS=0; FAIL=0
ok()  { echo "PASS  $*"; PASS=$((PASS+1)); }
bad() { echo "FAIL  $*"; FAIL=$((FAIL+1)); }
SSHO=(-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR)
vm()  { sshpass -p "$PASSWD" ssh "${SSHO[@]}" "root@$IP" "$@"; }
put() { sshpass -p "$PASSWD" scp -q "${SSHO[@]}" "$@"; }
www() { vm "cd $APP && runuser -u www-data -- $*"; }
snap() { www php /opt/lab/snapshot.php $APP > "$1"; }
http() { curl -s -o /dev/null -w '%{http_code}' "http://$IP$1"; }
same() { php -r '$a=json_decode(file_get_contents($argv[1]),true)["files"];$b=json_decode(file_get_contents($argv[2]),true)["files"]; exit(($a[$argv[3]]??"x")===($b[$argv[3]]??"y")?0:1);' "$@"; }

echo "== copying the lab releases to $IP"
vm "mkdir -p /opt/lab/releases && chmod 755 /opt/lab"
for d in A B-good B-bad-migration; do put -r "$WORK/releases/$d" "root@$IP:/opt/lab/releases/"; done
for v in good bad-migration; do sed "s#file://$WORK/#file:///opt/lab/#g" "$WORK/index-$v.json" > "$DIR/index-$v.json"; done
put "$DIR/index-good.json" "$DIR/index-bad-migration.json" "$WORK/lab-key.pub" "$LAB/customise.php" "$LAB/snapshot.php" "$LAB/compare.php" "$LAB/vm-install.sh" "root@$IP:/opt/lab/"
vm "chmod -R a+rX /opt/lab && chmod +x /opt/lab/vm-install.sh"

echo "== 1. command line"
vm /opt/lab/vm-install.sh good "$IP" > "$DIR/install1.log" 2>&1 && ok "1.3.0 installed natively (nginx, PHP-FPM, MariaDB, cron)" || bad "install (see $DIR/install1.log)"
[ "$(http /admin/login)" = 200 ] && ok "nginx + PHP-FPM serve the admin login" || bad "admin login over HTTP"
www php /opt/lab/customise.php $APP >> "$DIR/s1.log" 2>&1; www php artisan config:cache >> "$DIR/s1.log" 2>&1
snap "$DIR/before1.json"
www php artisan pnlcs:update --check > "$DIR/check1.out" 2>&1; [ $? -eq 2 ] && ok "check refuses on the conflict" || bad "check exit"
grep -q "conflict  public/robots.txt" "$DIR/check1.out" && ok "the conflict is robots.txt" || bad "robots conflict"
www php artisan pnlcs:update --yes --resolve public/robots.txt=mine > "$DIR/update1.out" 2>&1; [ $? -eq 0 ] && ok "update succeeds" || bad "update exit"
[ "$(vm cat $APP/VERSION)" = 1.3.1 ] && ok "VERSION 1.3.1" || bad "VERSION"
snap "$DIR/after1.json"
for f in .env themes/acme/views/sections/footer.blade.php modules/Servers/LabMine/pnlcs.json app/Hooks/lab-operator.php storage/app/public/logo.png public/robots.txt; do
    same "$DIR/before1.json" "$DIR/after1.json" "$f" && ok "kept: $f" || bad "changed: $f"
done
[ "$(vm stat -c %U $APP/app/Support/LabProbe.php)" = www-data ] && ok "new files belong to www-data" || bad "file owner"
[ "$(http /admin/login)" = 200 ] && [ "$(http /)" = 200 ] && ok "nginx + PHP-FPM serve 1.3.1 (/ and /admin/login 200)" || bad "HTTP after update"

echo "== 2. admin area + cron, a release whose migration fails"
vm /opt/lab/vm-install.sh bad-migration "$IP" > "$DIR/install2.log" 2>&1 || bad "reinstall"
www php /opt/lab/customise.php $APP >> "$DIR/s2.log" 2>&1; www php artisan config:cache >> "$DIR/s2.log" 2>&1
JAR="$DIR/cookies"; rm -f "$JAR"
tok()  { grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
post() { local p="$1"; shift; curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" -X POST "http://$IP$p" --data-urlencode "_token=$T" "$@"; }
refresh() { T="$(curl -s -b "$JAR" -c "$JAR" "http://$IP/admin/config/updates" | tok)"; }
wait_state() { S=""; for i in $(seq 1 60); do S="$(vm cat $APP/storage/app/pnlcs-update/status.json 2>/dev/null)"; vm test -f $APP/storage/app/pnlcs-update/request.json || { grep -Eq "\"state\": \"($1)\"" <<< "$S" && return 0; }; sleep 5; done; return 1; }
T="$(curl -s -b "$JAR" -c "$JAR" "http://$IP/admin/login" | tok)"
[ "$(post /admin/login --data-urlencode username=admin --data-urlencode password=admin123)" = 302 ] && ok "admin signs in" || bad "sign-in"
refresh; post /admin/config/updates/check >/dev/null
refresh; [ "$(post /admin/config/updates/prepare)" = 302 ] && ok "Check this update requested" || bad "prepare"
wait_state "ready|error" && ok "cron ran the check" || bad "cron did not run the check: $S"
refresh; [ "$(post /admin/config/updates/resolve --data-urlencode 'choice[0]=mine')" = 302 ] && ok "decision saved: keep mine" || bad "resolve"
snap "$DIR/before2.json"
refresh; [ "$(post /admin/config/updates/apply)" = 302 ] && ok "Update now requested" || bad "apply request"
wait_state "rolled_back|updated|refused|failed|rollback_failed|error"
grep -q '"state": "rolled_back"' <<< "$S" && ok "cron ran the update; the migration failed and it was rolled back" || bad "state: $S"
snap "$DIR/after2.json"
php "$LAB/compare.php" "$DIR/before2.json" "$DIR/after2.json" --ignore-table=activity_logs --ignore-table=scheduled_task_runs --ignore-row=settings.LastCronRun > "$DIR/compare2.out" 2>&1 && ok "every file and every table as before" || { bad "differs"; head "$DIR/compare2.out"; }
[ "$(vm cat $APP/VERSION)" = 1.3.0 ] && ok "VERSION still 1.3.0" || bad "VERSION"
[ "$(http /admin/login)" = 200 ] && ok "site up after the rollback" || bad "site after rollback"

echo "== 3. crash during the migrations"
vm /opt/lab/vm-install.sh good "$IP" > "$DIR/install3.log" 2>&1 || bad "reinstall"
www php /opt/lab/customise.php $APP >> "$DIR/s3.log" 2>&1; www php artisan config:cache >> "$DIR/s3.log" 2>&1
snap "$DIR/before3.json"
vm "cd $APP && runuser -u www-data -- env PNLCS_UPDATE_LAB_KILL_AT=migrating php artisan pnlcs:update --yes --resolve public/robots.txt=mine" > "$DIR/update3.out" 2>&1; [ $? -ne 0 ] && ok "the update died part way (simulated)" || bad "no crash"
[ "$(http /admin/login)" = 503 ] && ok "the site shows maintenance, not a half-updated page" || bad "site state after crash"
www php artisan pnlcs:update --yes > "$DIR/blocked3.out" 2>&1 && bad "an update ran over the unfinished one" || ok "updates refuse while one is unfinished"
www php artisan pnlcs:update-rollback > "$DIR/rollback3.out" 2>&1 && ok "rollback command" || bad "rollback command"
snap "$DIR/after3.json"
php "$LAB/compare.php" "$DIR/before3.json" "$DIR/after3.json" > "$DIR/compare3.out" 2>&1 && ok "every file and every table as before" || { bad "differs"; head "$DIR/compare3.out"; }
[ "$(http /admin/login)" = 200 ] && ok "site up after the rollback" || bad "site after rollback"

echo "== vm: $PASS passed, $FAIL failed"
[ $FAIL -eq 0 ]
