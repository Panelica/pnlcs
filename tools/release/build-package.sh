#!/usr/bin/env bash
# Builds and signs a PNLCS release package (RELEASING.md, "Cutting a release").
#
#   tools/release/build-package.sh <version> [--ref <git-ref>] [--out <dir>] [--key <private-key.pem>]
#
# - The code is `git archive` of the ref (default: the tag v<version>), so only
#   what is committed ships, minus the export-ignore paths in .gitattributes.
# - vendor/ (composer --no-dev) and public/build/ (npm run build) are built
#   here, so installations never run composer or npm to update.
# - .pnlcs-release.json lists every file with its sha256: the updater compares
#   installations against it.
# - The release statement (version, package sha256 and size, requirements) is
#   signed with the release key; the updater refuses anything else.
#
# Writes to <out> (default: dist/): pnlcs-<version>.tar.gz,
# pnlcs-<version>.release.json and pnlcs-<version>.release.json.sig.
# It publishes nothing: uploading a release is a separate, explicit step.
set -euo pipefail

VERSION="${1:-}"; shift || true
REF=""; OUT="dist"; KEY="${PNLCS_RELEASE_KEY:-}"; RUNTIME_IMAGE="${PNLCS_RUNTIME_IMAGE:-1.5}"
while [ $# -gt 0 ]; do
    case "$1" in
        --ref) REF="$2"; shift 2 ;;
        --out) OUT="$2"; shift 2 ;;
        --key) KEY="$2"; shift 2 ;;
        --runtime-image) RUNTIME_IMAGE="$2"; shift 2 ;;
        *) echo "unknown option $1" >&2; exit 64 ;;
    esac
done

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]]; then
    echo "usage: $0 <version> [--ref <git-ref>] [--out <dir>] [--key <private-key.pem>]" >&2; exit 64
fi
REF="${REF:-v$VERSION}"
[ -n "$KEY" ] && [ -r "$KEY" ] || { echo "the release key is needed: --key <file> or PNLCS_RELEASE_KEY" >&2; exit 64; }

REPO="$(git rev-parse --show-toplevel)"
COMMIT="$(git -C "$REPO" rev-parse "$REF^{commit}")"
mkdir -p "$OUT"; OUT="$(cd "$OUT" && pwd)"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
APP="$WORK/pnlcs"; mkdir -p "$APP"

echo "== code: $REF ($COMMIT)"
git -C "$REPO" archive --format=tar "$COMMIT" | tar -x -C "$APP"
printf '%s\n' "$VERSION" > "$APP/VERSION"

echo "== vendor (composer --no-dev)"
(cd "$APP" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts >/dev/null)
# The scripts composer would run need the application; package discovery is
# what Laravel needs from them.
(cd "$APP" && php artisan package:discover --ansi >/dev/null 2>&1 || true)
rm -f "$APP"/bootstrap/cache/*.php

echo "== assets (npm ci + build)"
(cd "$APP" && npm ci --no-audit --no-fund --silent && npm run build --silent >/dev/null && rm -rf node_modules)

echo "== manifest"
php "$REPO/tools/release/manifest.php" "$APP" "$VERSION" "$COMMIT" "$RUNTIME_IMAGE"

echo "== package"
PKG="pnlcs-$VERSION.tar.gz"
tar --sort=name --owner=0 --group=0 --numeric-owner -czf "$OUT/$PKG" -C "$WORK" pnlcs
SHA="$(sha256sum "$OUT/$PKG" | cut -d' ' -f1)"
SIZE="$(stat -c %s "$OUT/$PKG")"

STATEMENT="$OUT/pnlcs-$VERSION.release.json"
php -r '
$m = json_decode(file_get_contents($argv[1]), true);
echo json_encode(["version" => $argv[2], "package" => $argv[3], "sha256" => $argv[4], "size" => (int) $argv[5],
    "commit" => $m["commit"], "built_at" => $m["built_at"], "requires" => $m["requires"]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
' "$APP/.pnlcs-release.json" "$VERSION" "$PKG" "$SHA" "$SIZE" > "$STATEMENT"

openssl dgst -sha256 -sign "$KEY" "$STATEMENT" | base64 -w0 > "$STATEMENT.sig"
openssl dgst -sha256 -verify <(openssl ec -in "$KEY" -pubout 2>/dev/null) -signature <(base64 -d "$STATEMENT.sig") "$STATEMENT" >/dev/null

echo "== done"
ls -la "$OUT/$PKG" "$STATEMENT" "$STATEMENT.sig"
