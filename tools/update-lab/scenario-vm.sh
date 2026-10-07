#!/usr/bin/env bash
# The update on a real server: a fresh Debian, Ubuntu, AlmaLinux or Rocky
# machine set up as docs/install/native.md describes (nginx + PHP-FPM + MySQL
# or MariaDB + cron; SELinux enforcing on RHEL), driven over SSH and HTTP.
#
#   LAB_VM_IP=192.168.1.141 LAB_VM_PASS=... scenario-vm.sh
#
# 1. command line: the conflict is found; "keep mine"; updated; every operator
#    file kept; nginx/PHP-FPM serve the new version
# 2. admin area: a release whose migration fails; rolled back by itself;
#    files and tables as before
# 3. crash during the migrations and nobody acts: the site shows maintenance,
#    updates refuse, and the scheduler's self-healing puts every file and
#    table back and brings the site up
# 4. today's installations: cloned with git and built with composer and npm,
#    updated from the admin area, the conflict edited on the page
set -uo pipefail
LAB="$(cd "$(dirname "$0")" && pwd)"; WORK="$LAB/.work"; REPO="$(git -C "$LAB" rev-parse --show-toplevel)"
IP="${LAB_VM_IP:?LAB_VM_IP}"; PASSWD="${LAB_VM_PASS:?LAB_VM_PASS}"
DIR="$WORK/vm-$IP"; mkdir -p "$DIR"; APP=/var/www/pnlcs
PASS=0; FAIL=0
ok()  { echo "PASS  $*"; PASS=$((PASS+1)); }
bad() { echo "FAIL  $*"; FAIL=$((FAIL+1)); }
SSHO=(-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=ERROR -o ConnectTimeout=15)
vm()  { sshpass -p "$PASSWD" ssh "${SSHO[@]}" "root@$IP" "$@"; }
put() { sshpass -p "$PASSWD" scp -q "${SSHO[@]}" "$@"; }

echo "== $IP: copying the lab"
vm "mkdir -p /opt/lab/releases && chmod 755 /opt/lab"
for d in A B-good B-bad-migration; do put -r "$WORK/releases/$d" "root@$IP:/opt/lab/releases/"; done
for v in good bad-migration; do sed "s#file://$WORK/#file:///opt/lab/#g" "$WORK/index-$v.json" > "$DIR/index-$v.json"; done
# The git installation is a clone of the commit release A was built from.
A_COMMIT="$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["commit"] ?? "";' "$WORK/releases/A/pnlcs-1.3.0/.pnlcs-release.json" 2>/dev/null)"
[ -n "$A_COMMIT" ] || A_COMMIT="$(tar -xzOf "$WORK/releases/A/pnlcs-1.3.0.tar.gz" pnlcs/.pnlcs-release.json | php -r 'echo json_decode(stream_get_contents(STDIN), true)["commit"] ?? "";')"
rm -f "$DIR/repo.bundle"
git -C "$REPO" branch -f lab-main "$A_COMMIT" >/dev/null && git -C "$REPO" bundle create -q "$DIR/repo.bundle" lab-main; git -C "$REPO" branch -D lab-main >/dev/null 2>&1
[ -s "$DIR/repo.bundle" ] || { echo "no git bundle of $A_COMMIT"; exit 1; }
put "$DIR/index-good.json" "$DIR/index-bad-migration.json" "$DIR/repo.bundle" "$WORK/lab-key.pub" "$LAB/customise.php" "$LAB/snapshot.php" "$LAB/compare.php" "$LAB/vm-install.sh" "$LAB/vm-stack.sh" "root@$IP:/opt/lab/"
vm "chmod -R a+rX /opt/lab && chmod +x /opt/lab/vm-install.sh /opt/lab/vm-stack.sh"

echo "== $IP: the stack (docs/install/native.md step 0)"
FACTS="$(vm /opt/lab/vm-stack.sh 2>"$DIR/stack.err" | tail -1)"
case "$FACTS" in WEB_USER=*) eval "$FACTS" ;; *) bad "stack: $(tail -3 "$DIR/stack.err")"; echo "== vm $IP: $PASS passed, $FAIL failed"; exit 1 ;; esac
OS="$(vm '. /etc/os-release; echo $PRETTY_NAME')"; SELINUX="$(vm 'getenforce 2>/dev/null || echo none')"
ok "stack ready on $OS (web user $WEB_USER, SELinux $SELINUX)"

www() { vm "cd $APP && runuser -u $WEB_USER -- env HOME=/tmp/pnlcs-home $*"; }
snap() { www php /opt/lab/snapshot.php $APP > "$1"; }
http() { curl -s -o /dev/null -w '%{http_code}' --max-time 20 "http://$IP$1"; }
same() { php -r '$a=json_decode(file_get_contents($argv[1]),true)["files"];$b=json_decode(file_get_contents($argv[2]),true)["files"]; exit(($a[$argv[3]]??"x")===($b[$argv[3]]??"y")?0:1);' "$@"; }
status() { vm cat $APP/storage/app/pnlcs-update/status.json 2>/dev/null; }
wait_state() { S=""; for i in $(seq 1 "${2:-60}"); do S="$(status)"; vm test -f $APP/storage/app/pnlcs-update/request.json || { grep -Eq "\"state\": \"($1)\"" <<< "$S" && return 0; }; sleep 5; done; return 1; }
customise() { www php /opt/lab/customise.php $APP >> "$DIR/customise.log" 2>&1; www php artisan config:cache >> "$DIR/customise.log" 2>&1; }
KEPT=(.env themes/acme/views/sections/footer.blade.php modules/Servers/LabMine/pnlcs.json app/Hooks/lab-operator.php storage/app/public/logo.png)
IGNORE=(--ignore-table=activity_logs --ignore-table=scheduled_task_runs --ignore-row=settings.LastCronRun)
JAR="$DIR/cookies"
tok()  { grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
post() { local p="$1"; shift; curl -s -o /dev/null -w '%{http_code}' --max-time 30 -b "$JAR" -c "$JAR" -X POST "http://$IP$p" --data-urlencode "_token=$T" "$@"; }
refresh() { T="$(curl -s --max-time 30 -b "$JAR" -c "$JAR" "http://$IP/admin/config/updates" | tok)"; }
signin() { rm -f "$JAR"; T="$(curl -s --max-time 30 -b "$JAR" -c "$JAR" "http://$IP/admin/login" | tok)"; [ "$(post /admin/login --data-urlencode username=admin --data-urlencode password=admin123)" = 302 ]; }

echo "== 1. command line"
vm /opt/lab/vm-install.sh good "$IP" package > "$DIR/install1.log" 2>&1 && ok "1.3.0 installed from its package" || bad "install (see $DIR/install1.log)"
[ "$(http /admin/login)" = 200 ] && ok "nginx + PHP-FPM serve the admin login" || bad "admin login over HTTP"
customise; snap "$DIR/before1.json"
www php artisan pnlcs:update --check > "$DIR/check1.out" 2>&1; [ $? -eq 2 ] && ok "check refuses on the conflict" || bad "check exit ($(tail -2 "$DIR/check1.out"))"
grep -q "conflict  public/robots.txt" "$DIR/check1.out" && ok "the conflict is robots.txt" || bad "robots conflict"
www php artisan pnlcs:update --yes --resolve public/robots.txt=mine > "$DIR/update1.out" 2>&1; [ $? -eq 0 ] && ok "update succeeds" || bad "update exit ($(tail -3 "$DIR/update1.out"))"
[ "$(vm cat $APP/VERSION)" = 1.3.1 ] && ok "VERSION 1.3.1" || bad "VERSION"
snap "$DIR/after1.json"
for f in "${KEPT[@]}" public/robots.txt; do same "$DIR/before1.json" "$DIR/after1.json" "$f" && ok "kept: $f" || bad "changed: $f"; done
[ "$(vm stat -c %U $APP/app/Support/LabProbe.php)" = "$WEB_USER" ] && ok "new files belong to $WEB_USER" || bad "file owner"
[ "$(http /admin/login)" = 200 ] && [ "$(http /)" = 200 ] && ok "nginx + PHP-FPM serve 1.3.1" || bad "HTTP after update"

echo "== 2. admin area, a release whose migration fails"
vm /opt/lab/vm-install.sh bad-migration "$IP" package > "$DIR/install2.log" 2>&1 || bad "reinstall"
customise
signin && ok "admin signs in" || bad "sign-in"
refresh; post /admin/config/updates/check >/dev/null
refresh; START=$(date +%s); [ "$(post /admin/config/updates/prepare)" = 302 ] || bad "prepare"
wait_state "ready|error" && ok "the check ran ($(( $(date +%s) - START )) s after the click)" || bad "the check did not run: $S"
refresh; [ "$(post /admin/config/updates/resolve --data-urlencode 'choice[0]=mine')" = 302 ] && ok "decision saved: keep mine" || bad "resolve"
snap "$DIR/before2.json"
refresh; [ "$(post /admin/config/updates/apply)" = 302 ] && ok "Update now requested" || bad "apply request"
wait_state "rolled_back|updated|refused|failed|rollback_failed|error"
grep -q '"state": "rolled_back"' <<< "$S" && ok "the migration failed and it was rolled back by itself" || bad "state: $S"
snap "$DIR/after2.json"
php "$LAB/compare.php" "$DIR/before2.json" "$DIR/after2.json" "${IGNORE[@]}" > "$DIR/compare2.out" 2>&1 && ok "every file and every table as before" || { bad "differs"; head "$DIR/compare2.out"; }
[ "$(vm cat $APP/VERSION)" = 1.3.0 ] && [ "$(http /admin/login)" = 200 ] && ok "1.3.0, site up" || bad "after rollback"

echo "== 3. crash during the migrations, nobody acts"
vm /opt/lab/vm-install.sh good "$IP" package > "$DIR/install3.log" 2>&1 || bad "reinstall"
customise; snap "$DIR/before3.json"
# Stop cron for the instant checks, so the self-healing cannot win the race.
vm systemctl stop "$CRON_SERVICE"
www env PNLCS_UPDATE_LAB_KILL_AT=migrating php artisan pnlcs:update --yes --resolve public/robots.txt=mine > "$DIR/update3.out" 2>&1; [ $? -ne 0 ] && ok "the update died part way (simulated)" || bad "no crash"
[ "$(http /admin/login)" = 503 ] && ok "visitors get the maintenance page, not a half-updated site" || bad "site state after the crash"
www php artisan pnlcs:update --yes > "$DIR/blocked3.out" 2>&1 && bad "an update ran over the unfinished one" || ok "updates refuse while one is unfinished"
vm systemctl start "$CRON_SERVICE"
HEALED=0; for i in $(seq 1 36); do sleep 5; if vm "grep -q '\"phase\": \"rolled_back\"' $APP/storage/app/pnlcs-update/current-run.json" 2>/dev/null; then HEALED=$((i*5)); break; fi; done
[ $HEALED -gt 0 ] && ok "the scheduler healed it by itself (${HEALED} s after cron came back)" || bad "no self-healing within 3 minutes"
snap "$DIR/after3.json"
php "$LAB/compare.php" "$DIR/before3.json" "$DIR/after3.json" "${IGNORE[@]}" > "$DIR/compare3.out" 2>&1 && ok "every file and every table as before" || { bad "differs"; head "$DIR/compare3.out"; }
[ "$(http /admin/login)" = 200 ] && ok "site up again" || bad "site after self-heal"

echo "== 4. an installation cloned with git, updated from the admin area"
vm /opt/lab/vm-install.sh good "$IP" git > "$DIR/install4.log" 2>&1 && ok "cloned with git, built with composer and npm" || bad "git install (see $DIR/install4.log)"
customise; snap "$DIR/before4.json"
signin || bad "sign-in"
refresh; post /admin/config/updates/check >/dev/null
refresh; [ "$(post /admin/config/updates/prepare)" = 302 ] || bad "prepare"
wait_state "ready|error" || bad "the check did not run: $S"
curl -s --max-time 30 -b "$JAR" "http://$IP/admin/config/updates" > "$DIR/page4.html"
grep -q "set up with git" "$DIR/page4.html" && ok "the page says it is a git installation" || bad "no git note"
EDITED=$'User-agent: *\nDisallow: /operator-private\n# merged by hand on the page\nDisallow:\n'
refresh; [ "$(post /admin/config/updates/resolve --data-urlencode 'choice[0]=edited' --data-urlencode "resolved_text[0]=$EDITED" --data-urlencode then=apply)" = 302 ] && ok "Save and update" || bad "save and update"
wait_state "updated|rolled_back|refused|failed|rollback_failed|error"
grep -q '"state": "updated"' <<< "$S" && ok "updated" || bad "state: $S"
[ "$(vm cat $APP/VERSION)" = 1.3.1 ] && ok "VERSION 1.3.1" || bad "VERSION"
[ "$(vm sha256sum $APP/public/robots.txt | cut -d' ' -f1)" = "$(printf '%s' "$EDITED" | sha256sum | cut -d' ' -f1)" ] && ok "robots.txt is exactly the text edited on the page" || bad "robots.txt"
vm test -d $APP/.git && ok ".git left in place" || bad ".git"
vm test -f $APP/tests/Pest.php && ok "tests/ left in place (a release leaves it out, so an update never removes it)" || bad "tests/ removed"
snap "$DIR/after4.json"
for f in "${KEPT[@]}"; do same "$DIR/before4.json" "$DIR/after4.json" "$f" && ok "kept: $f" || bad "changed: $f"; done
[ "$(http /admin/login)" = 200 ] && [ "$(http /)" = 200 ] && ok "site serves 1.3.1" || bad "HTTP after update"

echo "== vm $IP ($OS): $PASS passed, $FAIL failed"
[ $FAIL -eq 0 ]
