# # Employee

## Properties

Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**meta** | [**\OpenAPI\Client\Model\Meta**](Meta.md) |  | [optional]
**id** | **string** | ID сотрудника | [optional] [readonly]
**account_id** | **string** | ID учетной записи | [optional] [readonly]
**name** | **string** | Наименование сотрудника | [optional] [readonly]
**external_code** | **string** | Внешний код сотрудника | [optional]
**archived** | **bool** | Добавлен ли сотрудник в архив | [optional]
**created** | **\DateTime** | Момент создания | [optional] [readonly]
**updated** | **\DateTime** | Момент последнего обновления | [optional] [readonly]
**description** | **string** | Комментарий к сотруднику | [optional]
**email** | **string** | Адрес электронной почты | [optional]
**phone** | **string** | Номер телефона | [optional]
**first_name** | **string** | Имя | [optional]
**middle_name** | **string** | Отчество | [optional]
**last_name** | **string** | Фамилия | [optional]
**full_name** | **string** | Полное имя сотрудника | [optional] [readonly]
**short_fio** | **string** | Краткое ФИО сотрудника | [optional] [readonly]
**shared** | **bool** | Общий доступ | [optional]
**group** | [**\OpenAPI\Client\Model\Group**](Group.md) |  | [optional]
**owner** | [**\OpenAPI\Client\Model\Owner**](Owner.md) |  | [optional]
**inn** | **string** | ИНН сотрудника (в формате ИНН физического лица) | [optional]
**position** | **string** | Должность сотрудника | [optional]
**uid** | **string** | Логин сотрудника | [optional] [readonly]
**salary** | [**\OpenAPI\Client\Model\EmployeeSalary**](EmployeeSalary.md) |  | [optional]
**cashiers** | [**\OpenAPI\Client\Model\Cashier[]**](Cashier.md) | Массив кассиров | [optional] [readonly]
**image** | [**\OpenAPI\Client\Model\Image**](Image.md) |  | [optional]
**attributes** | [**\OpenAPI\Client\Model\AttributeAbstract[]**](AttributeAbstract.md) | Дополнительные поля | [optional]

[[Back to Model list]](../../README.md#models) [[Back to API list]](../../README.md#endpoints) [[Back to README]](../../README.md)
