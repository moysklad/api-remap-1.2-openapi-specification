"""Контракт операций, полезные данные которых передаются в заголовке Location.

Проверяется на локальном HTTP-стабе: openapi-mock не отдаёт заголовки из спецификации.
"""

from __future__ import annotations

import threading
from collections import deque
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Iterator, NamedTuple, Optional

import pytest

from moysklad_remap_12_sdk import ApiClient, Configuration, LabelsApi
from moysklad_remap_12_sdk.exceptions import ApiException
from moysklad_remap_12_sdk.location_response import LocationResponse
from moysklad_remap_12_sdk.models.label_print_request import LabelPrintRequest


ENTITY_ID = "12345678-1234-1234-1234-123456789012"
LOCATION_TARGET_PATH = "/print-result"


class StubResponse(NamedTuple):
    status: int
    header_name: Optional[str] = None
    header_value: Optional[str] = None
    body: Optional[str] = None


class LocationStubServer:
    """Локальный HTTP-стаб с фиксированными ответами и счётчиком обращений к адресу из Location.

    http.server отправляет имя заголовка в том регистре, в котором его задал тест.
    """

    def __init__(self) -> None:
        self._responses: deque[StubResponse] = deque()
        self.location_hits = 0
        self._server = ThreadingHTTPServer(("127.0.0.1", 0), self._handler_class())
        self._thread = threading.Thread(target=self._server.serve_forever, daemon=True)
        self._thread.start()

    @property
    def base_path(self) -> str:
        return f"http://127.0.0.1:{self._server.server_port}/api/remap/1.2"

    def location_url(self, name: str) -> str:
        return f"http://127.0.0.1:{self._server.server_port}{LOCATION_TARGET_PATH}/{name}"

    def enqueue(self, response: StubResponse) -> None:
        """Ответы отдаются в порядке постановки в очередь, по одному на вызов."""
        self._responses.append(response)

    def close(self) -> None:
        self._server.shutdown()
        self._server.server_close()

    def _handler_class(self) -> type[BaseHTTPRequestHandler]:
        stub = self

        class Handler(BaseHTTPRequestHandler):
            def do_GET(self) -> None:
                self._respond()

            def do_POST(self) -> None:
                self._respond()

            def log_message(self, format: str, *args: object) -> None:
                pass

            def _respond(self) -> None:
                self.rfile.read(int(self.headers.get("Content-Length") or 0))

                if self.path.startswith(LOCATION_TARGET_PATH):
                    stub.location_hits += 1
                    response = StubResponse(200)
                elif stub._responses:
                    response = stub._responses.popleft()
                else:
                    response = StubResponse(599)

                body = (response.body or "").encode("utf-8")
                self.send_response(response.status)
                if response.header_name is not None:
                    self.send_header(response.header_name, response.header_value or "")
                if body:
                    self.send_header("Content-Type", "application/json;charset=utf-8")
                self.send_header("Content-Length", str(len(body)))
                self.end_headers()
                self.wfile.write(body)

        return Handler


@pytest.fixture
def stub() -> Iterator[LocationStubServer]:
    server = LocationStubServer()
    yield server
    server.close()


@pytest.fixture
def labels_api(stub: LocationStubServer) -> LabelsApi:
    return LabelsApi(ApiClient(Configuration(host=stub.base_path)))


def print_request() -> LabelPrintRequest:
    return LabelPrintRequest.from_dict(
        {"organization": {}, "count": 1, "salePrice": {"priceType": {}}, "template": {}}
    )


def print_labels(labels_api: LabelsApi) -> LocationResponse:
    return labels_api.print_labels(ENTITY_ID, "product", print_request())


def test_keeps_status_and_location_of_pending_print(stub, labels_api) -> None:
    expected = stub.location_url("status")
    stub.enqueue(StubResponse(202, "Location", expected))

    result = print_labels(labels_api)

    assert isinstance(result, LocationResponse)
    assert result.status_code == 202
    assert result.location == expected
    assert stub.location_hits == 0


def test_keeps_status_and_location_of_ready_print_without_following_redirect(
    stub, labels_api
) -> None:
    expected = stub.location_url("file.pdf")
    stub.enqueue(StubResponse(303, "Location", expected))

    result = print_labels(labels_api)

    assert result.status_code == 303
    assert result.location == expected
    assert stub.location_hits == 0, "переход по Location выполняться не должен"


def test_reads_location_regardless_of_header_case(stub, labels_api) -> None:
    expected = stub.location_url("file.pdf")
    stub.enqueue(StubResponse(303, "location", expected))

    result = print_labels(labels_api)

    assert result.location == expected


def test_reports_missing_location_as_none(stub, labels_api) -> None:
    stub.enqueue(StubResponse(202))

    result = print_labels(labels_api)

    assert result.status_code == 202
    assert result.location is None


def test_keeps_error_contract_for_error_status(stub, labels_api) -> None:
    stub.enqueue(StubResponse(400, body='{"errors":[{"error":"stub","code":1}]}'))

    with pytest.raises(ApiException) as error:
        print_labels(labels_api)

    assert error.value.status == 400


def test_treats_unexpected_redirect_as_error(stub, labels_api) -> None:
    stub.enqueue(StubResponse(302, "Location", stub.location_url("file.pdf")))

    with pytest.raises(ApiException) as error:
        print_labels(labels_api)

    assert error.value.status == 302
    assert stub.location_hits == 0


def test_with_http_info_returns_redirect_without_following(stub, labels_api) -> None:
    expected = stub.location_url("file.pdf")
    stub.enqueue(StubResponse(303, "Location", expected))

    response = labels_api.print_labels_with_http_info(ENTITY_ID, "product", print_request())

    assert response.status_code == 303
    assert response.headers["Location"] == expected
    assert stub.location_hits == 0
