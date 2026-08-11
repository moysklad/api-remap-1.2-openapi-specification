#!/bin/sh
# Golden тесты TypeScript SDK: roundtrip сериализации/десериализации на общих fixtures
# из tests/fixtures (те же, что у PHP и Java golden-тестов).
#
# В отличие от scripts/local-test-golden.sh здесь нет пропусков: отсутствие SDK, тестов,
# fixtures или итогов прогона — ошибка. Тесты обязательны и локально, и в CI.
#
# Использование: ./scripts/test-golden-typescript.sh
# (make test-golden-typescript / CI job sdk-golden-typescript)
# Работает в Docker, локально и в GitLab CI (корень репо определяется по пути скрипта).
set -e
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
cd "$ROOT_DIR"

SDK_DIR=clients/typescript
SDK_BUILD_ENTRY="$SDK_DIR/dist/esm/index.js"
TESTS_DIR=tests/typescript
FIXTURES_DIR=tests/fixtures
TEST_LOG="$TESTS_DIR/build/golden-tests.log"

fail() {
  echo "ERROR: $1" >&2
  exit 1
}

count_files() {
  find "$1" -maxdepth 1 -name "$2" -type f 2>/dev/null | wc -l | tr -d ' '
}

[ -f "$SDK_DIR/package.json" ] || fail "$SDK_DIR не найден. Запустите: make generate-typescript"
[ -f "$TESTS_DIR/package.json" ] || fail "$TESTS_DIR не найден: golden-тесты TypeScript обязательны"

TEST_FILES=$(count_files "$TESTS_DIR/golden" '*.test.ts')
[ "$TEST_FILES" -gt 0 ] || fail "в $TESTS_DIR/golden нет файлов *.test.ts"

FIXTURES=$(count_files "$FIXTURES_DIR" '*.json')
[ "$FIXTURES" -gt 0 ] || fail "в $FIXTURES_DIR нет fixtures"

# Тесты работают с собранным пакетом, как его потребитель, поэтому нужен dist/
if [ ! -f "$SDK_BUILD_ENTRY" ]; then
  echo "==> сборка SDK не найдена ($SDK_BUILD_ENTRY), собираем..."
  sh scripts/build-typescript-sdk.sh
fi
[ -f "$SDK_BUILD_ENTRY" ] || fail "сборка SDK не создана: $SDK_BUILD_ENTRY"

echo "==> установка зависимостей тестов ($TESTS_DIR)..."
sh scripts/npm-install-deps.sh "$TESTS_DIR" ci --no-audit --no-fund

echo "==> golden тесты ($FIXTURES fixtures, $TEST_FILES файл(ов) тестов)..."
mkdir -p "$TESTS_DIR/build"
STATUS=0
(cd "$TESTS_DIR" && npm run --silent test:golden) > "$TEST_LOG" 2>&1 || STATUS=$?
cat "$TEST_LOG"

# Итоги прогона обязательны: без них нельзя отличить успешный прогон от того, что тесты не запускались
[ -s "$TEST_LOG" ] || fail "прогон тестов не дал вывода (см. $TEST_LOG)"
PASSED=$(sed -n 's/^# pass \([0-9][0-9]*\)$/\1/p' "$TEST_LOG" | tail -1)
FAILED=$(sed -n 's/^# fail \([0-9][0-9]*\)$/\1/p' "$TEST_LOG" | tail -1)
SKIPPED=$(sed -n 's/^# skipped \([0-9][0-9]*\)$/\1/p' "$TEST_LOG" | tail -1)
[ -n "$PASSED" ] && [ -n "$FAILED" ] || fail "в выводе нет итогов прогона (# pass / # fail): результаты тестирования отсутствуют"

[ "$FAILED" -eq 0 ] || fail "golden тесты упали: $FAILED из $((PASSED + FAILED))"
[ "${SKIPPED:-0}" -eq 0 ] || fail "пропущено тестов: $SKIPPED (golden-тесты не должны пропускаться)"
[ "$STATUS" -eq 0 ] || fail "прогон тестов завершился с кодом $STATUS"

# Каждая fixture даёт как минимум один тест: меньший счётчик означает, что часть fixtures не проверена
[ "$PASSED" -ge "$FIXTURES" ] || fail "пройдено тестов ($PASSED) меньше, чем fixtures ($FIXTURES)"

echo "==> golden тесты TypeScript пройдены: $PASSED тест(ов), $FIXTURES fixtures"
