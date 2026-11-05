#!/usr/bin/env python3
"""Validate the package layout and metadata contained in a Python wheel."""

from __future__ import annotations

import sys
from pathlib import Path
from zipfile import ZipFile


PACKAGE_NAME = "moysklad_remap_12_sdk"
REQUIRED_FILES = {
    f"{PACKAGE_NAME}/__init__.py",
    f"{PACKAGE_NAME}/api/__init__.py",
    f"{PACKAGE_NAME}/models/__init__.py",
    f"{PACKAGE_NAME}/api_client.py",
    f"{PACKAGE_NAME}/py.typed",
}


def main() -> int:
    if len(sys.argv) != 2:
        print("Usage: verify-python-wheel.py <wheel>", file=sys.stderr)
        return 2

    wheel = Path(sys.argv[1])
    if not wheel.is_file() or wheel.suffix != ".whl":
        print(f"Wheel not found: {wheel}", file=sys.stderr)
        return 2

    with ZipFile(wheel) as archive:
        names = set(archive.namelist())

    missing = sorted(REQUIRED_FILES - names)
    if missing:
        print(f"Wheel is missing required files: {missing}", file=sys.stderr)
        return 1

    unexpected_packages = sorted(
        name
        for name in names
        if "/" in name
        and not name.startswith(f"{PACKAGE_NAME}/")
        and ".dist-info/" not in name
    )
    if unexpected_packages:
        print(
            f"Wheel contains unexpected package files: {unexpected_packages[:10]}",
            file=sys.stderr,
        )
        return 1

    dist_info = {
        name.split("/", 1)[0]
        for name in names
        if ".dist-info/" in name
    }
    if len(dist_info) != 1:
        print(f"Expected one dist-info directory, found: {sorted(dist_info)}", file=sys.stderr)
        return 1

    print(
        f"Wheel verified: {wheel.name}; "
        f"{len(names)} files; package={PACKAGE_NAME}; metadata={dist_info.pop()}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
