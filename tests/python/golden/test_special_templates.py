from __future__ import annotations

import importlib
import re
from pathlib import Path
from typing import Any

import pytest
import yaml
from pydantic import BaseModel, ValidationError


ROOT = Path(__file__).resolve().parents[3]
SPEC_PATH = ROOT / "src" / "openapi.yaml"


def load_schemas() -> dict[str, dict[str, Any]]:
    specification = yaml.safe_load(SPEC_PATH.read_text(encoding="utf-8"))
    schemas: dict[str, dict[str, Any]] = {}
    for name, schema in specification["components"]["schemas"].items():
        if isinstance(schema, dict) and "$ref" in schema:
            reference, _, fragment = schema["$ref"].partition("#")
            if not reference:
                schemas[name] = schema
                continue
            target: Any = yaml.safe_load(
                (SPEC_PATH.parent / reference).resolve().read_text(encoding="utf-8")
            )
            if fragment:
                for part in fragment.strip("/").split("/"):
                    target = target[part]
            schemas[name] = target
        else:
            schemas[name] = schema
    return schemas


SCHEMAS = load_schemas()


def snake_case(name: str) -> str:
    name = re.sub(r"(.)([A-Z][a-z]+)", r"\1_\2", name)
    return re.sub(r"([a-z0-9])([A-Z])", r"\1_\2", name).lower()


def model_class(name: str) -> type[BaseModel]:
    class_name = name[:1].upper() + name[1:]
    module = importlib.import_module(
        f"moysklad_remap_12_sdk.models.{snake_case(class_name)}"
    )
    return getattr(module, class_name)


PARENTS = [
    (name, schema["x-polymorphic-parent"])
    for name, schema in SCHEMAS.items()
    if isinstance(schema, dict) and schema.get("x-polymorphic-parent")
]
# Например, path=meta.type проверяется payload вида {"meta": {"type": "<mapping>"}}.
DISCRIMINATORS = [
    (name, extension, mapping)
    for name, schema in SCHEMAS.items()
    if isinstance(schema, dict)
    for extension in [schema.get("x-polymorphic-discriminator")]
    if extension
    for mapping in extension["mappings"]
]
FALLBACKS = [
    (name, extension)
    for name, schema in SCHEMAS.items()
    if isinstance(schema, dict)
    for extension in [schema.get("x-polymorphic-discriminator")]
    if extension and extension.get("batchErrorFallback")
]
BUILDERS = [
    (name, schema["x-entity-static-builder"])
    for name, schema in SCHEMAS.items()
    if isinstance(schema, dict) and schema.get("x-entity-static-builder")
]
AGENT_REFERENCES = [
    (name, property_name)
    for name, schema in SCHEMAS.items()
    if isinstance(schema, dict)
    for property_name, prop in schema.get("properties", {}).items()
    if isinstance(prop, dict) and prop.get("x-agent-reference") is True
]
NON_AGENT_REFERENCES = [
    (name, property_name, prop["$ref"].rsplit("/", 1)[-1])
    for name, schema in SCHEMAS.items()
    if isinstance(schema, dict)
    for property_name, prop in schema.get("properties", {}).items()
    if isinstance(prop, dict) and prop.get("x-agent-reference") is False
]


def nested_discriminator(path: str, value: str) -> dict[str, Any]:
    result: dict[str, Any] = {}
    current = result
    parts = path.split(".")
    for part in parts[:-1]:
        child: dict[str, Any] = {}
        current[part] = child
        current = child
    current[parts[-1]] = value
    return result


@pytest.mark.parametrize(
    ("name", "parent_name"),
    PARENTS,
    ids=[f"{name}->{parent}" for name, parent in PARENTS],
)
def test_every_polymorphic_parent_is_inherited(
    name: str,
    parent_name: str,
) -> None:
    """Проверяет наследование моделей из x-polymorphic-parent."""
    assert model_class(parent_name) in model_class(name).__mro__[1:]


@pytest.mark.parametrize(
    ("name", "extension", "mapping"),
    DISCRIMINATORS,
    ids=[
        f"{name}:{mapping['type']}->{mapping['componentName']}"
        for name, _, mapping in DISCRIMINATORS
    ],
)
def test_every_nested_discriminator_mapping_dispatches(
    monkeypatch: pytest.MonkeyPatch,
    name: str,
    extension: dict[str, Any],
    mapping: dict[str, str],
) -> None:
    """Проверяет выбор модели по вложенному discriminator mapping."""
    target = model_class(mapping["componentName"])
    sentinel = object()
    monkeypatch.setattr(
        target,
        "from_dict",
        classmethod(lambda cls, obj: sentinel),
    )

    payload = nested_discriminator(extension["path"], mapping["type"])
    assert model_class(name).from_dict(payload) is sentinel


@pytest.mark.parametrize(
    ("name", "extension"),
    FALLBACKS,
    ids=[name for name, _ in FALLBACKS],
)
def test_every_batch_error_fallback_dispatches(
    monkeypatch: pytest.MonkeyPatch,
    name: str,
    extension: dict[str, Any],
) -> None:
    """Проверяет fallback в Error для неизвестного batch discriminator."""
    error_class = model_class("Errors")
    sentinel = object()
    monkeypatch.setattr(
        error_class,
        "from_dict",
        classmethod(lambda cls, obj: sentinel),
    )
    payload = nested_discriminator(extension["path"], "unknown-future-type")
    payload["errors"] = [{"error": "Invalid JSON structure"}]

    assert model_class(name).from_dict(payload) is sentinel


@pytest.mark.parametrize(
    ("name", "extension"),
    BUILDERS,
    ids=[name for name, _ in BUILDERS],
)
def test_every_static_builder_sets_id_meta_and_href(
    name: str,
    extension: dict[str, Any],
) -> None:
    """Проверяет id и meta, созданные статическим builder."""
    from moysklad_remap_12_sdk.configuration import Configuration

    values = {
        parameter: f"{parameter}-00000000-0000-0000-0000-000000000001"
        for parameter in extension["methodParams"]
    }
    entity = model_class(name).create_with_meta(**values)

    expected_href = Configuration.get_default().host
    for part in extension["href"]:
        expected_href += "/" + (
            part["path"] if "path" in part else values[part["param"]]
        )

    assert entity.id == values["id"]
    assert entity.meta is not None
    assert entity.meta.href == expected_href
    assert entity.meta.type == extension["type"]
    assert entity.meta.media_type == "application/json"
    assert entity.to_dict()["id"] == values["id"]


@pytest.mark.parametrize(
    ("name", "property_name"),
    AGENT_REFERENCES,
    ids=[f"{name}.{prop}" for name, prop in AGENT_REFERENCES],
)
def test_every_agent_reference_accepts_supported_entities(
    name: str,
    property_name: str,
) -> None:
    """Проверяет допустимые типы сущностей для x-agent-reference."""
    from moysklad_remap_12_sdk.models.agent import Agent
    from moysklad_remap_12_sdk.models.counterparty import Counterparty
    from moysklad_remap_12_sdk.models.employee import Employee
    from moysklad_remap_12_sdk.models.meta import Meta
    from moysklad_remap_12_sdk.models.organization import Organization

    for reference_class, entity_type in (
        (Agent, "counterparty"),
        (Counterparty, "counterparty"),
        (Organization, "organization"),
        (Employee, "employee"),
    ):
        reference = reference_class(
            meta=Meta(
                href=f"https://example.test/entity/{entity_type}/id",
                type=entity_type,
                media_type="application/json",
            )
        )
        owner = model_class(name)(**{snake_case(property_name): reference})
        assert getattr(owner, snake_case(property_name)) is reference


@pytest.mark.parametrize(
    ("name", "property_name"),
    AGENT_REFERENCES,
    ids=[f"{name}.{prop}" for name, prop in AGENT_REFERENCES],
)
def test_every_agent_reference_rejects_other_entities(
    name: str,
    property_name: str,
) -> None:
    """Проверяет отклонение неподдерживаемых x-agent-reference сущностей."""
    from moysklad_remap_12_sdk.models.store import Store

    with pytest.raises(ValidationError):
        model_class(name)(**{snake_case(property_name): Store()})


@pytest.mark.parametrize(
    ("name", "property_name", "target_name"),
    NON_AGENT_REFERENCES,
    ids=[f"{name}.{prop}->{target}" for name, prop, target in NON_AGENT_REFERENCES],
)
def test_every_non_agent_reference_keeps_declared_type(
    name: str,
    property_name: str,
    target_name: str,
) -> None:
    """Проверяет сохранение исходного типа без x-agent-reference."""
    from moysklad_remap_12_sdk.models.agent import Agent

    field_name = snake_case(property_name)
    target = model_class(target_name)()
    owner = model_class(name)(**{field_name: target})
    assert getattr(owner, field_name) is target

    with pytest.raises(ValidationError):
        model_class(name)(**{field_name: Agent()})


def test_special_models_allow_extra_and_arbitrary_types() -> None:
    """Проверяет Pydantic config у моделей со спецрасширениями."""
    special_names = {
        *(name for name, _ in PARENTS),
        *(parent for _, parent in PARENTS),
        *(name for name, _ in BUILDERS),
        *(name for name, _ in FALLBACKS),
        *(name for name, _ in AGENT_REFERENCES),
    }
    for name in special_names:
        config = model_class(name).model_config
        assert config["extra"] == "allow", name
        assert config["arbitrary_types_allowed"] is True, name


def test_from_dict_preserves_inherited_extra_fields() -> None:
    """Проверяет сохранение extra-полей у унаследованной модели."""
    from moysklad_remap_12_sdk.models.product import Product

    product = Product.from_dict(
        {
            "meta": {
                "href": "https://example.test/entity/product/id",
                "type": "product",
                "mediaType": "application/json",
            },
            "name": "Template product",
            "futureField": "preserved",
        }
    )
    assert product is not None
    assert product.to_dict()["futureField"] == "preserved"
    assert product.to_dict()["meta"]["type"] == "product"
