#!/bin/sh
# Генерация TypeScript SDK (generator: typescript-fetch) в clients/typescript.
# Версия npm-пакета: SDK_VERSION -> version из package.json (его же ставит version:auto).
set -e

VERSION="${SDK_VERSION:-}"
VERSION_SOURCE="SDK_VERSION"
if [ -z "$VERSION" ]; then
  VERSION=$(node -p "require('./package.json').version")
  VERSION_SOURCE="package.json"
fi

if ! echo "$VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$'; then
  echo "ERROR: version '$VERSION' (source: $VERSION_SOURCE) is not semver MAJOR.MINOR.PATCH[-prerelease]"
  exit 1
fi

echo "==> TypeScript SDK version: $VERSION (source: $VERSION_SOURCE)"

rm -rf clients/typescript

openapi-generator-cli generate \
  -i src/openapi.yaml \
  -g typescript-fetch \
  -o clients/typescript \
  -c typescript-sdk-config.yaml \
  -t customtemplates/typescript \
  --additional-properties=npmVersion="$VERSION"

rm -rf clients/typescript/.openapi-generator
rm -f clients/typescript/.openapi-generator-ignore

echo "==> TypeScript SDK generated in clients/typescript"
