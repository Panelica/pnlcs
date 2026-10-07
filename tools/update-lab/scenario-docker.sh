#!/usr/bin/env bash
# One update inside the Docker image (panelica/pnlcs-runtime 1.5 candidate):
# the container installs release A on its first start (signed package, not a
# clone), is customised, and is updated to B with /usr/local/bin/update.sh.
#
#   scenario-docker.sh good|bad-migration|bad-view|crash [<image>]
#
# crash: the update process dies during the migrations; the container is
# restarted, and its entrypoint must roll the update back by itself.
set -uo pipefail
VARIANT="${1:?variant}"; IMAGE="${2:-pnlcs-runtime:1.5-candidate}"
LAB="$(cd "$(dirname "$0")" && pwd)"; WORK="$LAB/.work"
NAME="docker-$VARIANT"; DIR="$WORK/$NAME"; LOG="$DIR/scenario.log"; CT="pnlcs-lab-$VARIANT"; VOL="pnlcs_lab_$VARIANT"
DB_PORT="${LAB_DB_PORT:-33061}"; DB_USER="${LAB_DB_USER:-root}"; DB_PASS="${LAB_DB_PASS:-testroot}"
DB="pnlcs_lab_docker_$(echo "$VARIANT" | tr -c 'a-z0-9\n' '_')"
INDEX_VARIANT="$VARIANT"; [ "$VARIANT" = crash ] && INDEX_VARIANT=good
APP=/var/www/pnlcs
PASS=0; FAIL=0
ok()   { echo "PASS  $*"; PASS=$((PASS+1)); }
bad()  { echo "FAIL  $*"; FAIL=$((FAIL+1)); }
in_ct() { docker exec "$CT" "$@"; }
as_www() { docker exec -u www-data -w "$APP" "$CT" "$@"; }
snapshot() { as_www php "$LAB/snapshot.php" "$APP" > "$1"; }
# Not `docker logs | grep -q`: with pipefail, grep leaving early fails the pipe.
SINCE=""
logs_have() { docker logs ${SINCE:+--since "$SINCE"} "$CT" > "$DIR/.logs" 2>&1; grep -q "$1" "$DIR/.logs"; }
wait_ready() {
    for i in $(seq 1 120); do
        logs_have "Handing off to supervisord" && return 0
        docker inspect -f '{{.State.Running}}' "$CT" 2>/dev/null | grep -q true || return 1
        sleep 2
    done
    return 1
}

docker rm -f "$CT" >/dev/null 2>&1; docker volume rm "$VOL" >/dev/null 2>&1
rm -rf "$DIR"; mkdir -p "$DIR"; : > "$LOG"
php -r '$p=new PDO("mysql:host=127.0.0.1;port=$argv[1]",$argv[2],$argv[3]); $p->exec("DROP DATABASE IF EXISTS `$argv[4]`"); $p->exec("CREATE DATABASE `$argv[4]` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");' "$DB_PORT" "$DB_USER" "$DB_PASS" "$DB"
KEY_B64="$(base64 -w0 "$WORK/lab-key.pub")"

echo "== $NAME: first start installs release 1.3.0"
docker run -d --name "$CT" --add-host=host.docker.internal:host-gateway \
    -v "$LAB:$LAB:ro" -v "$VOL:$APP" \
    -e DB_HOST=host.docker.internal -e DB_PORT="$DB_PORT" -e DB_DATABASE="$DB" -e DB_USERNAME="$DB_USER" -e DB_PASSWORD="$DB_PASS" \
    -e APP_URL=http://lab.example.test -e PNLCS_VERSION=1.3.0 \
    -e PNLCS_UPDATE_INDEX_URL="file://$WORK/index-$INDEX_VARIANT.json" -e PNLCS_UPDATE_PUBLIC_KEY="$KEY_B64" \
    "$IMAGE" >>"$LOG" 2>&1
wait_ready && ok "container started" || { bad "container did not start"; docker logs "$CT" >>"$LOG" 2>&1; echo "== $NAME: $PASS passed, $FAIL failed"; exit 1; }
logs_have "installed PNLCS 1.3.0" && ok "first start installed the signed release 1.3.0, no git clone" || bad "first start did not install the release"
[ "$(in_ct cat $APP/VERSION)" = "1.3.0" ] && ok "VERSION is 1.3.0" || bad "VERSION is $(in_ct cat $APP/VERSION)"
as_www php artisan db:seed --force >>"$LOG" 2>&1
in_ct sh -c "date -Iseconds > $APP/storage/installed.lock && chown www-data:www-data $APP/storage/installed.lock"
as_www php artisan pnlcs:update-health >>"$LOG" 2>&1 && ok "1.3.0 is healthy" || bad "1.3.0 health"

echo "== customise"
as_www php "$LAB/customise.php" "$APP" >>"$LOG" 2>&1 || bad "customise"
as_www php artisan config:cache >>"$LOG" 2>&1
snapshot "$DIR/before.json"

echo "== check: the conflict is found, nothing changes"
in_ct /usr/local/bin/update.sh --check >"$DIR/check.out" 2>&1; CODE=$?
[ $CODE -eq 2 ] && ok "update.sh --check refuses on the conflict (exit 2)" || bad "check exit $CODE"
grep -q "conflict  public/robots.txt" "$DIR/check.out" && ok "the conflict is public/robots.txt" || bad "robots conflict not reported"
snapshot "$DIR/after-check.json"
php "$LAB/compare.php" "$DIR/before.json" "$DIR/after-check.json" >>"$LOG" 2>&1 && ok "a check changes nothing" || bad "the check changed something"

echo "== update with the decision: keep mine"
EXTRA=(); [ "$VARIANT" = crash ] && EXTRA=(-e PNLCS_UPDATE_LAB_KILL_AT=migrating)
docker exec "${EXTRA[@]}" "$CT" /usr/local/bin/update.sh --resolve public/robots.txt=mine -v >"$DIR/update.out" 2>&1; CODE=$?

if [ "$VARIANT" = good ]; then
    [ $CODE -eq 0 ] && ok "update succeeds" || bad "update exit $CODE"
    [ "$(in_ct cat $APP/VERSION)" = "1.3.1" ] && ok "VERSION is 1.3.1" || bad "VERSION is $(in_ct cat $APP/VERSION)"
    snapshot "$DIR/after.json"
    for f in .env themes/acme/theme.json themes/acme/views/sections/footer.blade.php modules/Servers/LabMine/pnlcs.json app/Hooks/lab-operator.php storage/app/public/logo.png public/robots.txt; do
        php -r '$a=json_decode(file_get_contents($argv[1]),true)["files"];$b=json_decode(file_get_contents($argv[2]),true)["files"]; exit(($a[$argv[3]]??"x")===($b[$argv[3]]??"y")?0:1);' "$DIR/before.json" "$DIR/after.json" "$f" \
            && ok "kept byte for byte: $f" || bad "changed: $f"
    done
    in_ct grep -q "operator: our own note at the top" $APP/resources/views/admin/layouts/app.blade.php && in_ct grep -q "lab release: added near the end" $APP/resources/views/admin/layouts/app.blade.php \
        && ok "admin layout merged" || bad "admin layout merge"
    in_ct sh -c "[ \$(stat -c %U $APP/app/Support/LabProbe.php) = www-data ]" && ok "new files belong to www-data" || bad "new files are not www-data's"
    [ "$(docker exec "$CT" curl -s -o /dev/null -w '%{http_code}' -H 'Host: lab.example.test' http://127.0.0.1/admin/login)" = 200 ] && ok "nginx + php-fpm serve the admin login (200)" || bad "admin login over HTTP"
    as_www php artisan pnlcs:update-health >>"$LOG" 2>&1 && ok "health after update" || bad "health after update"
else
    if [ "$VARIANT" = crash ]; then
        [ $CODE -ne 0 ] && ok "the update process died part way (simulated)" || bad "the crash simulation did not stop the update"
        SINCE="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
        docker restart "$CT" >>"$LOG" 2>&1
        wait_ready && ok "container restarted" || bad "container did not restart"
        logs_have "rolled back" && ok "the entrypoint rolled the unfinished update back on restart" || bad "no rollback on restart"
    else
        [ $CODE -eq 1 ] && ok "update fails and says so" || bad "update exit $CODE"
    fi
    snapshot "$DIR/after.json"
    php "$LAB/compare.php" "$DIR/before.json" "$DIR/after.json" --content-only=storage/ >>"$LOG" 2>&1 && ok "files and database exactly as before" || { bad "something differs"; php "$LAB/compare.php" "$DIR/before.json" "$DIR/after.json" --content-only=storage/ | head -10; }
    [ "$(in_ct cat $APP/VERSION)" = "1.3.0" ] && ok "VERSION is still 1.3.0" || bad "VERSION is $(in_ct cat $APP/VERSION)"
    in_ct test ! -f $APP/storage/framework/down && ok "site is up" || bad "site is in maintenance"
    [ "$(docker exec "$CT" curl -s -o /dev/null -w '%{http_code}' -H 'Host: lab.example.test' http://127.0.0.1/admin/login)" = 200 ] && ok "admin login answers 200" || bad "admin login over HTTP"
fi

docker logs "$CT" >"$DIR/container.log" 2>&1
echo "== $NAME: $PASS passed, $FAIL failed (log: $LOG)"
[ "${LAB_KEEP:-0}" = 1 ] || { docker rm -f "$CT" >/dev/null 2>&1; docker volume rm "$VOL" >/dev/null 2>&1; }
[ $FAIL -eq 0 ]
