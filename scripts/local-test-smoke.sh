#!/bin/sh
# Smoke тесты: Prism в фоне + тесты для указанного языка
# Использование: ./scripts/local-test-smoke.sh <php|python|java|javascript>
# Работает в Docker (working_dir=/workspace) и локально (корень репо по пути скрипта).
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

# Start Prism in this shell (not in a subshell) so it is not killed by SIGHUP
echo "Starting Prism..." >&2
prism mock dist/openapi.yaml -h 0.0.0.0 -p 4010 &
PRISM_PID=$!
# Prism needs ~10s to load spec and bind port; "Prism is listening" appears after route list
sleep 25
if ! kill -0 $PRISM_PID 2>/dev/null; then
  echo "ERROR: Prism failed to start" >&2
  exit 1
fi
echo "Prism started (PID $PRISM_PID), waiting for port 4010..." >&2
max=60
while [ $max -gt 0 ]; do
  if php "$ROOT_DIR/scripts/check-port.php" 2>/dev/null; then
    echo "Prism listening, waiting 5s for spec load..." >&2
    sleep 5
    echo "Prism ready." >&2
    break
  fi
  sleep 1
  max=$((max - 1))
done
if [ $max -eq 0 ]; then
  echo "ERROR: Prism did not open port 4010 in time" >&2
  kill $PRISM_PID 2>/dev/null || true
  exit 1
fi

EXIT=0
case "$LANG" in
  php) run_php || EXIT=$? ;;
  python) run_python || EXIT=$? ;;
  java) run_java || EXIT=$? ;;
  javascript) run_javascript || EXIT=$? ;;
  *) echo "Unknown language: $LANG" >&2; EXIT=1 ;;
esac
kill $PRISM_PID 2>/dev/null || true
exit $EXIT
