#!/usr/bin/env bash
# Every scenario of the update lab, one after the other. Exit 0 only when all
# pass. RELEASING.md: a release is not published until this is green.
#
#   tools/update-lab/run-all.sh [--no-build] [--image <docker image>]
set -uo pipefail
LAB="$(cd "$(dirname "$0")" && pwd)"
BUILD=1; IMAGE="pnlcs-runtime:1.5-candidate"
while [ $# -gt 0 ]; do
    case "$1" in --no-build) BUILD=0; shift ;; --image) IMAGE="$2"; shift 2 ;; *) echo "unknown $1" >&2; exit 64 ;; esac
done

[ $BUILD = 1 ] && { "$LAB/make-releases.sh" > "$LAB/.work/make-releases.log" 2>&1 || { echo "building the lab releases failed: $LAB/.work/make-releases.log"; exit 1; }; }

RESULTS=()
run() { local name="$1"; shift; "$@" > "$LAB/.work/$name.out" 2>&1; local code=$?; RESULTS+=("$(printf '%-24s %s' "$name" "$(tail -1 "$LAB/.work/$name.out" | grep -o '[0-9]* passed, [0-9]* failed' || echo 'did not finish')")"); return $code; }

FAILED=0
for v in good bad-migration bad-view crash; do run "native-$v" "$LAB/scenario-native.sh" "$v" || FAILED=1; done
for v in good bad-migration bad-view crash; do run "docker-$v" "$LAB/scenario-docker.sh" "$v" "$IMAGE" || FAILED=1; done
run legacy-native "$LAB/scenario-legacy.sh" native || FAILED=1
run legacy-docker "$LAB/scenario-legacy.sh" docker "$IMAGE" || FAILED=1
run ui "$LAB/scenario-ui.sh" "$IMAGE" || FAILED=1
# A real server (nginx + PHP-FPM + MariaDB + cron), when one is given:
# LAB_VM_IP / LAB_VM_PASS of a fresh Debian or Ubuntu test machine.
if [ -n "${LAB_VM_IP:-}" ]; then run vm "$LAB/scenario-vm.sh" || FAILED=1; else RESULTS+=("vm                       skipped (no LAB_VM_IP)"); fi

printf '%s\n' "${RESULTS[@]}"
[ $FAILED = 0 ] && echo "ALL GREEN" || echo "NOT GREEN: see $LAB/.work/<scenario>.out"
exit $FAILED
