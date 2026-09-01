#!/usr/bin/env python3
"""Prepare packaging layout for a generated Python SDK."""

from __future__ import annotations

import shutil
import sys
from pathlib import Path


PACKAGE_NAME = "moysklad_remap_12_sdk"


def remove_generator_metadata(sdk_dir: Path) -> None:
    """Remove metadata that OpenAPI Generator writes despite the ignore list."""
    metadata = sdk_dir / ".openapi-generator"
    if metadata.is_dir():
        shutil.rmtree(metadata)


def move_package_to_src_layout(sdk_dir: Path) -> None:
    """Move the generated import package under the standard ``src`` layout."""
    source = sdk_dir / PACKAGE_NAME
    target = sdk_dir / "src" / PACKAGE_NAME
    if not source.is_dir():
        raise RuntimeError(f"Generated package not found: {source}")
    if target.exists():
        raise RuntimeError(
            f"Target package already exists: {target}. "
            "Clean clients/python manually before regeneration."
        )
    target.parent.mkdir(exist_ok=True)
    shutil.move(str(source), str(target))


def main() -> int:
    if len(sys.argv) != 2:
        print(
            "Usage: prepare-python-sdk-layout.py <sdk-dir>",
            file=sys.stderr,
        )
        return 2

    sdk_dir = Path(sys.argv[1]).resolve()
    if not sdk_dir.is_dir():
        print("Generated SDK directory is missing", file=sys.stderr)
        return 2

    remove_generator_metadata(sdk_dir)
    move_package_to_src_layout(sdk_dir)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
