#!/bin/sh
set -e
# Schemathesis: контрактные тесты против живого API
# Нужны: SCHEMATHESIS_HOST, SCHEMATHESIS_LOGIN, SCHEMATHESIS_PASSWORD
# Работает в Docker и локально (корень репо по пути скрипта).
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
cd "$ROOT_DIR"
if [ -z "$SCHEMATHESIS_HOST" ] || [ -z "$SCHEMATHESIS_LOGIN" ] || [ -z "$SCHEMATHESIS_PASSWORD" ]; then
  echo "ERROR: Set SCHEMATHESIS_HOST, SCHEMATHESIS_LOGIN, SCHEMATHESIS_PASSWORD"
  echo "Example: docker compose run --rm -e SCHEMATHESIS_HOST=https://api.example.com -e SCHEMATHESIS_LOGIN=user -e SCHEMATHESIS_PASSWORD=pass sdk make schemathesis"
  exit 1
fi
if [ ! -f "dist/openapi.yaml" ]; then
  echo "ERROR: dist/openapi.yaml not found. Run: make bundle"
  exit 1
fi
# Базовый URL должен включать путь /api/remap/1.2
case "$SCHEMATHESIS_HOST" in
  */api/remap/1.2) SCHEMATHESIS_BASE_URL="$SCHEMATHESIS_HOST" ;;
  */api/remap/1.2/) SCHEMATHESIS_BASE_URL="${SCHEMATHESIS_HOST%/}" ;;
  *) SCHEMATHESIS_BASE_URL="${SCHEMATHESIS_HOST%/}/api/remap/1.2" ;;
esac
# Используем venv, чтобы не упираться в externally-managed-environment (PEP 668) в Docker/Alpine
SCHEMATHESIS_VENV="${SCHEMATHESIS_VENV:-$ROOT_DIR/.venv-schemathesis}"
if [ ! -d "$SCHEMATHESIS_VENV" ] || [ ! -x "$SCHEMATHESIS_VENV/bin/schemathesis" ]; then
  echo "Creating Schemathesis venv at $SCHEMATHESIS_VENV..."
  python3 -m venv "$SCHEMATHESIS_VENV"
  "$SCHEMATHESIS_VENV/bin/pip" install -q schemathesis
fi

# Режимы тестирования:
# - examples: базовые тесты с примерами из спецификации
# - coverage: расширенное покрытие всех комбинаций параметров
# - fuzzing: генерация случайных данных для поиска уязвимостей
# - stateful: тесты с изменением состояния
#
# По умолчанию: examples,coverage
# С изменением данных: SCHEMATHESIS_PHASES=examples,coverage,fuzzing,stateful
SCHEMATHESIS_PHASES="${SCHEMATHESIS_PHASES:-examples,coverage}"
SCHEMATHESIS_MAX_EXAMPLES="${SCHEMATHESIS_MAX_EXAMPLES:-50}"

AUTH_HEADER=$(echo -n "${SCHEMATHESIS_LOGIN}:${SCHEMATHESIS_PASSWORD}" | base64)

# Исключаемые проверки:
# @see https://schemathesis.readthedocs.io/en/stable/reference/checks/
# - unsupported_method: nginx возвращает 405 без заголовка Allow (требуется по RFC 9110)
# - positive_data_acceptance: API может вернуть 400/412 при валидной по схеме дате (бизнес-правила)
# Коды 400/412 документированы в спецификации
EXCLUDE_CHECKS="positive_data_acceptance,unsupported_method"

echo "==> Running Schemathesis tests"
echo "    Base URL: $SCHEMATHESIS_BASE_URL"
echo "    Phases: $SCHEMATHESIS_PHASES"
echo "    Max examples: $SCHEMATHESIS_MAX_EXAMPLES"
echo "    Exclude checks: $EXCLUDE_CHECKS"

exec "$SCHEMATHESIS_VENV/bin/schemathesis" run dist/openapi.yaml \
  --url "$SCHEMATHESIS_BASE_URL" \
  -H "Authorization: Basic $AUTH_HEADER" \
  -H 'Accept-Encoding: gzip, deflate, br' \
  --max-examples "$SCHEMATHESIS_MAX_EXAMPLES" \
  --phases "$SCHEMATHESIS_PHASES" \
  --exclude-checks "$EXCLUDE_CHECKS"
