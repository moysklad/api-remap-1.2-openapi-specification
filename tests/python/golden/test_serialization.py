from __future__ import annotations

import importlib
import json
import re
from pathlib import Path
from typing import Any

import pytest


FIXTURES = Path(__file__).resolve().parents[2] / "fixtures"

FIXTURE_MODEL_MAP = {
    "product": "Product",
    "product_full": "Product",
    "product_image": "Image",
    "product_storebalance": "StoreBalance",
    "counterparty": "Counterparty",
    "counterparty_legal": "Counterparty",
    "counterparty_metadata": "CounterpartyMetadata",
    "counterparty_metadata_expanded": "CounterpartyMetadata",
    "counterparty_metadata_minimum": "CounterpartyMetadata",
    "document_metadata": "DocumentMetadata",
    "document_metadata_expanded": "DocumentMetadata",
    "product_metadata": "Metadata",
    "product_metadata_expanded": "Metadata",
    "counterparty_account": "Account",
    "counterparty_contactperson": "ContactPerson",
    "counterparty_note": "Note",
    "event_note": "EventNote",
    "counterparty_file": "File",
    "currency": "Currency",
    "employee": "Employee",
    "employee_security": "EmployeeSecurity",
    "employee_role": "EmployeeRole",
    "custom_role": "CustomRole",
    "group": "Group",
    "entity_with_extra_field": "Group",
    "entity_with_attributes": "Product",
    "country": "Country",
    "region": "Region",
    "tax_rate": "TaxRate",
    "product_folder": "ProductFolder",
    "processing_stage": "ProcessingStage",
    "processing_process": "ProcessingProcess",
    "processing_plan": "ProcessingPlan",
    "processing_plan_expanded": "ProcessingPlan",
    "service": "Service",
    "uom": "Uom",
    "price_type": "PriceType",
    "sale_platform": "SalePlatform",
    "store": "Store",
    "retail_store": "RetailStore",
    "retail_shift": "RetailShift",
    "cashier": "Cashier",
    "bundle": "Bundle",
    "bundle_component": "BundleComponent",
    "variant": "Variant",
    "webhook": "Webhook",
    "webhookstock": "WebhookStock",
    "thing": "Thing",
    "internal_order": "InternalOrder",
    "processing_order": "ProcessingOrder",
    "processing": "Processing",
    "customer_order": "CustomerOrder",
    "invoice_out": "InvoiceOut",
    "invoice_in": "InvoiceIn",
    "demand": "Demand",
    "move": "Move",
    "retail_demand": "RetailDemand",
    "retail_sales_return": "RetailSalesReturn",
    "enter": "Enter",
    "sales_return": "SalesReturn",
    "loss": "Loss",
    "purchase_order": "PurchaseOrder",
    "purchase_return": "PurchaseReturn",
    "supply": "Supply",
    "prepayment_return": "PrepaymentReturn",
    "emission_order": "EmissionOrder",
    "variantcharacteristic": "VariantCharacteristic",
    "contract": "Contract",
    "task": "Task",
    "sales_channel": "SalesChannel",
    "project": "Project",
    "consignment": "Consignment",
    "expense_item": "ExpenseItem",
    "cash_in": "CashIn",
    "retail_drawer_cash_in": "RetailDrawerCashIn",
    "retail_drawer_cash_out": "RetailDrawerCashOut",
    "inventory": "Inventory",
    "cash_out": "CashOut",
    "finance_in_operation_commission_report_in": "FinanceInOperationCommissionReportIn",
    "finance_in_operation_customer_order": "FinanceInOperationCustomerOrder",
    "finance_in_operation_demand": "FinanceInOperationDemand",
    "finance_in_operation_invoice_out": "FinanceInOperationInvoiceOut",
    "finance_in_operation_purchase_return": "FinanceInOperationPurchaseReturn",
    "finance_in_operation_retail_shift": "FinanceInOperationRetailShift",
    "finance_out_operation_commission_report_out": "FinanceOutOperationCommissionReportOut",
    "finance_out_operation_invoice_in": "FinanceOutOperationInvoiceIn",
    "finance_out_operation_purchase_order": "FinanceOutOperationPurchaseOrder",
    "finance_out_operation_sales_return": "FinanceOutOperationSalesReturn",
    "finance_out_operation_supply": "FinanceOutOperationSupply",
    "payment_in": "PaymentIn",
    "payment_out": "PaymentOut",
    "facture_in": "FactureIn",
    "facture_out": "FactureOut",
    "company_settings": "CompanySettings",
    "company_settings_metadata": "CompanySettingsMetadata",
    "user_settings": "UserSettings",
    "notification_settings": "NotificationSettings",
    "subscription": "Subscription",
    "assortment_settings": "AssortmentSettings",
    "assortment": "Assortment",
    "discount": "Discount",
    "accumulation_discount": "AccumulationDiscount",
    "personal_discount": "PersonalDiscount",
    "special_price_discount": "SpecialPriceDiscount",
    "bonus_program": "BonusProgram",
    "bonus_transaction": "BonusTransaction",
    "custom_entity": "CustomEntity",
    "custom_entity_element": "CustomEntityElement",
    "commission_report_in": "CommissionReportIn",
    "commission_report_out": "CommissionReportOut",
    "processing_plan_folder": "ProcessingPlanFolder",
    "organization": "Organization",
    "organization_account": "Account",
}

IGNORED_FIELDS = {
    "updated",
    "created",
    "accountId",
    "pathName",
    "effectiveVat",
    "effectiveVatEnabled",
    "variantsCount",
    "tobacco",
    "salesAmount",
    "bonusPoints",
    "extra_field",
    "additional_properties",
}


def snake_case(name: str) -> str:
    return re.sub(r"(?<!^)(?=[A-Z])", "_", name).lower()


def model_class(name: str) -> type:
    module = importlib.import_module(
        f"moysklad_remap_12_sdk.models.{snake_case(name)}"
    )
    return getattr(module, name)


def normalize(value: Any) -> Any:
    if isinstance(value, dict):
        normalized = {
            key: normalize(item)
            for key, item in value.items()
            if key not in IGNORED_FIELDS and item is not None
        }
        if "attributes" in normalized:
            for attribute in normalized["attributes"]:
                if isinstance(attribute, dict) and "value" in attribute:
                    attribute["value"] = normalize_attribute(attribute["value"])
        return dict(sorted(normalized.items()))
    if isinstance(value, list):
        return [normalize(item) for item in value]
    if isinstance(value, float) and value.is_integer():
        return int(value)
    return value


def normalize_attribute(value: Any) -> Any:
    if not isinstance(value, (list, dict)):
        return value
    items = list(value.values()) if isinstance(value, dict) else value
    if len(items) == 1 and isinstance(items[0], (str, int, float, bool)):
        return items[0]
    return next(
        (item for item in items if isinstance(item, (str, int, float, bool))),
        value,
    )


def coerce_integral_floats(value: Any) -> Any:
    if isinstance(value, dict):
        return {key: coerce_integral_floats(item) for key, item in value.items()}
    if isinstance(value, list):
        return [coerce_integral_floats(item) for item in value]
    if isinstance(value, float) and value.is_integer():
        return int(value)
    return value


def test_mapping_and_fixture_consistency() -> None:
    fixture_names = {path.stem for path in FIXTURES.glob("*.json")}
    assert fixture_names == set(FIXTURE_MODEL_MAP)
    for fixture_name, name in FIXTURE_MODEL_MAP.items():
        assert model_class(name), f"Model missing for {fixture_name}: {name}"


@pytest.mark.parametrize(
    ("fixture_name", "name"),
    FIXTURE_MODEL_MAP.items(),
    ids=FIXTURE_MODEL_MAP.keys(),
)
def test_roundtrip_serialization(fixture_name: str, name: str) -> None:
    original = json.loads((FIXTURES / f"{fixture_name}.json").read_text())
    model = model_class(name).from_dict(coerce_integral_floats(original))
    assert model is not None
    serialized = model.to_dict()
    assert normalize(serialized) == normalize(original)


@pytest.mark.parametrize(
    ("wrapper_name", "entity_type"),
    [
        ("RetailShiftPaymentsInner", "paymentin"),
        ("SupplyPaymentsInner", "paymentout"),
    ],
)
def test_payment_union_roundtrip(wrapper_name: str, entity_type: str) -> None:
    data = {
        "meta": {
            "href": (
                "https://api.moysklad.ru/api/remap/1.2/entity/"
                f"{entity_type}/00000000-0000-0000-0000-000000000001"
            ),
            "type": entity_type,
            "mediaType": "application/json",
        }
    }
    wrapper = model_class(wrapper_name).from_dict(data)
    assert normalize(wrapper.to_dict()) == normalize(data)


def test_batch_error_fallback_resolves_unknown_discriminator() -> None:
    data = {
        "meta": {"type": "futuretype"},
        "errors": [{"error": "Invalid JSON structure", "code": 1000}],
    }
    model = model_class("BatchResponseEntity").from_dict(data)
    assert isinstance(model, model_class("Error"))


def test_batch_error_fallback_requires_error_markers() -> None:
    data = {
        "meta": {"type": "futuretype"},
        "errors": [{"error": 1000}],
    }
    model = model_class("BatchResponseEntity").from_dict(data)
    assert type(model) is model_class("BatchResponseEntity")
