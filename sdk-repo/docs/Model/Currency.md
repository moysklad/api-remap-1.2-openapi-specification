# # Currency

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**meta** | [**\OpenAPI\Client\Model\Meta**](Meta.md) |  | [optional]
**id** | **string** | ID валюты | [optional] [readonly]
**system** | **bool** | Системная валюта | [optional] [readonly]
**name** | **string** | Краткое наименование валюты | [optional]
**full_name** | **string** | Полное наименование валюты | [optional]
**code** | **string** | Цифровой код валюты | [optional]
**iso_code** | **string** | Буквенный код валюты | [optional]
**multiplicity** | **int** | Кратность курса валюты | [optional]
**rate** | **float** | Курс валюты | [optional]
**margin** | **float** | Наценка при автоматическом обновлении курса | [optional]
**indirect** | **bool** | Признак обратного курса валюты | [optional]
**rate_update_type** | **string** | Способ обновления курса валюты | [optional]
**archived** | **bool** | Добавлена ли валюта в архив | [optional]
**default** | **bool** | Валюта учета по умолчанию | [optional]
**major_unit** | [**\OpenAPI\Client\Model\CurrencyMajorUnit**](CurrencyMajorUnit.md) |  | [optional]
**minor_unit** | [**\OpenAPI\Client\Model\CurrencyMinorUnit**](CurrencyMinorUnit.md) |  | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
