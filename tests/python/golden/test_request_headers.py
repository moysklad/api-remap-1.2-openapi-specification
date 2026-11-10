"""Заголовки запроса берутся из default в спецификации и не дублируются."""

from __future__ import annotations

import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Dict, List

import pytest

from moysklad_remap_12_sdk import ApiClient, Configuration, ProductsApi, SuppliesApi


class HeaderCapture:
    def __init__(self) -> None:
        self.headers: Dict[str, List[str]] = {}
        self._server = ThreadingHTTPServer(("127.0.0.1", 0), self._handler_class())
        self._thread = threading.Thread(target=self._server.serve_forever, daemon=True)
        self._thread.start()

    @property
    def base_path(self) -> str:
        host, port = self._server.server_address
        return f"http://{host}:{port}/api/remap/1.2"

    def close(self) -> None:
        self._server.shutdown()
        self._server.server_close()

    def values(self, name: str) -> List[str]:
        return self.headers.get(name.lower(), [])

    def _handler_class(self) -> type[BaseHTTPRequestHandler]:
        capture = self

        class Handler(BaseHTTPRequestHandler):
            def do_GET(self) -> None:
                self._record()

            def do_POST(self) -> None:
                self._record()

            def log_message(self, format: str, *args: object) -> None:
                pass

            def _record(self) -> None:
                length = int(self.headers.get("Content-Length") or 0)
                self.rfile.read(length)
                recorded: Dict[str, List[str]] = {}
                for key, value in self.headers.items():
                    recorded.setdefault(key.lower(), []).append(value)
                capture.headers = recorded
                body = b'{"rows":[]}'
                self.send_response(200)
                self.send_header("Content-Type", "application/json")
                self.send_header("Content-Length", str(len(body)))
                self.end_headers()
                self.wfile.write(body)

        return Handler


@pytest.fixture
def capture() -> HeaderCapture:
    server = HeaderCapture()
    yield server
    server.close()


def _client(capture: HeaderCapture) -> ApiClient:
    return ApiClient(Configuration(host=capture.base_path))


def test_default_headers_come_from_specification(capture: HeaderCapture) -> None:
    try:
        ProductsApi(_client(capture)).get_products(limit=1)
    except Exception:
        pass

    assert capture.values("accept") == ["application/json;charset=utf-8"]
    assert capture.values("accept-encoding") == ["gzip"]


def test_explicit_accept_replaces_default_header(capture: HeaderCapture) -> None:
    try:
        ProductsApi(_client(capture)).get_products(
            limit=1,
            accept="application/json;charset=utf-8",
            accept_encoding="gzip",
        )
    except Exception:
        pass

    assert capture.values("accept") == ["application/json;charset=utf-8"]
    assert capture.values("accept-encoding") == ["gzip"]


def test_supply_trash_sends_json_content_type(capture: HeaderCapture) -> None:
    try:
        SuppliesApi(_client(capture)).move_supply_to_trash("00000000-0000-0000-0000-000000000001")
    except Exception:
        pass

    assert capture.values("content-type") == ["application/json"]
