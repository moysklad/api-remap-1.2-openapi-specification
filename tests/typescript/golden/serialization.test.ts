/**
 * Golden тесты сериализации/десериализации моделей TypeScript SDK.
 *
 * По каждой fixture из tests/fixtures (общие с PHP и Java golden-тестами) выполняется
 * roundtrip `fixture -> <Model>FromJSON -> <Model>ToJSON` и проверяется, что:
 * 1) значения не искажены и в выводе нет ключей, которых нет в fixture;
 * 2) поля не потеряны, кроме readOnly, которые генератор сознательно не сериализует.
 *
 * @see https://dev.moysklad.ru/doc/api/remap/1.2/
 */

import assert from 'node:assert/strict';
import test from 'node:test';

import { diffRoundtrip, formatValueDiffs, normalizeForComparison } from '../src/compare.js';
import { getFixturesPath, listFixtureNames, loadFixture } from '../src/fixtures.js';
import {
    getAllFieldsOmittedOnSerialization,
    getFieldsOmittedOnSerialization,
    getFromJson,
    getToJson,
    hasModel,
    loadSdk,
} from '../src/sdk.js';

/**
 * Маппинг fixture-файлов на модели SDK: ключ — имя файла без расширения,
 * значение — имя модели в сгенерированном пакете.
 */
const FIXTURE_MODEL_MAP: Readonly<Record<string, string>> = {
    product: 'Product',
    product_full: 'Product',
    product_image: 'Image',
    product_storebalance: 'StoreBalance',
    counterparty: 'Counterparty',
    counterparty_legal: 'Counterparty',
    counterparty_metadata: 'CounterpartyMetadata',
    counterparty_metadata_expanded: 'CounterpartyMetadata',
    counterparty_metadata_minimum: 'CounterpartyMetadata',
    document_metadata: 'DocumentMetadata',
    document_metadata_expanded: 'DocumentMetadata',
    product_metadata: 'Metadata',
    product_metadata_expanded: 'Metadata',
    counterparty_account: 'Account',
    counterparty_contactperson: 'ContactPerson',
    counterparty_note: 'Note',
    event_note: 'EventNote',
    // Схема File переименована генератором в ModelFile (конфликт со встроенным типом File)
    counterparty_file: 'ModelFile',
    currency: 'Currency',
    employee: 'Employee',
    employee_security: 'EmployeeSecurity',
    employee_role: 'EmployeeRole',
    custom_role: 'CustomRole',
    group: 'Group',
    entity_with_extra_field: 'Group',
    entity_with_attributes: 'Product',
    country: 'Country',
    region: 'Region',
    tax_rate: 'TaxRate',
    product_folder: 'ProductFolder',
    processing_stage: 'ProcessingStage',
    processing_process: 'ProcessingProcess',
    processing_plan: 'ProcessingPlan',
    processing_plan_expanded: 'ProcessingPlan',
    processing_plan_folder: 'ProcessingPlanFolder',
    service: 'Service',
    uom: 'Uom',
    price_type: 'PriceType',
    sale_platform: 'SalePlatform',
    report_dashboard: 'ReportDashboard',
    report_orders_plotseries_list: 'ReportOrdersPlotSeriesList',
    report_sales_plotseries_list: 'ReportSalesPlotSeriesList',
    stock_all_consignment: 'StockAll',
    stock_all_product: 'StockAll',
    stock_all_variant: 'StockAll',
    stock_by_store: 'StockByStore',
    stock_by_operation: 'StockByOperation',
    by_operations_stock: 'ByOperationsStock',
    by_operations_reserve: 'ByOperationsReserve',
    by_operations_intransit: 'ByOperationsInTransit',
    store: 'Store',
    retail_store: 'RetailStore',
    retail_shift: 'RetailShift',
    cashier: 'Cashier',
    bundle: 'Bundle',
    bundle_component: 'BundleComponent',
    variant: 'Variant',
    webhook: 'Webhook',
    webhookstock: 'WebhookStock',
    thing: 'Thing',
    internal_order: 'InternalOrder',
    processing_order: 'ProcessingOrder',
    processing: 'Processing',
    customer_order: 'CustomerOrder',
    invoice_out: 'InvoiceOut',
    invoice_in: 'InvoiceIn',
    demand: 'Demand',
    move: 'Move',
    retail_demand: 'RetailDemand',
    retail_sales_return: 'RetailSalesReturn',
    enter: 'Enter',
    sales_return: 'SalesReturn',
    loss: 'Loss',
    purchase_order: 'PurchaseOrder',
    purchase_return: 'PurchaseReturn',
    supply: 'Supply',
    prepayment_return: 'PrepaymentReturn',
    emission_order: 'EmissionOrder',
    variantcharacteristic: 'VariantCharacteristic',
    contract: 'Contract',
    task: 'Task',
    sales_channel: 'SalesChannel',
    project: 'Project',
    consignment: 'Consignment',
    expense_item: 'ExpenseItem',
    cash_in: 'CashIn',
    cash_out: 'CashOut',
    retail_drawer_cash_in: 'RetailDrawerCashIn',
    retail_drawer_cash_out: 'RetailDrawerCashOut',
    inventory: 'Inventory',
    finance_in_operation_commission_report_in: 'FinanceInOperationCommissionReportIn',
    finance_in_operation_customer_order: 'FinanceInOperationCustomerOrder',
    finance_in_operation_demand: 'FinanceInOperationDemand',
    finance_in_operation_invoice_out: 'FinanceInOperationInvoiceOut',
    finance_in_operation_purchase_return: 'FinanceInOperationPurchaseReturn',
    finance_in_operation_retail_shift: 'FinanceInOperationRetailShift',
    finance_out_operation_commission_report_out: 'FinanceOutOperationCommissionReportOut',
    finance_out_operation_invoice_in: 'FinanceOutOperationInvoiceIn',
    finance_out_operation_purchase_order: 'FinanceOutOperationPurchaseOrder',
    finance_out_operation_sales_return: 'FinanceOutOperationSalesReturn',
    finance_out_operation_supply: 'FinanceOutOperationSupply',
    payment_in: 'PaymentIn',
    payment_out: 'PaymentOut',
    facture_in: 'FactureIn',
    facture_out: 'FactureOut',
    company_settings: 'CompanySettings',
    company_settings_metadata: 'CompanySettingsMetadata',
    user_settings: 'UserSettings',
    notification_settings: 'NotificationSettings',
    subscription: 'Subscription',
    assortment_settings: 'AssortmentSettings',
    assortment: 'Assortment',
    discount: 'Discount',
    accumulation_discount: 'AccumulationDiscount',
    personal_discount: 'PersonalDiscount',
    special_price_discount: 'SpecialPriceDiscount',
    bonus_program: 'BonusProgram',
    bonus_transaction: 'BonusTransaction',
    custom_entity: 'CustomEntity',
    custom_entity_element: 'CustomEntityElement',
    commission_report_in: 'CommissionReportIn',
    commission_report_out: 'CommissionReportOut',
    organization: 'Organization',
    organization_account: 'Account',
};

const sdk = await loadSdk();

function asObject(value: unknown, message: string): Record<string, unknown> {
    assert.ok(value !== null && typeof value === 'object' && !Array.isArray(value), message);

    return value as Record<string, unknown>;
}

test('fixtures, маппинг моделей и сгенерированный SDK согласованы', () => {
    const fixtureNames = listFixtureNames();
    assert.notEqual(fixtureNames.length, 0, `Fixtures не найдены: ${getFixturesPath()}`);

    for (const fixtureName of fixtureNames) {
        assert.ok(
            Object.hasOwn(FIXTURE_MODEL_MAP, fixtureName),
            `В FIXTURE_MODEL_MAP нет модели для fixture ${fixtureName}.json`,
        );
    }

    for (const [fixtureName, modelName] of Object.entries(FIXTURE_MODEL_MAP)) {
        assert.ok(
            fixtureNames.includes(fixtureName),
            `Не найден fixture для записи маппинга: ${fixtureName}.json`,
        );
        assert.ok(
            hasModel(sdk, modelName),
            `SDK не экспортирует ${modelName}FromJSON/${modelName}ToJSON (fixture ${fixtureName})`,
        );
    }

});

test('неизвестные спецификации поля не ломают десериализацию', () => {
    const fixture = loadFixture('entity_with_extra_field');
    assert.ok('extra_field' in fixture, 'fixture должен содержать неизвестное спецификации поле extra_field');

    const model = asObject(getFromJson(sdk, 'Group')(fixture), 'Модель не создана');

    assert.equal('extra_field' in model, false, 'Неизвестное поле не должно попадать в модель');
    assert.equal(model['name'], fixture['name'], 'Известные поля должны сохраняться');
});

for (const [fixtureName, modelName] of Object.entries(FIXTURE_MODEL_MAP)) {
    test(`roundtrip: ${fixtureName} -> ${modelName}`, () => {
        const fixture = loadFixture(fixtureName);
        const model = asObject(
            getFromJson(sdk, modelName)(fixture),
            `Не удалось десериализовать ${fixtureName}.json в ${modelName}`,
        );
        const serialized = asObject(
            getToJson(sdk, modelName)(model),
            `Сериализация ${modelName} должна вернуть объект`,
        );

        const { valueDiffs, missingFields } = diffRoundtrip(
            normalizeForComparison(fixture),
            normalizeForComparison(serialized),
        );

        assert.equal(
            valueDiffs.length,
            0,
            `Roundtrip ${fixtureName} через ${modelName} изменил данные:\n${formatValueDiffs(valueDiffs)}`,
        );

        // Пропуск поля допустим только для readOnly: на верхнем уровне — по самой модели,
        // во вложенных объектах — по readOnly-полям всех моделей (модель поля неизвестна).
        const modelOmittedFields = getFieldsOmittedOnSerialization(modelName);
        const allOmittedFields = getAllFieldsOmittedOnSerialization();
        const lostFields = missingFields
            .filter((field) => (field.depth === 0 ? !modelOmittedFields.has(field.key) : !allOmittedFields.has(field.key)))
            .map((field) => field.path)
            .sort();

        assert.deepEqual(
            lostFields,
            [],
            `Roundtrip ${fixtureName} через ${modelName} потерял поля`
                + ` (поля readOnly исключены из проверки): ${lostFields.join(', ')}`,
        );
    });
}
