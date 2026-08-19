from __future__ import annotations

import sys
from pathlib import Path


REPOSITORY_ROOT = Path(__file__).resolve().parents[2]
SDK_ROOT = REPOSITORY_ROOT / "clients" / "python"
SDK_SOURCE = SDK_ROOT / "src"

if not SDK_SOURCE.is_dir():
    raise RuntimeError("Python SDK is not generated. Run: make generate-python")

sys.path.insert(0, str(SDK_SOURCE))
