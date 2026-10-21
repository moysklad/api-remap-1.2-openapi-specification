#!/usr/bin/env python3
"""Set the generated Python distribution version safely."""

from __future__ import annotations

import re
import sys
from pathlib import Path


PEP440_VERSION = re.compile(
    r"^[0-9]+(?:\.[0-9]+){1,2}(?:(?:a|b|rc)[0-9]+|\.post[0-9]+|\.dev[0-9]+)?$"
)


def replace_once(path: Path, pattern: str, replacement: str) -> None:
    content = path.read_text(encoding="utf-8")
    updated, count = re.subn(pattern, replacement, content, count=1, flags=re.MULTILINE)
    if count != 1:
        raise RuntimeError(f"Expected one version declaration in {path}, found {count}")
    path.write_text(updated, encoding="utf-8")


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: set-python-package-version.py <sdk-dir> <version>", file=sys.stderr)
        return 2

    sdk_dir = Path(sys.argv[1]).resolve()
    version = sys.argv[2]
    if not PEP440_VERSION.fullmatch(version):
        print(f"Invalid PEP 440 release version: {version}", file=sys.stderr)
        return 2
    if not sdk_dir.is_dir():
        print(f"SDK directory does not exist: {sdk_dir}", file=sys.stderr)
        return 2

    replace_once(
        sdk_dir / "pyproject.toml",
        r'^version = "[^"]+"$',
        f'version = "{version}"',
    )
    setup_py = sdk_dir / "setup.py"
    if setup_py.exists():
        replace_once(
            setup_py,
            r'^VERSION = "[^"]+"$',
            f'VERSION = "{version}"',
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
