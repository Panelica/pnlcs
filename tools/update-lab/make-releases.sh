#!/usr/bin/env bash
# Builds the update lab's releases from HEAD, signed with a lab key that is
# generated here and never leaves .work/:
#   A = 1.3.0  HEAD as it is
#   B = 1.3.1  HEAD + synthetic-change.sh, in three variants:
#              good, bad-migration (a migration that fails), bad-view (a page that fails)
# and one release index per variant (index-<variant>.json, the GitHub
# Releases shape, with file:// links) for PNLCS_UPDATE_INDEX_URL.
set -euo pipefail
LAB="$(cd "$(dirname "$0")" && pwd)"
REPO="$(git -C "$LAB" rev-parse --show-toplevel)"
WORK="$LAB/.work"
A="${LAB_VERSION_A:-1.3.0}"; B="${LAB_VERSION_B:-1.3.1}"
mkdir -p "$WORK/releases"

KEY="$WORK/lab-key.pem"
[ -f "$KEY" ] || (umask 077; openssl ecparam -name prime256v1 -genkey -noout -out "$KEY")
openssl ec -in "$KEY" -pubout -out "$WORK/lab-key.pub" 2>/dev/null

HEAD="$(git -C "$REPO" rev-parse HEAD)"
build() { (cd "$REPO" && tools/release/build-package.sh "$2" --ref "$1" --out "$3" --key "$KEY"); }

echo "### A = $A from $HEAD"
rm -rf "$WORK/releases/A"; build "$HEAD" "$A" "$WORK/releases/A"

for kind in good bad-migration bad-view; do
    echo "### B ($kind) = $B"
    SRC="$WORK/src-$kind"
    git -C "$REPO" worktree remove --force "$SRC" 2>/dev/null || rm -rf "$SRC"
    git -C "$REPO" worktree add --detach -q "$SRC" "$HEAD"
    "$LAB/synthetic-change.sh" "$SRC" "$kind"
    git -C "$SRC" add -A
    git -C "$SRC" -c user.name=lab -c user.email=lab@example.com commit -q -m "lab release $B ($kind)"
    REF="$(git -C "$SRC" rev-parse HEAD)"
    rm -rf "$WORK/releases/B-$kind"; build "$REF" "$B" "$WORK/releases/B-$kind"
    git -C "$REPO" worktree remove --force "$SRC"

    python3 - "$WORK" "$A" "$B" "$kind" <<'PY'
import json, sys
work, a, b, kind = sys.argv[1:]
def release(version, folder, pre=False):
    base = f"{work}/releases/{folder}"
    names = [f"pnlcs-{version}.tar.gz", f"pnlcs-{version}.release.json", f"pnlcs-{version}.release.json.sig"]
    return {"tag_name": f"v{version}", "prerelease": pre, "draft": False, "body": f"Lab release {version} ({folder}).",
            "html_url": None, "published_at": None,
            "assets": [{"name": n, "browser_download_url": f"file://{base}/{n}"} for n in names]}
json.dump([release(b, f"B-{kind}"), release(a, "A")], open(f"{work}/index-{kind}.json", "w"), indent=2)
PY
done

echo "### done"; ls -la "$WORK"/releases/*/ "$WORK"/index-*.json
