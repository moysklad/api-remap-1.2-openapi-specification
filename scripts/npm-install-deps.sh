#!/bin/sh
# Установка npm-зависимостей в подпроекте репозитория (clients/typescript, tests/typescript).
#
# Решает две задачи:
#  1. USE_PUBLIC_NPM_REGISTRY=true (Docker/CI): в package-lock.json подменяются URL Nexus на
#     registry.npmjs.org, иначе установка падает с UNABLE_TO_VERIFY_LEAF_SIGNATURE. Lock-файл
#     восстанавливается после установки, поэтому рабочая копия не меняется.
#  2. Повторные прогоны: установка пропускается, если node_modules уже соответствует
#     package.json и package-lock.json. Принудительно: NPM_CI_FORCE=1
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

if [ -n "$USE_PUBLIC_NPM_REGISTRY" ] && [ -f package-lock.json ]; then
  cp package-lock.json package-lock.json.bak
  trap 'mv package-lock.json.bak package-lock.json' EXIT INT TERM
  sed 's|https://nexus.infra.lognex/repository/npm|https://registry.npmjs.org|g' \
    package-lock.json > package-lock.json.tmp
  mv package-lock.json.tmp package-lock.json
  npm "$NPM_CMD" "$@"
  trap - EXIT INT TERM
  mv package-lock.json.bak package-lock.json
else
  npm "$NPM_CMD" "$@"
fi
