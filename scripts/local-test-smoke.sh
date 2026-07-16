#!/bin/sh
# Smoke тесты: ожидание mock-сервера + тесты для указанного языка
# Использование: ./scripts/local-test-smoke.sh <java|python|javascript>
# Mock-сервер (openapi-mock) запускается как sidecar через docker-compose.
set -e
LANG="${1:-java}"
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
SMOKE_TEST_TIMEOUT="${SMOKE_TEST_TIMEOUT:-120}"
cd "$ROOT_DIR"
if [ ! -f "dist/openapi.yaml" ]; then
  echo "ERROR: dist/openapi.yaml not found. Run: make bundle"
  exit 1
fi

run_java() {
  echo "Running Java smoke suite (Maven)..." >&2
  timeout "$SMOKE_TEST_TIMEOUT" mvn -pl tests/java/assertions -am test "-Dtest=**/smoke/ApiEndpointsTest" -Dsurefire.failIfNoSpecifiedTests=false || {
    r=$?
    if [ $r -eq 124 ]; then
      echo "ERROR: Java smoke tests timed out after ${SMOKE_TEST_TIMEOUT}s" >&2
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
  python) run_python || EXIT=$? ;;
  java) run_java || EXIT=$? ;;
  javascript) run_javascript || EXIT=$? ;;
  *) echo "Unknown language: $LANG" >&2; EXIT=1 ;;
esac
exit $EXIT
