#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
ROOT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
SDK_DIR="${1:-$ROOT_DIR/clients/python}"
OUTPUT_DIR="$SDK_DIR/dist"

if [ ! -f "$SDK_DIR/pyproject.toml" ]; then
  echo "ERROR: generated Python SDK not found at $SDK_DIR" >&2
  exit 1
fi

mkdir -p "$OUTPUT_DIR"
rm -f "$OUTPUT_DIR"/*.whl "$OUTPUT_DIR"/*.tar.gz
if [ -z "${SOURCE_DATE_EPOCH:-}" ]; then
  SOURCE_DATE_EPOCH=$(git -C "$SDK_DIR" log -1 --format=%ct 2>/dev/null || true)
  SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-315532800}"
fi
export SOURCE_DATE_EPOCH
export PYTHONHASHSEED=0
python3 -m build --outdir "$OUTPUT_DIR" "$SDK_DIR"
python3 -m twine check "$OUTPUT_DIR"/*

set -- "$OUTPUT_DIR"/*.whl
if [ "$#" -ne 1 ] || [ ! -f "$1" ]; then
  echo "ERROR: expected exactly one wheel in $OUTPUT_DIR" >&2
  exit 1
fi
python3 "$SCRIPT_DIR/verify-python-wheel.py" "$1"
