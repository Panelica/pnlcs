#!/bin/sh
# Free, local-only complexity gate. Installs nothing and makes no network calls.
set -eu
HERE=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PHP=${PHP_BINARY:-php}
PHPCS=${PHPCS_BIN:-"$HERE/vendor/bin/phpcs"}
if [ ! -f "$PHPCS" ]; then
    echo 'Missing pinned tooling. Run: composer install --working-dir=tools/aapanel-quality --no-plugins --no-scripts' >&2
    exit 1
fi
case "${1:-lint}" in
    lint)
        "$PHP" "$PHPCS" --standard="$HERE/phpcs.xml" -s
        "$PHP" "$PHPCS" --standard="$HERE/phpcs-closures.xml" -s
        echo 'Stock PHPCS and supplemental closure/arrow gates passed (maximum 10).'
        ;;
    metrics)
        OUT=${2:-"$HERE/reports/complexity.json"}
        mkdir -p "$(dirname -- "$OUT")"
        status=0
        "$PHP" "$PHPCS" --standard="$HERE/phpcs-metrics.xml" --report=json > "$OUT" || status=$?
        # PHPCS status 2 is expected: reporting floor zero emits all measurements.
        if [ "$status" -ne 0 ] && [ "$status" -ne 2 ]; then
            echo "Measurement run failed (exit $status)." >&2
            exit "$status"
        fi
        echo "Measurement-only report written to $OUT. Run lint separately."
        ;;
    *)
        echo 'Usage: ./tools/aapanel-quality/run.sh [lint | metrics [output.json]]' >&2
        exit 1
        ;;
esac
