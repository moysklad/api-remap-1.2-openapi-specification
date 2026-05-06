#!/bin/sh
# Запускает npm ci, подменяя в package-lock.json URL Nexus на registry.npmjs.org.
# Используется в Docker при USE_PUBLIC_NPM_REGISTRY=true, чтобы избежать
# UNABLE_TO_VERIFY_LEAF_SIGNATURE при обращении к nexus.infra.lognex.
# Всегда выполняется из корня репозитория (по пути скрипта).
# Локально: пропускает npm ci, если node_modules уже есть и package-lock.json не менялся
# (чтобы не качать зависимости при каждом make lint/bundle). Принудительно: NPM_CI_FORCE=1
set -e
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
cd "$ROOT_DIR"
if [ -z "$USE_PUBLIC_NPM_REGISTRY" ]; then
  exec npm ci
fi
if [ ! -f package-lock.json ]; then
  exec npm ci
fi
# Пропуск полной установки, если зависимости уже стоят и lock не менялся
if [ -z "$NPM_CI_FORCE" ] && [ -d node_modules ] && [ -f node_modules/.package-lock.json ]; then
  if [ ! "package-lock.json" -nt "node_modules/.package-lock.json" ]; then
    echo "==> node_modules up to date, skipping npm ci (set NPM_CI_FORCE=1 to force)"
    exit 0
  fi
fi
cp package-lock.json package-lock.json.bak
trap 'mv package-lock.json.bak package-lock.json' EXIT
sed 's|https://nexus.infra.lognex/repository/npm|https://registry.npmjs.org|g' package-lock.json > package-lock.json.tmp
mv package-lock.json.tmp package-lock.json
npm ci
trap - EXIT
mv package-lock.json.bak package-lock.json
