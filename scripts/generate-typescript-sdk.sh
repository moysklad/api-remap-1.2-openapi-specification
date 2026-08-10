#!/bin/sh
# Генерация TypeScript SDK (generator: typescript-fetch) в clients/typescript.
# Версия npm-пакета берётся из semver-версии репозитория спецификации в порядке:
#   SDK_VERSION -> ближайший git-тег -> version из package.json (его же ставит version:auto).
# Работает в Docker (working_dir=/workspace) и локально (корень репо определяется по пути скрипта).
set -e
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
cd "$ROOT_DIR"

# При запуске в Docker репо смонтирован с хоста — Git считает владельца «ненадёжным»
if [ -d ".git" ] && git rev-parse --git-dir >/dev/null 2>&1; then
  git config --global --add safe.directory "$ROOT_DIR" 2>/dev/null || true
fi

VERSION="${SDK_VERSION:-}"
VERSION_SOURCE="SDK_VERSION"
if [ -z "$VERSION" ]; then
  VERSION=$(git describe --tags --abbrev=0 2>/dev/null || echo "")
  VERSION_SOURCE="git tag"
fi
if [ -z "$VERSION" ]; then
  VERSION=$(node -p "require('./package.json').version")
  VERSION_SOURCE="package.json"
fi

# Версия попадает в публикуемый package.json, поэтому пропускаем только semver
if ! echo "$VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$'; then
  echo "ERROR: version '$VERSION' (source: $VERSION_SOURCE) is not semver MAJOR.MINOR.PATCH[-prerelease]"
  exit 1
fi

if [ -x node_modules/.bin/openapi-generator-cli ]; then
  GENERATOR=node_modules/.bin/openapi-generator-cli
else
  GENERATOR=openapi-generator-cli
fi

echo "==> TypeScript SDK version: $VERSION (source: $VERSION_SOURCE)"

# Полная перегенерация: файлы прошлых запусков не должны оставаться в выводе
rm -rf clients/typescript

"$GENERATOR" generate \
  -i src/openapi.yaml \
  -g typescript-fetch \
  -o clients/typescript \
  -c typescript-sdk-config.yaml \
  -t customtemplates/typescript \
  --additional-properties=npmVersion="$VERSION"

# Служебные файлы генератора: не нужны в SDK-репозитории и меняются при обновлении генератора
rm -rf clients/typescript/.openapi-generator
rm -f clients/typescript/.openapi-generator-ignore

echo "==> TypeScript SDK generated in clients/typescript"
