#!/usr/bin/env bash
# One update, on a native installation (PHP on the host, no container):
# release A installed from its package, customised the way an operator would,
# then updated to B. Every promise in RELEASING.md is checked with snapshots.
#
#   scenario-native.sh good|bad-migration|bad-view|crash|operator-down [<name>]
#
# operator-down: the operator has put the site in maintenance themselves
# (`artisan down`, no bypass secret); the update runs, checks the new version
# and leaves the site in their maintenance.
#
# Needs: make-releases.sh run first; a MySQL reachable with LAB_DB_* (default:
# the test database container on 127.0.0.1:33061, root/testroot).
set -uo pipefail
VARIANT="${1:?variant}"; NAME="${2:-native-$VARIANT}"
LAB="$(cd "$(dirname "$0")" && pwd)"; WORK="$LAB/.work"
DIR="$WORK/$NAME"; APP="$DIR/pnlcs"; LOG="$DIR/scenario.log"
DB_HOST="${LAB_DB_HOST:-127.0.0.1}"; DB_PORT="${LAB_DB_PORT:-33061}"; DB_USER="${LAB_DB_USER:-root}"; DB_PASS="${LAB_DB_PASS:-testroot}"
DB="pnlcs_lab_$(echo "$NAME" | tr -c 'a-z0-9\n' '_')"
INDEX_VARIANT="$VARIANT"; case "$VARIANT" in crash|operator-down) INDEX_VARIANT=good ;; esac
PASS=0; FAIL=0
ok()   { echo "PASS  $*"; PASS=$((PASS+1)); }
bad()  { echo "FAIL  $*"; FAIL=$((FAIL+1)); }
check() { local name="$1"; shift; if "$@" >>"$LOG" 2>&1; then ok "$name"; else bad "$name"; fi; }
art()  { (cd "$APP" && php artisan "$@"); }

rm -rf "$DIR"; mkdir -p "$DIR"; : > "$LOG"
echo "== $NAME: install 1.3.0 from its package"
tar -xzf "$WORK/releases/A/pnlcs-1.3.0.tar.gz" -C "$DIR"
php -r '$p=new PDO("mysql:host=$argv[1];port=$argv[2]",$argv[3],$argv[4]); $p->exec("DROP DATABASE IF EXISTS `$argv[5]`"); $p->exec("CREATE DATABASE `$argv[5]` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");' "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" "$DB"
cp "$APP/.env.example" "$APP/.env"
python3 - "$APP/.env" <<PY
import re, sys, base64
p = sys.argv[1]; s = open(p).read()
vals = {'APP_ENV': 'production', 'APP_DEBUG': 'false', 'APP_URL': 'http://lab.example.test',
        'DB_CONNECTION': 'mysql', 'DB_HOST': '$DB_HOST', 'DB_PORT': '$DB_PORT', 'DB_DATABASE': '$DB', 'DB_USERNAME': '$DB_USER', 'DB_PASSWORD': '$DB_PASS',
        'SESSION_DRIVER': 'file', 'CACHE_STORE': 'file', 'QUEUE_CONNECTION': 'sync', 'MAIL_MAILER': 'log',
        'PNLCS_UPDATE_INDEX_URL': 'file://$WORK/index-$INDEX_VARIANT.json',
        'PNLCS_UPDATE_PUBLIC_KEY': base64.b64encode(open('$WORK/lab-key.pub', 'rb').read()).decode()}
for k, v in vals.items():
    if re.search(rf'^{k}=', s, re.M): s = re.sub(rf'^{k}=.*$', f'{k}={v}', s, flags=re.M)
    else: s += f'\n{k}={v}'
open(p, 'w').write(s + '\n')
PY
art key:generate --force >>"$LOG" 2>&1
art migrate --force >>"$LOG" 2>&1 || { echo "FAIL  install migrate"; exit 1; }
art db:seed --force >>"$LOG" 2>&1
date -Iseconds > "$APP/storage/installed.lock"
art config:cache >>"$LOG" 2>&1; art route:cache >>"$LOG" 2>&1
check "installed 1.3.0 is healthy" art pnlcs:update-health

echo "== customise"
php "$LAB/customise.php" "$APP" >>"$LOG" 2>&1 || { echo "FAIL  customise"; exit 1; }
art config:cache >>"$LOG" 2>&1
php "$LAB/snapshot.php" "$APP" > "$DIR/before.json"

echo "== check: must find the robots.txt conflict and change nothing"
art pnlcs:update --check >"$DIR/check.out" 2>&1; CODE=$?
[ $CODE -eq 2 ] && ok "check refuses on a conflict (exit 2)" || bad "check exit $CODE"
grep -q "conflict  public/robots.txt" "$DIR/check.out" && ok "the conflict is public/robots.txt" || bad "robots.txt conflict not reported"
grep -q "merged    resources/views/admin/layouts/app.blade.php" "$DIR/check.out" && ok "the admin layout edit is reported as merged" || bad "admin layout merge not reported"
grep -q "acme replaces views" "$DIR/check.out" && ok "the theme override warning is shown" || bad "no theme override warning"
php "$LAB/snapshot.php" "$APP" > "$DIR/after-check.json"
check "a check changes nothing" php "$LAB/compare.php" "$DIR/before.json" "$DIR/after-check.json"

echo "== update without a decision: refused, nothing changed"
art pnlcs:update --yes >"$DIR/refused.out" 2>&1; CODE=$?
[ $CODE -eq 2 ] && ok "update refuses while a conflict is undecided" || bad "undecided update exit $CODE"
php "$LAB/snapshot.php" "$APP" > "$DIR/after-refused.json"
check "a refused update changes nothing" php "$LAB/compare.php" "$DIR/before.json" "$DIR/after-refused.json"

echo "== update with the decision: keep mine"
if [ "$VARIANT" = operator-down ]; then
    art down --retry=30 >>"$LOG" 2>&1
    DOWN_BEFORE="$(cat "$APP/storage/framework/down")"
fi
EXTRA_ENV=()
[ "$VARIANT" = crash ] && EXTRA_ENV=(env PNLCS_UPDATE_LAB_KILL_AT=migrating)
(cd "$APP" && "${EXTRA_ENV[@]}" php artisan pnlcs:update --yes --resolve public/robots.txt=mine -v) >"$DIR/update.out" 2>&1; CODE=$?
php "$LAB/snapshot.php" "$APP" > "$DIR/after.json"

if [ "$VARIANT" = good ] || [ "$VARIANT" = operator-down ]; then
    [ $CODE -eq 0 ] && ok "update succeeds" || bad "update exit $CODE"
    [ "$(cat "$APP/VERSION")" = "1.3.1" ] && ok "VERSION is 1.3.1" || bad "VERSION is $(cat "$APP/VERSION")"
    for f in .env themes/acme/theme.json themes/acme/views/sections/footer.blade.php themes/acme/assets/site.css modules/Servers/LabMine/pnlcs.json modules/Servers/LabMine/README.md app/Hooks/lab-operator.php storage/app/public/logo.png public/robots.txt; do
        php -r '$a=json_decode(file_get_contents($argv[1]),true)["files"];$b=json_decode(file_get_contents($argv[2]),true)["files"]; exit(($a[$argv[3]]??"x")===($b[$argv[3]]??"y")?0:1);' "$DIR/before.json" "$DIR/after.json" "$f" \
            && ok "kept byte for byte: $f" || bad "changed: $f"
    done
    grep -q "operator: our own note at the top" "$APP/resources/views/admin/layouts/app.blade.php" && grep -q "lab release: added near the end" "$APP/resources/views/admin/layouts/app.blade.php" \
        && ok "admin layout: operator's edit and the release's both present" || bad "admin layout merge lost a side"
    [ -f "$APP/app/Support/LabProbe.php" ] && ok "new file installed" || bad "new file missing"
    [ ! -f "$APP/app/Hooks/example.php.disabled" ] && ok "file the release dropped is removed" || bad "dropped file still there"
    check "lab_probe migration ran" php -r '$p=new PDO("mysql:host=$argv[1];port=$argv[2];dbname=$argv[5]",$argv[3],$argv[4]); exit($p->query("SHOW TABLES LIKE \"lab_probe\"")->rowCount()===1?0:1);' "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" "$DB"
    php -r '$p=new PDO("mysql:host=$argv[1];port=$argv[2];dbname=$argv[5]",$argv[3],$argv[4]); $t=$p->query("SELECT message FROM email_templates WHERE custom=1")->fetchColumn(); $d=$p->query("SELECT value FROM dynamic_translations WHERE `key`=\"lab_operator\"")->fetchColumn(); exit($t==="Operator wording" && $d==="Operator translation"?0:1);' "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" "$DB" \
        && ok "operator's email template and translation kept" || bad "operator's database rows changed"
    if [ "$VARIANT" = operator-down ]; then
        [ "$(cat "$APP/storage/framework/down" 2>/dev/null)" = "$DOWN_BEFORE" ] && ok "the operator's maintenance is left exactly as it was" || bad "the operator's maintenance was changed"
    else
        check "site is up (no maintenance file)" test ! -f "$APP/storage/framework/down"
    fi
    check "health after update" art pnlcs:update-health
    grep -q '"result": "updated"' "$APP/storage/app/pnlcs-update/history.json" && ok "history records the update" || bad "history"
else
    if [ "$VARIANT" = crash ]; then
        [ $CODE -ne 0 ] && ok "the update process died part way (simulated)" || bad "crash simulation did not stop the update"
        art pnlcs:update --yes --resolve public/robots.txt=mine >"$DIR/blocked.out" 2>&1 && bad "a new update started over an unfinished one" || ok "a new update refuses while a run is unfinished"
        grep -q "did not finish" "$DIR/blocked.out" && ok "it says the last update did not finish" || bad "no unfinished-run message"
        art pnlcs:update-rollback >"$DIR/rollback.out" 2>&1 && ok "pnlcs:update-rollback finishes the job" || bad "rollback command failed"
        php "$LAB/snapshot.php" "$APP" > "$DIR/after.json"
    else
        [ $CODE -eq 1 ] && ok "update fails and says so" || bad "update exit $CODE"
        grep -q "rolled back" "$DIR/update.out" && ok "output says it was rolled back" || bad "no rollback message"
    fi
    check "files and database are exactly as before" php "$LAB/compare.php" "$DIR/before.json" "$DIR/after.json"
    [ "$(cat "$APP/VERSION")" = "1.3.0" ] && ok "VERSION is still 1.3.0" || bad "VERSION is $(cat "$APP/VERSION")"
    check "site is up (no maintenance file)" test ! -f "$APP/storage/framework/down"
    check "health after rollback" art pnlcs:update-health
fi

echo "== $NAME: $PASS passed, $FAIL failed (log: $LOG)"
[ $FAIL -eq 0 ]
