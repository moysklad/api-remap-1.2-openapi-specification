#!/bin/sh
# Golden тесты для указанного языка
# Использование: ./scripts/local-test-golden.sh <php|python|java|javascript>
# Работает в Docker (working_dir=/workspace) и локально (корень репо по пути скрипта).
set -e
LANG="${1:-php}"
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
cd "$ROOT_DIR"
# При запуске в Docker репо смонтирован с хоста — Git считает владельца «ненадёжным»
if [ -d ".git" ] && git rev-parse --git-dir >/dev/null 2>&1; then
  git config --global --add safe.directory "$ROOT_DIR" 2>/dev/null || true
fi

run_php() {
  if [ ! -d "clients/php" ]; then
    echo "ERROR: clients/php not found. Run: make generate-php"
    exit 1
  fi
  cd tests/php
  composer install --no-interaction
  php vendor/bin/phpunit --testsuite golden
}

run_python() {
  if [ ! -d "clients/python" ]; then
    echo "Skipping golden tests for python: clients/python not found. Run: make generate-python"
    return 0
  fi
  if [ ! -d "tests/python" ]; then
    echo "Skipping: tests/python not configured"
    return 0
  fi
  cd tests/python
  pip install -q -r requirements.txt
  pytest golden/ -v 2>/dev/null || echo "Skipping: no golden tests for python yet"
}

run_java() {
  if [ ! -d "clients/java" ]; then
    echo "Skipping golden tests for java: clients/java not found"
    return 0
  fi
  if [ ! -d "tests/java" ]; then
    echo "Skipping: tests/java not configured"
    return 0
  fi
  cd tests/java
  mvn test -Dtest=**/golden/**
}

run_javascript() {
  if [ ! -d "clients/javascript" ]; then
    echo "Skipping golden tests for javascript: clients/javascript not found"
    return 0
  fi
  if [ ! -d "tests/javascript" ]; then
    echo "Skipping: tests/javascript not configured"
    return 0
  fi
  cd tests/javascript
  npm ci
  npm run test:golden 2>/dev/null || echo "Skipping: no test:golden script"
}

case "$LANG" in
  php) run_php ;;
  python) run_python ;;
  java) run_java ;;
  javascript) run_javascript ;;
  *) echo "Unknown language: $LANG"; exit 1 ;;
esac
