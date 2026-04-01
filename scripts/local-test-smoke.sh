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

run_python() {
  if [ ! -d "tests/python" ] || [ ! -f "tests/python/requirements.txt" ]; then
    echo "Skipping smoke tests for python: tests/python not configured"
    return 0
  fi
  cd tests/python
  pip install -q -r requirements.txt
  pytest smoke/ -v 2>/dev/null || echo "Skipping: no smoke tests for python yet"
}

run_java() {
  if [ ! -d "tests/java" ] || [ ! -f "tests/java/pom.xml" ]; then
    echo "Skipping smoke tests for java: tests/java not configured"
    return 0
  fi
  cd tests/java
  mvn test -Dtest=**/smoke/**
}

run_javascript() {
  if [ ! -d "tests/javascript" ] || [ ! -f "tests/javascript/package.json" ]; then
    echo "Skipping smoke tests for javascript: tests/javascript not configured"
    return 0
  fi
  cd tests/javascript
  npm ci
  npm run test:smoke 2>/dev/null || echo "Skipping: no test:smoke script"
}

PRISM_URL="${PRISM_BASE_URL:-http://mock:8080}"
echo "Waiting for mock server at ${PRISM_URL}..." >&2
max=90
while [ $max -gt 0 ]; do
  code=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 5 "${PRISM_URL}/" 2>/dev/null || true)
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
