#!/usr/bin/env bash
# The admin-area path, end to end, as a browser drives it: Setup -> Updates,
# check, see the conflict, download the merged file, decide "keep mine",
# update, see the new version - with the scheduler inside the container doing
# the work, as it does on every installation.
#
#   scenario-ui.sh [<image>] [<host-port>]      (LAB_KEEP=1 leaves it running)
set -uo pipefail
IMAGE="${1:-pnlcs-runtime:1.5-candidate}"; PORT="${2:-18091}"
LAB="$(cd "$(dirname "$0")" && pwd)"; WORK="$LAB/.work"
NAME="ui"; DIR="$WORK/$NAME"; LOG="$DIR/scenario.log"; CT="pnlcs-lab-ui"; VOL="pnlcs_lab_ui"; APP=/var/www/pnlcs
DB_PORT="${LAB_DB_PORT:-33061}"; DB_USER="${LAB_DB_USER:-root}"; DB_PASS="${LAB_DB_PASS:-testroot}"; DB="pnlcs_lab_ui"
URL="http://127.0.0.1:$PORT"; JAR="$DIR/cookies"
PASS=0; FAIL=0
ok()  { echo "PASS  $*"; PASS=$((PASS+1)); }
bad() { echo "FAIL  $*"; FAIL=$((FAIL+1)); }
page() { curl -s -b "$JAR" -c "$JAR" "$URL$1"; }
token() { grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
post() { local path="$1"; shift; curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" -X POST "$URL$path" --data-urlencode "_token=$TOKEN" "$@"; }
wait_state() {  # until status.json says one of the given states and nothing is queued
    for i in $(seq 1 90); do
        local s; s="$(docker exec "$CT" cat $APP/storage/app/pnlcs-update/status.json 2>/dev/null)"
        if ! docker exec "$CT" test -f $APP/storage/app/pnlcs-update/request.json && grep -Eq "\"state\": \"($1)\"" <<< "$s"; then return 0; fi
        sleep 4
    done
    return 1
}

docker rm -f "$CT" >/dev/null 2>&1; docker volume rm "$VOL" >/dev/null 2>&1
rm -rf "$DIR"; mkdir -p "$DIR"; : > "$LOG"
php -r '$p=new PDO("mysql:host=127.0.0.1;port=$argv[1]",$argv[2],$argv[3]); $p->exec("DROP DATABASE IF EXISTS `$argv[4]`"); $p->exec("CREATE DATABASE `$argv[4]` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");' "$DB_PORT" "$DB_USER" "$DB_PASS" "$DB"
docker run -d --name "$CT" --add-host=host.docker.internal:host-gateway -p "$PORT:80" -v "$LAB:$LAB:ro" -v "$VOL:$APP" \
    -e DB_HOST=host.docker.internal -e DB_PORT="$DB_PORT" -e DB_DATABASE="$DB" -e DB_USERNAME="$DB_USER" -e DB_PASSWORD="$DB_PASS" \
    -e APP_URL="${LAB_APP_URL:-$URL}" -e PNLCS_VERSION=1.3.0 \
    -e PNLCS_UPDATE_INDEX_URL="file://$WORK/index-good.json" -e PNLCS_UPDATE_PUBLIC_KEY="$(base64 -w0 "$WORK/lab-key.pub")" \
    "$IMAGE" >>"$LOG" 2>&1
for i in $(seq 1 90); do docker logs "$CT" > "$DIR/.logs" 2>&1; grep -q "Handing off" "$DIR/.logs" && break; sleep 2; done
docker exec -u www-data -w $APP "$CT" php artisan db:seed --force >>"$LOG" 2>&1
docker exec "$CT" sh -c "date -Iseconds > $APP/storage/installed.lock; chown www-data:www-data $APP/storage/installed.lock"
docker exec -u www-data "$CT" php "$LAB/customise.php" "$APP" >>"$LOG" 2>&1
docker exec -u www-data -w $APP "$CT" php artisan config:cache >>"$LOG" 2>&1

TOKEN="$(page /admin/login | token)"
CODE="$(post /admin/login --data-urlencode username=admin --data-urlencode password=admin123)"
[ "$CODE" = 302 ] && ok "admin signs in" || bad "sign-in answered $CODE"
HTML="$(page /admin/config/updates)"; TOKEN="$(echo "$HTML" | token)"
grep -q "Installed version" <<< "$HTML" && grep -q "1.3.0" <<< "$HTML" && ok "Setup -> Updates shows 1.3.0" || bad "updates page"
grep -q 'admin/config/updates"' <<< "$HTML" && ok "the Setup menu links to Updates" || bad "no menu link"

[ "$(post /admin/config/updates/check)" = 302 ] && ok "check for updates" || bad "check"
HTML="$(page /admin/config/updates)"; TOKEN="$(echo "$HTML" | token)"
grep -q "PNLCS 1.3.1 is available" <<< "$HTML" && ok "1.3.1 is offered, with its notes" || bad "1.3.1 not offered"

[ "$(post /admin/config/updates/prepare)" = 302 ] && ok "check this update (queued for the scheduler)" || bad "prepare"
page /admin/config/updates > "$DIR/queued.html"; grep -Eq "Waiting to start|Preparing" "$DIR/queued.html" && ok "the page shows the request waiting or running" || bad "no queued state"
wait_state "ready|error" && ok "the scheduler ran the check" || bad "the scheduler did not run the check"
HTML="$(page /admin/config/updates)"; TOKEN="$(echo "$HTML" | token)"
grep -q "public/robots.txt" <<< "$HTML" && grep -q "Conflicts with your changes (1)" <<< "$HTML" && ok "the conflict is shown" || bad "conflict not shown"
grep -q "Merged with the new version (1)" <<< "$HTML" && ok "the merged admin layout is listed" || bad "merge not listed"
echo "$HTML" > "$DIR/report.html"; grep -q "acme replaces views" <<< "$HTML" && ok "the theme warning is shown" || bad "no theme warning"
grep -q "Update now" <<< "$HTML" && bad "Update now offered while a conflict is open" || ok "no Update now while the conflict is open"
grep -q "<<<<<<< your version" <<< "$(curl -s -b "$JAR" "$URL/admin/config/updates/merged?path=public/robots.txt")" && ok "the merged file downloads, with conflict markers" || bad "merged download"

# A file still holding conflict markers is refused; the clean edit is kept.
post /admin/config/updates/resolve --data-urlencode 'choice[0]=edited' --data-urlencode $'resolved_text[0]=<<<<<<< your version\nx\n=======\n>>>>>>> new version\n' >/dev/null
grep -q "conflict markers" <<< "$(page /admin/config/updates)" && ok "an edit that still has markers is refused" || bad "markers not refused"
EDITED=$'User-agent: *\nDisallow: /operator-private\n# merged by hand on the page\n'
HTML="$(page /admin/config/updates)"; TOKEN="$(token <<< "$HTML")"
[ "$(post /admin/config/updates/resolve --data-urlencode 'choice[0]=edited' --data-urlencode "resolved_text[0]=$EDITED")" = 302 ] && ok "decision saved: the file edited on the page" || bad "resolve"
[ "$(post /admin/config/updates/prepare)" = 302 ] || bad "second prepare"
wait_state "ready|error" || bad "second check did not run"
HTML="$(page /admin/config/updates)"; TOKEN="$(echo "$HTML" | token)"
grep -q "Nothing stands in the way" <<< "$HTML" && grep -q "Update now" <<< "$HTML" && ok "with the decision, the update is offered" || bad "update not offered after the decision"

[ "$(post /admin/config/updates/apply)" = 302 ] && ok "update now (queued)" || bad "apply"
wait_state "updated|rolled_back|refused|failed|rollback_failed|error" && ok "the scheduler ran the update" || bad "the update did not finish"
docker exec "$CT" cat $APP/storage/app/pnlcs-update/status.json >>"$LOG"
STATUS="$(curl -s -b "$JAR" "$URL/admin/config/updates/status")"
grep -q '"state":"updated"' <<< "$STATUS" && ok "status endpoint: updated" || bad "status: $STATUS"
HTML="$(page /admin/config/updates)"
grep -q '1.3.1</div>' <<< "$HTML" && ok "the page now shows 1.3.1 installed" || bad "installed version on page"
grep -q ">Updated<" <<< "$HTML" && ok "the history lists the update" || bad "history"
[ "$(docker exec "$CT" sha256sum $APP/public/robots.txt | cut -d' ' -f1)" = "$(printf '%s' "$EDITED" | sha256sum | cut -d' ' -f1)" ] && ok "robots.txt is exactly the file edited on the page" || bad "robots.txt"

echo "== ui: $PASS passed, $FAIL failed (log: $LOG)"
[ "${LAB_KEEP:-0}" = 1 ] || { docker rm -f "$CT" >/dev/null 2>&1; docker volume rm "$VOL" >/dev/null 2>&1; }
[ $FAIL -eq 0 ]
