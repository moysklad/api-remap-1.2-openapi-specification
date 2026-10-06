"""Location response object."""

from __future__ import annotations
from typing import Mapping, Optional


class LocationResponse:
    """
    Ответ вызова, полезные данные которого передаются в заголовке Location,
    а тело ответа отсутствует. Значения принадлежат конкретному вызову.
    """

    def __init__(self, status_code: int, location: Optional[str]) -> None:
        self._status_code = status_code
        self._location = location

    @classmethod
    def from_headers(cls, status_code: int, headers: Mapping[str, str]) -> LocationResponse:
        """Имя заголовка Location сравнивается без учета регистра."""
        for header_name, value in headers.items():
            if header_name.lower() == "location":
                return cls(status_code, value)
        return cls(status_code, None)

    @property
    def status_code(self) -> int:
        return self._status_code

    @property
    def location(self) -> Optional[str]:
        """Значение заголовка Location или None, если заголовка нет."""
        return self._location
