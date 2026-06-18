#!/bin/sh
# Smoke тесты: ожидание mock-сервера + тесты для указанного языка
# Использование: ./scripts/local-test-smoke.sh <php|python|java|javascript>
# Mock-сервер (openapi-mock) запускается как sidecar через docker-compose.
set -e
LANG="${1:-php}"
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
cd "$ROOT_DIR"
if [ ! -f "dist/openapi.yaml" ]; then
  echo "ERROR: dist/openapi.yaml not found. Run: make bundle"
  exit 1
fi

run_php() {
  echo "Installing PHP deps (composer)..." >&2
  cd tests/php
  composer install --no-interaction
  echo "Running PHPUnit smoke suite (timeout 120s)..." >&2
  timeout 120 php vendor/bin/phpunit --testsuite smoke || {
    r=$?
    if [ $r -eq 124 ]; then
      echo "ERROR: Smoke tests timed out after 120s" >&2
    fi
    return $r
  }
}

SMOKE_URL="${SMOKE_BASE_URL:-http://mock:8080}"
echo "Waiting for mock server at ${SMOKE_URL}..." >&2
max=90
while [ $max -gt 0 ]; do
  code=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 5 "${SMOKE_URL}/" 2>/dev/null || true)
  if [ -n "$code" ] && [ "$code" != "000" ]; then
    echo "Mock server is ready (HTTP $code)." >&2
    break
  fi
  if [ $max -eq 1 ]; then
    echo "ERROR: Mock server did not become ready in time" >&2
    exit 1
  fi
  sleep 1
  max=$((max - 1))
done

EXIT=0
case "$LANG" in
  php) run_php || EXIT=$? ;;
  python) run_python || EXIT=$? ;;
  java) run_java || EXIT=$? ;;
  javascript) run_javascript || EXIT=$? ;;
  *) echo "Unknown language: $LANG" >&2; EXIT=1 ;;
esac
exit $EXIT
