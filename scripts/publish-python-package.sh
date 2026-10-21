#!/bin/sh
set -eu

TARGET="${1:-}"
DIST_DIR="${2:-}"

case "$TARGET" in
  testpypi)
    REPOSITORY_URL="https://test.pypi.org/legacy/"
    TOKEN="${TEST_PYPI_API_TOKEN:-}"
    ;;
  pypi)
    REPOSITORY_URL="https://upload.pypi.org/legacy/"
    TOKEN="${PYPI_API_TOKEN:-}"
    ;;
  *)
    echo "Usage: publish-python-package.sh <testpypi|pypi> <dist-dir>" >&2
    exit 2
    ;;
esac

if [ -z "$DIST_DIR" ] || [ ! -d "$DIST_DIR" ]; then
  echo "ERROR: distribution directory not found: $DIST_DIR" >&2
  exit 2
fi
if [ -z "$TOKEN" ]; then
  echo "ERROR: token for $TARGET is not configured" >&2
  exit 1
fi
if ! ls "$DIST_DIR"/*.whl "$DIST_DIR"/*.tar.gz >/dev/null 2>&1; then
  echo "ERROR: no wheel or sdist found in $DIST_DIR" >&2
  exit 1
fi

python3 -m twine check "$DIST_DIR"/*
TWINE_USERNAME="__token__" \
TWINE_PASSWORD="$TOKEN" \
  python3 -m twine upload --non-interactive \
    --repository-url "$REPOSITORY_URL" \
    "$DIST_DIR"/*
