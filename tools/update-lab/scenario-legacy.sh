#!/usr/bin/env bash
# The first update of an installation made before releases existed: a git
# clone of main (as docs/install/native.md and Docker image 1.4 set them up),
# customised, then updated to a release by the new updater, its files
# compared against the commit it was cloned at.
#
#   scenario-legacy.sh native     a clone on the host
#   scenario-legacy.sh docker     a clone made by image 1.4's entrypoint, the
#                                 container then recreated on image 1.5 with
#                                 the same volume, and updated with update.sh
set -uo pipefail
MODE="${1:?native|docker}"; NEW_IMAGE="${2:-pnlcs-runtime:1.5-candidate}"; OLD_IMAGE="${LAB_OLD_IMAGE:-panelica/pnlcs-runtime:1.4}"
LAB="$(cd "$(dirname "$0")" && pwd)"; WORK="$LAB/.work"; REPO="$(git -C "$LAB" rev-parse --show-toplevel)"
. "$WORK/versions.env"   # LAB_A, LAB_B (make-releases.sh)
NAME="legacy-$MODE"; DIR="$WORK/$NAME"; LOG="$DIR/scenario.log"; CT="pnlcs-lab-legacy"; VOL="pnlcs_lab_legacy"
DB_PORT="${LAB_DB_PORT:-33061}"; DB_USER="${LAB_DB_USER:-root}"; DB_PASS="${LAB_DB_PASS:-testroot}"; DB="pnlcs_lab_legacy_$MODE"
A_COMMIT="$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1]))["commit"])' "$WORK/releases/A/pnlcs-${LAB_A}.release.json")"
PASS=0; FAIL=0
ok()   { echo "PASS  $*"; PASS=$((PASS+1)); }
bad()  { echo "FAIL  $*"; FAIL=$((FAIL+1)); }
rm -rf "$DIR"; mkdir -p "$DIR"; : > "$LOG"
php -r '$p=new PDO("mysql:host=127.0.0.1;port=$argv[1]",$argv[2],$argv[3]); $p->exec("DROP DATABASE IF EXISTS `$argv[4]`"); $p->exec("CREATE DATABASE `$argv[4]` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");' "$DB_PORT" "$DB_USER" "$DB_PASS" "$DB"
KEY_B64="$(base64 -w0 "$WORK/lab-key.pub")"

# The "GitHub" the old installation was cloned from: main at release A's commit.
git clone -q --bare "$REPO" "$DIR/origin.git"
git -C "$DIR/origin.git" update-ref refs/heads/main "$A_COMMIT"

if [ "$MODE" = native ]; then
    APP="$DIR/pnlcs"
    git clone -q --branch main "$DIR/origin.git" "$APP"
    # composer install + npm run build, as the old guide had the operator do
    # (taken from release A, built from the same lock files, to save minutes).
    tar -xzf "$WORK/releases/A/pnlcs-${LAB_A}.tar.gz" -C "$DIR" pnlcs/vendor pnlcs/public/build 2>/dev/null || { mkdir -p "$DIR/x"; tar -xzf "$WORK/releases/A/pnlcs-${LAB_A}.tar.gz" -C "$DIR/x"; cp -a "$DIR/x/pnlcs/vendor" "$DIR/x/pnlcs/public/build" -t "$APP/" ; }
    [ -d "$APP/vendor" ] || cp -a "$DIR/x/pnlcs/vendor" "$APP/vendor"
    cp "$APP/.env.example" "$APP/.env"
    python3 - "$APP/.env" "$DB" "$DB_PORT" "$DB_USER" "$DB_PASS" "$WORK" "$KEY_B64" <<'PY'
import re, sys
p, db, port, user, pw, work, key = sys.argv[1:]
s = open(p).read()
vals = {'APP_ENV': 'production', 'APP_DEBUG': 'false', 'APP_URL': 'http://lab.example.test', 'DB_HOST': '127.0.0.1', 'DB_PORT': port,
        'DB_DATABASE': db, 'DB_USERNAME': user, 'DB_PASSWORD': pw, 'SESSION_DRIVER': 'file', 'CACHE_STORE': 'file', 'QUEUE_CONNECTION': 'sync',
        'MAIL_MAILER': 'log', 'PNLCS_UPDATE_INDEX_URL': f'file://{work}/index-good.json', 'PNLCS_UPDATE_PUBLIC_KEY': key}
for k, v in vals.items():
    s = re.sub(rf'^{k}=.*$', f'{k}={v}', s, flags=re.M) if re.search(rf'^{k}=', s, re.M) else s + f'\n{k}={v}'
open(p, 'w').write(s + '\n')
PY
    run() { (cd "$APP" && "$@"); }
    run php artisan key:generate --force >>"$LOG" 2>&1
    run php artisan migrate --force >>"$LOG" 2>&1 && run php artisan db:seed --force >>"$LOG" 2>&1
    date -Iseconds > "$APP/storage/installed.lock"
    run php artisan config:cache >>"$LOG" 2>&1
    run php artisan pnlcs:update-health >>"$LOG" 2>&1 && ok "the git-cloned installation works" || bad "git install health"
    php "$LAB/customise.php" "$APP" >>"$LOG" 2>&1; run php artisan config:cache >>"$LOG" 2>&1
    php "$LAB/snapshot.php" "$APP" > "$DIR/before.json"
    # As under a hosting account's cron: the system temporary directory cannot
    # be written (/tmp owned by root there; /proc here, which not even root can).
    run env TMPDIR=/proc php artisan pnlcs:update --check >"$DIR/check.out" 2>&1; CODE=$?
    run env TMPDIR=/proc php artisan pnlcs:update --yes --resolve public/robots.txt=mine -v >"$DIR/update.out" 2>&1; UCODE=$?
    TESTS_KEPT=$([ -f "$APP/tests/Pest.php" ] && echo 1 || echo 0)
    php "$LAB/snapshot.php" "$APP" > "$DIR/after.json"
    VERSION_NOW="$(cat "$APP/VERSION")"; HAS_MANIFEST=$([ -f "$APP/.pnlcs-release.json" ] && echo 1 || echo 0); GIT_KEPT=$([ -d "$APP/.git" ] && echo 1 || echo 0)
    HEALTH() { run php artisan pnlcs:update-health; }
else
    APP=/var/www/pnlcs
    docker rm -f "$CT" >/dev/null 2>&1; docker volume rm "$VOL" >/dev/null 2>&1
    echo "== image 1.4 clones main and builds it (several minutes)"
    docker run -d --name "$CT" --add-host=host.docker.internal:host-gateway -v "$DIR/origin.git:/origin.git:ro" -v "$LAB:$LAB:ro" -v "$VOL:$APP" \
        -e PNLCS_REPO=file:///origin.git -e PNLCS_BRANCH=main \
        -e DB_HOST=host.docker.internal -e DB_PORT="$DB_PORT" -e DB_DATABASE="$DB" -e DB_USERNAME="$DB_USER" -e DB_PASSWORD="$DB_PASS" \
        -e APP_URL=http://lab.example.test "$OLD_IMAGE" >>"$LOG" 2>&1
    for i in $(seq 1 300); do docker logs "$CT" > "$DIR/.logs" 2>&1; grep -q "Handing off" "$DIR/.logs" && break; sleep 3; done
    grep -q "Handing off" "$DIR/.logs" && ok "image 1.4 set the clone up" || { bad "image 1.4 did not start"; tail -30 "$DIR/.logs"; echo "== $NAME: $PASS passed, $FAIL failed"; exit 1; }
    docker exec -u www-data -w $APP "$CT" php artisan db:seed --force >>"$LOG" 2>&1
    docker exec "$CT" sh -c "date -Iseconds > $APP/storage/installed.lock; chown www-data:www-data $APP/storage/installed.lock"
    docker exec -u www-data "$CT" php "$LAB/customise.php" "$APP" >>"$LOG" 2>&1 || bad "customise"
    docker exec -u www-data -w $APP "$CT" php artisan config:cache >>"$LOG" 2>&1
    docker exec -u www-data "$CT" php "$LAB/snapshot.php" "$APP" > "$DIR/before.json"

    echo "== the container is recreated on image 1.5, same volume"
    docker rm -f "$CT" >>"$LOG" 2>&1
    docker run -d --name "$CT" --add-host=host.docker.internal:host-gateway -v "$LAB:$LAB:ro" -v "$VOL:$APP" \
        -e DB_HOST=host.docker.internal -e DB_PORT="$DB_PORT" -e DB_DATABASE="$DB" -e DB_USERNAME="$DB_USER" -e DB_PASSWORD="$DB_PASS" \
        -e APP_URL=http://lab.example.test -e PNLCS_UPDATE_INDEX_URL="file://$WORK/index-good.json" -e PNLCS_UPDATE_PUBLIC_KEY="$KEY_B64" \
        "$NEW_IMAGE" >>"$LOG" 2>&1
    for i in $(seq 1 120); do docker logs "$CT" > "$DIR/.logs" 2>&1; grep -q "Handing off" "$DIR/.logs" && break; sleep 2; done
    grep -q "PNLCS is installed" "$DIR/.logs" && ok "image 1.5 keeps the git-cloned code (no wipe, no re-clone)" || bad "image 1.5 did not recognise the installation"
    # The operator recreated the container with new settings: the cached
    # configuration is rebuilt, as after any change of configuration.
    docker exec -u www-data -w $APP "$CT" php artisan config:cache >>"$LOG" 2>&1
    docker exec -u www-data "$CT" php "$LAB/snapshot.php" "$APP" > "$DIR/after-recreate.json"
    php "$LAB/compare.php" "$DIR/before.json" "$DIR/after-recreate.json" --content-only=storage/ >>"$LOG" 2>&1 && ok "recreating the container changed nothing" || bad "recreating the container changed something"
    docker exec "$CT" /usr/local/bin/update.sh --check >"$DIR/check.out" 2>&1; CODE=$?
    docker exec "$CT" /usr/local/bin/update.sh --resolve public/robots.txt=mine -v >"$DIR/update.out" 2>&1; UCODE=$?
    docker exec -u www-data "$CT" php "$LAB/snapshot.php" "$APP" > "$DIR/after.json"
    VERSION_NOW="$(docker exec "$CT" cat $APP/VERSION)"; HAS_MANIFEST=$(docker exec "$CT" test -f $APP/.pnlcs-release.json && echo 1 || echo 0); GIT_KEPT=$(docker exec "$CT" test -d $APP/.git && echo 1 || echo 0)
    TESTS_KEPT=$(docker exec "$CT" test -f $APP/tests/Pest.php && echo 1 || echo 0)
    HEALTH() { docker exec -u www-data -w $APP "$CT" php artisan pnlcs:update-health; }
fi

[ $CODE -eq 2 ] && ok "check: conflict found (exit 2)" || bad "check exit $CODE"
grep -q "set up with git" "$DIR/check.out" && ok "check explains this is a git installation" || bad "no git-install note"
grep -q "conflict  public/robots.txt" "$DIR/check.out" && ok "robots.txt conflict found against the cloned commit" || bad "robots conflict"
grep -q "merged    resources/views/admin/layouts/app.blade.php" "$DIR/check.out" && ok "admin layout edit merges" || bad "layout merge"
[ $UCODE -eq 0 ] && ok "update succeeds" || { bad "update exit $UCODE"; tail -20 "$DIR/update.out"; }
[ "$VERSION_NOW" = "${LAB_B}" ] && ok "VERSION is ${LAB_B}" || bad "VERSION is $VERSION_NOW"
[ "$HAS_MANIFEST" = 1 ] && ok "now a release installation (manifest at the root)" || bad "no manifest"
[ "$GIT_KEPT" = 1 ] && ok ".git is left where it was" || bad ".git removed"
[ "$TESTS_KEPT" = 1 ] && ok "tests/ is left where it was (a release leaves it out, an update never removes it)" || bad "tests/ removed"
for f in .env themes/acme/theme.json themes/acme/views/sections/footer.blade.php modules/Servers/LabMine/pnlcs.json app/Hooks/lab-operator.php storage/app/public/logo.png public/robots.txt; do
    php -r '$a=json_decode(file_get_contents($argv[1]),true)["files"];$b=json_decode(file_get_contents($argv[2]),true)["files"]; $x=fn($v)=>preg_replace("/^[0-7]+:/","",(string)$v); exit($x($a[$argv[3]]??"x")===$x($b[$argv[3]]??"y")?0:1);' "$DIR/before.json" "$DIR/after.json" "$f" \
        && ok "kept: $f" || bad "changed: $f"
done
HEALTH >>"$LOG" 2>&1 && ok "health after update" || bad "health after update"

echo "== $NAME: $PASS passed, $FAIL failed (log: $LOG)"
[ "$MODE" = docker ] && [ "${LAB_KEEP:-0}" != 1 ] && { docker logs "$CT" > "$DIR/container.log" 2>&1; docker rm -f "$CT" >/dev/null 2>&1; docker volume rm "$VOL" >/dev/null 2>&1; }
[ $FAIL -eq 0 ]
