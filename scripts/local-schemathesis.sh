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
SCHEMATHESIS_VENV_DEFAULT="$ROOT_DIR/.venv-schemathesis"
SCHEMATHESIS_VENV="${SCHEMATHESIS_VENV:-$SCHEMATHESIS_VENV_DEFAULT}"
SCHEMATHESIS_VERSION_PIN="${SCHEMATHESIS_VERSION:-4.17.0}"
if [ -e "$SCHEMATHESIS_VENV" ] && [ ! -w "$SCHEMATHESIS_VENV" ]; then
  SCHEMATHESIS_VENV="/tmp/remap-api-specification-schemathesis-venv-$(id -u)"
  echo "Existing Schemathesis venv is not writable; using $SCHEMATHESIS_VENV"
fi

INSTALLED_SCHEMATHESIS_VERSION=""
if [ -x "$SCHEMATHESIS_VENV/bin/schemathesis" ]; then
  INSTALLED_SCHEMATHESIS_VERSION=$("$SCHEMATHESIS_VENV/bin/schemathesis" --version 2>/dev/null | sed -n 's/^schemathesis, version //p')
fi

if [ ! -d "$SCHEMATHESIS_VENV" ] || [ ! -x "$SCHEMATHESIS_VENV/bin/schemathesis" ] || [ "$INSTALLED_SCHEMATHESIS_VERSION" != "$SCHEMATHESIS_VERSION_PIN" ]; then
  echo "Creating Schemathesis venv at $SCHEMATHESIS_VENV..."
  python3 -m venv --clear "$SCHEMATHESIS_VENV"
  "$SCHEMATHESIS_VENV/bin/pip" install -q "schemathesis==$SCHEMATHESIS_VERSION_PIN"
fi

# Режимы тестирования:
# - examples: позитивные тесты по example из спеки (по умолчанию; fill-missing выключен в Schemathesis)
# - coverage: расширенное покрытие; только на изменённых сущностях через SCHEMATHESIS_INCLUDE_*
# - fuzzing / stateful: вручную при необходимости
#
# Coverage для конкретной сущности:
#   SCHEMATHESIS_PHASES=coverage SCHEMATHESIS_INCLUDE_PATH_REGEX='^/entity/product(/|$)' make schemathesis
#   SCHEMATHESIS_PHASES=examples SCHEMATHESIS_INCLUDE_OPERATION_ID=createProduct make schemathesis
# Отладка воспроизводимости (два прогона подряд):
#   SCHEMATHESIS_REPEAT=2 make schemathesis
SCHEMATHESIS_PHASES="${SCHEMATHESIS_PHASES:-examples}"
SCHEMATHESIS_MODE="${SCHEMATHESIS_MODE:-positive}"
SCHEMATHESIS_MAX_EXAMPLES="${SCHEMATHESIS_MAX_EXAMPLES:-50}"
SCHEMATHESIS_WORKERS="${SCHEMATHESIS_WORKERS:-auto}"
SCHEMATHESIS_REQUEST_TIMEOUT="${SCHEMATHESIS_REQUEST_TIMEOUT:-30}"
SCHEMATHESIS_NO_SHRINK="${SCHEMATHESIS_NO_SHRINK:-1}"
SCHEMATHESIS_REPEAT="${SCHEMATHESIS_REPEAT:-1}"
SCHEMATHESIS_SEED="${SCHEMATHESIS_SEED:-1}"
SCHEMATHESIS_INCLUDE_PATH_REGEX="${SCHEMATHESIS_INCLUDE_PATH_REGEX:-}"
SCHEMATHESIS_INCLUDE_METHOD="${SCHEMATHESIS_INCLUDE_METHOD:-}"
SCHEMATHESIS_INCLUDE_OPERATION_ID="${SCHEMATHESIS_INCLUDE_OPERATION_ID:-}"

AUTH_HEADER=$(echo -n "${SCHEMATHESIS_LOGIN}:${SCHEMATHESIS_PASSWORD}" | base64)

# Исключаемые проверки:
# @see https://schemathesis.readthedocs.io/en/stable/reference/checks/
# - unsupported_method: nginx возвращает 405 без заголовка Allow (требуется по RFC 9110)
# - negative_data_rejection: невалидные заголовки/параметры — поведение nginx, не дефект спеки
# - ignored_auth: лишние запросы без тела для проверки 401
# positive_data_acceptance включён: валидные example должны давать 2XX
EXCLUDE_CHECKS="unsupported_method,negative_data_rejection,ignored_auth"

echo "==> Running Schemathesis tests"
echo "    Base URL: $SCHEMATHESIS_BASE_URL"
echo "    Phases: $SCHEMATHESIS_PHASES"
echo "    Mode: $SCHEMATHESIS_MODE"
echo "    Max examples: $SCHEMATHESIS_MAX_EXAMPLES"
echo "    Workers: $SCHEMATHESIS_WORKERS"
echo "    Request timeout: $SCHEMATHESIS_REQUEST_TIMEOUT"
echo "    No shrink: $SCHEMATHESIS_NO_SHRINK"
echo "    Repeat: $SCHEMATHESIS_REPEAT"
echo "    Seed: $SCHEMATHESIS_SEED"
echo "    Include path regex: ${SCHEMATHESIS_INCLUDE_PATH_REGEX:-<none>}"
echo "    Include method: ${SCHEMATHESIS_INCLUDE_METHOD:-<none>}"
echo "    Include operationId: ${SCHEMATHESIS_INCLUDE_OPERATION_ID:-<none>}"
echo "    Exclude checks: $EXCLUDE_CHECKS"

set -- "$SCHEMATHESIS_VENV/bin/schemathesis" run dist/openapi.yaml \
  --url "$SCHEMATHESIS_BASE_URL" \
  -H "Authorization: Basic $AUTH_HEADER" \
  -H 'Accept-Encoding: gzip, deflate, br' \
  --max-examples "$SCHEMATHESIS_MAX_EXAMPLES" \
  --phases "$SCHEMATHESIS_PHASES" \
  --mode "$SCHEMATHESIS_MODE" \
  --workers "$SCHEMATHESIS_WORKERS" \
  --exclude-checks "$EXCLUDE_CHECKS" \
  --generation-deterministic \
  --seed "$SCHEMATHESIS_SEED" \
  --request-timeout "$SCHEMATHESIS_REQUEST_TIMEOUT"

if [ "$SCHEMATHESIS_NO_SHRINK" = "1" ]; then
  set -- "$@" --no-shrink
fi

if [ -n "$SCHEMATHESIS_INCLUDE_PATH_REGEX" ]; then
  set -- "$@" --include-path-regex "$SCHEMATHESIS_INCLUDE_PATH_REGEX"
fi

if [ -n "$SCHEMATHESIS_INCLUDE_METHOD" ]; then
  set -- "$@" --include-method "$SCHEMATHESIS_INCLUDE_METHOD"
fi

if [ -n "$SCHEMATHESIS_INCLUDE_OPERATION_ID" ]; then
  set -- "$@" --include-operation-id "$SCHEMATHESIS_INCLUDE_OPERATION_ID"
fi

run_index=1
while [ "$run_index" -le "$SCHEMATHESIS_REPEAT" ]; do
  echo "==> Schemathesis run $run_index/$SCHEMATHESIS_REPEAT"
  "$@"
  run_index=$((run_index + 1))
done
