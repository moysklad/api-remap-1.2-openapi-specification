#!/bin/sh
# Установка npm-зависимостей в подпроекте репозитория (clients/typescript, tests/typescript).
#
# Повторные прогоны: установка пропускается, если node_modules уже соответствует
# package.json и package-lock.json. Принудительно: NPM_CI_FORCE=1
#
# Использование: sh scripts/npm-install-deps.sh <каталог> <ci|install> [доп. флаги npm]
# Работает в Docker (working_dir=/workspace) и локально (корень репо определяется по пути скрипта).
set -e
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)

DIR=$1
NPM_CMD=$2
if [ -z "$DIR" ] || [ -z "$NPM_CMD" ]; then
  echo "usage: $0 <каталог> <ci|install> [доп. флаги npm]" >&2
  exit 2
fi
shift 2

cd "$ROOT_DIR/$DIR"
if [ ! -f package.json ]; then
  echo "ERROR: $DIR/package.json не найден" >&2
  exit 1
fi

INSTALLED_LOCK=node_modules/.package-lock.json
if [ -z "$NPM_CI_FORCE" ] && [ -f "$INSTALLED_LOCK" ] \
  && [ ! package.json -nt "$INSTALLED_LOCK" ] \
  && { [ ! -f package-lock.json ] || [ ! package-lock.json -nt "$INSTALLED_LOCK" ]; }; then
  echo "==> зависимости $DIR актуальны, установка пропущена (принудительно: NPM_CI_FORCE=1)"
  exit 0
fi

# npm ci без lock-файла невозможен: первая установка в сгенерированном SDK создаёт его сама
if [ "$NPM_CMD" = "ci" ] && [ ! -f package-lock.json ]; then
  NPM_CMD=install
fi

npm "$NPM_CMD" "$@"
