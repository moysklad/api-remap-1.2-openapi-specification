from __future__ import annotations

import pytest
from pydantic import ValidationError

from moysklad_remap_12_sdk.models.agent import Agent
from moysklad_remap_12_sdk.models.cash_in import CashIn
from moysklad_remap_12_sdk.models.counterparty import Counterparty
from moysklad_remap_12_sdk.models.employee import Employee
from moysklad_remap_12_sdk.models.facture_out import FactureOut
from moysklad_remap_12_sdk.models.meta import Meta
from moysklad_remap_12_sdk.models.organization import Organization
from moysklad_remap_12_sdk.models.store import Store


def meta(entity_type: str) -> Meta:
    return Meta(
        href=(
            "https://api.moysklad.ru/api/remap/1.2/entity/"
            f"{entity_type}/00000000-0000-0000-0000-000000000001"
        ),
        type=entity_type,
        media_type="application/json",
    )


@pytest.mark.parametrize(
    ("reference", "expected_type"),
    [
        (Counterparty(meta=meta("counterparty"), name="Acme"), "counterparty"),
        (Organization(meta=meta("organization"), name="Sphere"), "organization"),
        (Employee(meta=meta("employee"), name="Ivan"), "employee"),
        (Agent(meta=meta("counterparty")), "counterparty"),
    ],
)
def test_agent_accepts_and_serializes_reference(
    reference: Agent,
    expected_type: str,
) -> None:
    serialized = CashIn(agent=reference).to_dict()
    assert serialized["agent"]["meta"]["type"] == expected_type


def test_agent_rejects_arbitrary_type() -> None:
    with pytest.raises(ValidationError):
        CashIn(agent=Store(meta=meta("store")))


def test_nullable_consignee_accepts_none() -> None:
    assert FactureOut(consignee=None).consignee is None


def test_agent_deserializes_by_nested_meta_type() -> None:
    result = Agent.from_dict(
        {
            "meta": {
                "href": (
                    "https://api.moysklad.ru/api/remap/1.2/entity/"
                    "counterparty/00000000-0000-0000-0000-000000000001"
                ),
                "type": "counterparty",
                "mediaType": "application/json",
            },
            "name": "Acme",
            "legalTitle": "Acme LLC",
            "inn": "7700000000",
        }
    )
    assert isinstance(result, Counterparty)
    assert result.name == "Acme"
    assert result.legal_title == "Acme LLC"
    assert result.inn == "7700000000"
