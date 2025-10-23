# # ProductFolder

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**meta** | [**\OpenAPI\Client\Model\Meta**](Meta.md) |  | [optional]
**id** | **string** | ID группы товаров | [optional] [readonly]
**account_id** | **string** | ID учетной записи | [optional] [readonly]
**name** | **string** | Наименование группы товаров | [optional]
**code** | **string** | Код группы товаров | [optional]
**external_code** | **string** | Внешний код группы товаров | [optional]
**archived** | **bool** | Добавлена ли группа товаров в архив | [optional]
**path_name** | **string** | Наименование родительской группы | [optional] [readonly]
**description** | **string** | Описание группы товаров | [optional]
**vat** | **int** | НДС % | [optional]
**vat_enabled** | **bool** | Включен ли НДС для группы | [optional]
**effective_vat** | **int** | Реальный НДС % | [optional] [readonly]
**effective_vat_enabled** | **bool** | Дополнительный признак для определения разграничения реального НДС | [optional] [readonly]
**use_parent_vat** | **bool** | Используется ли ставка НДС родительской группы | [optional]
**shared** | **bool** | Общий доступ | [optional]
**group** | [**\OpenAPI\Client\Model\Group**](Group.md) |  | [optional]
**owner** | [**\OpenAPI\Client\Model\Owner**](Owner.md) |  | [optional]
**updated** | **\DateTime** | Момент последнего обновления | [optional] [readonly]
**product_folder** | [**\OpenAPI\Client\Model\ProductFolder**](ProductFolder.md) |  | [optional]
**tax_system** | **string** | Код системы налогообложения | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
