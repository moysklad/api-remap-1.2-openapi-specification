/**
 * Известные потери данных TypeScript SDK на roundtrip `FromJSON -> ToJSON`.
 *
 * Причина одна: шаблоны `customtemplates/typescript/` пока не реализуют расширения
 * спецификации `x-polymorphic-parent` (модель не наследует поля родителя) и
 * `x-polymorphic-discriminator` (дочерняя модель не выбирается по `meta.type`).
 * В PHP и Java это делают кастомные шаблоны, поэтому их golden-тесты проходят без пропусков.
 *
 * Ключ — имя fixture, значение — пути потерянных полей. Список сравнивается точно:
 * новая потеря роняет тест, исчезнувшая — тоже, поэтому вместе с доработкой шаблонов
 * запись нужно удалить. Искажение значений не допускается ни для одной fixture.
 */
export const KNOWN_SERIALIZATION_GAPS: Readonly<Record<string, readonly string[]>> = {
    product_full: [
        'supplier.meta',
    ],
    counterparty: [
        'bonusProgram.meta',
    ],
    entity_with_attributes: [
        'attributes[10].value.meta',
        'attributes[13].value.meta',
        'attributes[14].value.meta',
        'attributes[8].value.meta',
        'attributes[9].value.meta',
    ],
    processing_stage: [
        'performers[0].meta',
        'performers[1].meta',
    ],
    processing_plan_expanded: [
        'materials.rows[0].assortment.meta',
        'products.rows[0].assortment.meta',
    ],
    retail_store: [
        'acquire.meta',
        'qrAcquire.meta',
    ],
    retail_shift: [
        'acquire.meta',
        'qrAcquire.meta',
    ],
    internal_order: [
        'positions.rows[0].assortment.meta',
    ],
    processing: [
        'materials.rows[0].assortment.meta',
        'products.rows[0].assortment.meta',
    ],
    customer_order: [
        'agent.meta',
        'positions.rows[0].assortment.meta',
    ],
    invoice_out: [
        'agent.meta',
    ],
    invoice_in: [
        'agent.meta',
    ],
    demand: [
        'agent.meta',
        'positions.rows[0].assortment.meta',
    ],
    move: [
        'positions.rows[0].assortment.meta',
    ],
    retail_demand: [
        'agent.meta',
        'positions.rows[0].assortment.meta',
    ],
    retail_sales_return: [
        'agent.meta',
        'positions.rows[0].assortment.meta',
        'positions.rows[1].assortment.meta',
    ],
    enter: [
        'positions.rows[0].assortment.meta',
    ],
    sales_return: [
        'agent.meta',
        'positions.rows[0].assortment.meta',
    ],
    loss: [
        'positions.rows[0].assortment.meta',
        'positions.rows[1].assortment.meta',
    ],
    purchase_order: [
        'agent.meta',
        'positions.rows[0].assortment.meta',
    ],
    purchase_return: [
        'agent.meta',
        'positions.rows[0].assortment.meta',
    ],
    supply: [
        'agent.meta',
        'positions.rows[0].assortment.meta',
    ],
    prepayment_return: [
        'agent.meta',
    ],
    emission_order: [
        'positions.rows[0].assortment.meta',
    ],
    contract: [
        'agent.meta',
    ],
    task: [
        'agent.meta',
    ],
    consignment: [
        'assortment.meta',
    ],
    cash_in: [
        'agent.meta',
        'operations[0].meta',
        'operations[0].payments',
        'operations[0].positions',
        'operations[1].meta',
        'operations[1].payments',
        'operations[1].positions',
        'operations[2].customerOrder',
        'operations[2].meta',
        'operations[2].positions',
        'operations[3].customerOrder',
        'operations[3].meta',
        'operations[3].payments',
        'operations[4].commissionPeriodEnd',
        'operations[4].commissionPeriodStart',
        'operations[4].meta',
        'operations[4].positions',
        'operations[5].meta',
        'operations[5].retailStore',
    ],
    cash_out: [
        'agent.meta',
        'operations[0].meta',
        'operations[0].payments',
        'operations[0].positions',
        'operations[1].meta',
        'operations[1].payments',
        'operations[1].positions',
        'operations[1].purchaseOrder',
        'operations[2].meta',
        'operations[2].payments',
        'operations[2].purchaseOrder',
        'operations[3].invoicesIn',
        'operations[3].meta',
        'operations[3].payments',
        'operations[3].positions',
        'operations[4].commissionPeriodEnd',
        'operations[4].commissionPeriodStart',
        'operations[4].meta',
        'operations[4].payments',
        'operations[4].positions',
    ],
    inventory: [
        'positions.rows[0].assortment.meta',
    ],
    finance_in_operation_commission_report_in: [
        'linkedSum',
    ],
    finance_in_operation_customer_order: [
        'linkedSum',
    ],
    finance_in_operation_demand: [
        'linkedSum',
    ],
    finance_in_operation_invoice_out: [
        'linkedSum',
    ],
    finance_in_operation_purchase_return: [
        'linkedSum',
    ],
    finance_in_operation_retail_shift: [
        'linkedSum',
    ],
    finance_out_operation_commission_report_out: [
        'linkedSum',
    ],
    finance_out_operation_invoice_in: [
        'linkedSum',
    ],
    finance_out_operation_purchase_order: [
        'linkedSum',
    ],
    finance_out_operation_sales_return: [
        'linkedSum',
    ],
    finance_out_operation_supply: [
        'linkedSum',
    ],
    payment_in: [
        'agent.meta',
        'operations[0].meta',
        'operations[0].payments',
        'operations[0].positions',
        'operations[1].meta',
        'operations[1].payments',
        'operations[1].positions',
        'operations[2].customerOrder',
        'operations[2].meta',
        'operations[2].positions',
        'operations[3].customerOrder',
        'operations[3].meta',
        'operations[3].payments',
        'operations[4].commissionPeriodEnd',
        'operations[4].commissionPeriodStart',
        'operations[4].meta',
        'operations[4].positions',
        'operations[5].meta',
        'operations[5].retailStore',
    ],
    payment_out: [
        'agent.meta',
        'operations[0].meta',
        'operations[0].payments',
        'operations[0].positions',
        'operations[1].meta',
        'operations[1].payments',
        'operations[1].positions',
        'operations[1].purchaseOrder',
        'operations[2].meta',
        'operations[2].payments',
        'operations[2].purchaseOrder',
        'operations[3].invoicesIn',
        'operations[3].meta',
        'operations[3].payments',
        'operations[3].positions',
        'operations[4].commissionPeriodEnd',
        'operations[4].commissionPeriodStart',
        'operations[4].meta',
        'operations[4].payments',
        'operations[4].positions',
    ],
    facture_in: [
        'agent.meta',
    ],
    facture_out: [
        'agent.meta',
        'consignee.meta',
    ],
    discount: [
        'active',
        'agentTags',
        'allAgents',
        'name',
    ],
    accumulation_discount: [
        'accountId',
        'active',
        'agentTags',
        'allAgents',
        'allProducts',
        'assortment',
        'name',
    ],
    personal_discount: [
        'accountId',
        'active',
        'agentTags',
        'allAgents',
        'allProducts',
        'assortment',
        'name',
    ],
    special_price_discount: [
        'accountId',
        'active',
        'agentTags',
        'allAgents',
        'allProducts',
        'assortment',
        'name',
    ],
    bonus_program: [
        'accountId',
        'active',
        'agentTags',
        'allAgents',
        'allProducts',
        'id',
        'meta',
        'name',
    ],
    bonus_transaction: [
        'agent.meta',
        'bonusProgram.meta',
    ],
    commission_report_in: [
        'agent.meta',
        'positions.rows[0].assortment.meta',
        'positions.rows[1].assortment.meta',
    ],
    commission_report_out: [
        'agent.meta',
    ],
    organization: [
        'bonusProgram.meta',
    ],

};
