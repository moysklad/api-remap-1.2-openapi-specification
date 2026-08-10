#!/bin/sh
# Сборка сгенерированного TypeScript SDK: dist/ (CommonJS) и dist/esm/ (ESM) с declarations.
# Требует готовый вывод генерации: сначала make generate-typescript.
# Работает в Docker (working_dir=/workspace) и локально (корень репо определяется по пути скрипта).
set -e
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
cd "$ROOT_DIR"

SDK_DIR=clients/typescript

if [ ! -f "$SDK_DIR/package.json" ]; then
  echo "ERROR: $SDK_DIR/package.json не найден. Запустите: make generate-typescript" >&2
  exit 1
fi

# --ignore-scripts: prepare пакета сам вызывает build, второй прогон tsc не нужен
echo "==> установка зависимостей ($SDK_DIR)..."
sh scripts/npm-install-deps.sh "$SDK_DIR" install --ignore-scripts --no-audit --no-fund

echo "==> npm run build ($SDK_DIR)..."
(cd "$SDK_DIR" && npm run build)

echo "==> TypeScript SDK собран в $SDK_DIR/dist"
