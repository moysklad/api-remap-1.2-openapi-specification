"""Document payments select a concrete model even for an unexpanded meta link."""
import json
from pathlib import Path

import pytest

from test_serialization import model_class

CASES = json.loads((Path(__file__).resolve().parents[2] / "fixtures/polymorphic/document_payments.json").read_text())


# Every variant covers a reference and an expanded payment with linkedSum where the document has it.
# Zero needs a separate regression check only for the two representative variants.
SCENARIOS = [
    pytest.param(case, expanded, 123.45 if expanded and case.get("linkedSum", True) else None,
                 id=f'{case["variant"]}-{"expanded" if expanded else "reference"}')
    for case in CASES for expanded in [False, True]
] + [
    pytest.param(case, True, 0, id=f'{case["variant"]}-zero')
    for case in CASES if case["document"] == "RetailShift"
]


@pytest.mark.parametrize("case,expanded,linked_sum", SCENARIOS)
def test_document_payments(case, expanded, linked_sum):
    payment = {"meta": {"href": f'https://api.moysklad.ru/api/remap/1.2/entity/{case["type"]}/1',
                        "type": case["type"], "mediaType": "application/json"}}
    if expanded:
        payment.update(name="Expanded payment", sum=456.75, paymentPurpose="Invoice settlement")
    if linked_sum is not None:
        payment["linkedSum"] = linked_sum
    owner = model_class(case["document"]).from_dict({"payments": [payment]})
    actual = owner.payments[0]
    assert type(actual) is model_class(case["variant"])
    assert isinstance(actual, model_class(case["base"]))
    assert actual.meta.type == case["type"]
    assert actual.meta.href == payment["meta"]["href"]
    assert actual.name == ("Expanded payment" if expanded else None)
    if expanded:
        assert actual.sum == 456.75
        assert actual.payment_purpose == "Invoice settlement"
    serialized = owner.to_dict()["payments"][0]
    assert {key: value for key, value in serialized.items() if value is not None} == payment
    assert ("linkedSum" in serialized) == (linked_sum is not None)
    if linked_sum is not None:
        assert serialized["linkedSum"] == linked_sum
    assert type(model_class(case["document"]).from_dict({"payments": [serialized]}).payments[0]) is type(actual)
