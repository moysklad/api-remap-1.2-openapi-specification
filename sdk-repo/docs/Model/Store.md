# # Store

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**meta** | [**\OpenAPI\Client\Model\Meta**](Meta.md) |  | [optional]
**id** | **string** | ID Склада | [optional] [readonly]
**account_id** | **string** | ID учетной записи | [optional] [readonly]
**owner** | [**\OpenAPI\Client\Model\Owner**](Owner.md) |  | [optional]
**shared** | **bool** | Общий доступ | [optional]
**group** | [**\OpenAPI\Client\Model\Group**](Group.md) |  | [optional]
**updated** | **\DateTime** | Момент последнего обновления | [optional] [readonly]
**name** | **string** | Наименование Склада | [optional]
**description** | **string** | Комментарий к Складу | [optional]
**code** | **string** | Код Склада | [optional]
**external_code** | **string** | Внешний код Склада | [optional]
**archived** | **bool** | Добавлен ли в архив | [optional]
**address** | **string** | Адрес склада | [optional]
**address_full** | [**\OpenAPI\Client\Model\Address**](Address.md) |  | [optional]
**path_name** | **string** | Группа Склада | [optional] [readonly]
**parent** | [**\OpenAPI\Client\Model\Store**](Store.md) |  | [optional]
**attributes** | [**\OpenAPI\Client\Model\AttributeAbstract[]**](AttributeAbstract.md) | Дополнительные поля | [optional]
**zones** | [**\OpenAPI\Client\Model\StoreZones**](StoreZones.md) |  | [optional]
**slots** | [**\OpenAPI\Client\Model\StoreSlots**](StoreSlots.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
