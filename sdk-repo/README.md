# OpenAPIClient-php

API для манипуляции с сущностями и создания отчетов в онлайн-сервисе МойСклад.

## Аутентификация

МойСклад поддерживает аутентификацию по протоколу Basic Auth и с использованием токена доступа:
- Basic Auth: заголовок `Authorization` со значением пары `логин:пароль`, закодированным в Base64
- Bearer Token: заголовок `Authorization` со значением `Bearer <Access-Token>`

## Ограничения

- Не более 45 запросов за 3 секундный период от аккаунта
- Не более 5 параллельных запросов от одного пользователя  
- Не более 20 параллельных запросов от аккаунта
- Не более 20 Мб данных в одном запросе
- Максимум 1000 элементов в массиве
- Обязательное использование сжатия gzip



## Installation & Usage

### Requirements

PHP 8.1 and later.

### Composer

To install the bindings via [Composer](https://getcomposer.org/), add the following to `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/GIT_USER_ID/GIT_REPO_ID.git"
    }
  ],
  "require": {
    "GIT_USER_ID/GIT_REPO_ID": "*@dev"
  }
}
```

Then run `composer install`

### Manual Installation

Download the files and include `autoload.php`:

```php
<?php
require_once('/path/to/OpenAPIClient-php/vendor/autoload.php');
```

## Getting Started

Please follow the [installation procedure](#installation--usage) and then run the following:

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');



// Configure HTTP basic authorization: basicAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()
              ->setUsername('YOUR_USERNAME')
              ->setPassword('YOUR_PASSWORD');

// Configure Bearer authorization: bearerAuth
$config = OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken('YOUR_ACCESS_TOKEN');


$apiInstance = new OpenAPI\Client\Api\ProductsApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$limit = 1000; // int | Максимальное количество элементов в выданном списке (максимум 1000)
$offset = 0; // int | Отступ в выданном списке
$search = name=123; // string | Контекстный поиск по строковым полям сущностей
$filter = archived=false; // string | Фильтрация выборки
$expand = agent,organization; // string | Замена ссылок объектами с помощью expand
$order = name; // string | Сортировка
$accept = 'application/json;charset=utf-8'; // string
$accept_encoding = 'gzip'; // string

try {
    $result = $apiInstance->entityProductGet($limit, $offset, $search, $filter, $expand, $order, $accept, $accept_encoding);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling ProductsApi->entityProductGet: ', $e->getMessage(), PHP_EOL;
}

```

## API Endpoints

All URIs are relative to *https://api.moysklad.ru/api/remap/1.2*

Class | Method | HTTP request | Description
------------ | ------------- | ------------- | -------------
*ProductsApi* | [**entityProductGet**](docs/Api/ProductsApi.md#entityproductget) | **GET** /entity/product | Получить список товаров
*ProductsApi* | [**entityProductIdDelete**](docs/Api/ProductsApi.md#entityproductiddelete) | **DELETE** /entity/product/{id} | Удалить товар
*ProductsApi* | [**entityProductIdGet**](docs/Api/ProductsApi.md#entityproductidget) | **GET** /entity/product/{id} | Получить товар по ID
*ProductsApi* | [**entityProductIdPut**](docs/Api/ProductsApi.md#entityproductidput) | **PUT** /entity/product/{id} | Обновить товар
*ProductsApi* | [**entityProductMetadataGet**](docs/Api/ProductsApi.md#entityproductmetadataget) | **GET** /entity/product/metadata | Получить метаданные товаров
*ProductsApi* | [**entityProductPost**](docs/Api/ProductsApi.md#entityproductpost) | **POST** /entity/product | Создать товар

## Models

- [Account](docs/Model/Account.md)
- [Address](docs/Model/Address.md)
- [Application](docs/Model/Application.md)
- [AttributeAbstract](docs/Model/AttributeAbstract.md)
- [AttributeBool](docs/Model/AttributeBool.md)
- [AttributeCustomEntity](docs/Model/AttributeCustomEntity.md)
- [AttributeDateTime](docs/Model/AttributeDateTime.md)
- [AttributeDouble](docs/Model/AttributeDouble.md)
- [AttributeFile](docs/Model/AttributeFile.md)
- [AttributeLink](docs/Model/AttributeLink.md)
- [AttributeLong](docs/Model/AttributeLong.md)
- [AttributeMetaInfo](docs/Model/AttributeMetaInfo.md)
- [AttributeMetaInfoList](docs/Model/AttributeMetaInfoList.md)
- [AttributeObject](docs/Model/AttributeObject.md)
- [AttributeString](docs/Model/AttributeString.md)
- [AttributeText](docs/Model/AttributeText.md)
- [Barcode](docs/Model/Barcode.md)
- [BuyPrice](docs/Model/BuyPrice.md)
- [Cashier](docs/Model/Cashier.md)
- [ChequePrinter](docs/Model/ChequePrinter.md)
- [ContactPerson](docs/Model/ContactPerson.md)
- [Context](docs/Model/Context.md)
- [Counterparty](docs/Model/Counterparty.md)
- [CounterpartyAccounts](docs/Model/CounterpartyAccounts.md)
- [CounterpartyContactpersons](docs/Model/CounterpartyContactpersons.md)
- [CounterpartyNotes](docs/Model/CounterpartyNotes.md)
- [Country](docs/Model/Country.md)
- [Currency](docs/Model/Currency.md)
- [CurrencyMajorUnit](docs/Model/CurrencyMajorUnit.md)
- [CurrencyMinorUnit](docs/Model/CurrencyMinorUnit.md)
- [Driver](docs/Model/Driver.md)
- [Employee](docs/Model/Employee.md)
- [EmployeeSalary](docs/Model/EmployeeSalary.md)
- [Environment](docs/Model/Environment.md)
- [Error](docs/Model/Error.md)
- [ErrorErrorsInner](docs/Model/ErrorErrorsInner.md)
- [File](docs/Model/File.md)
- [FileList](docs/Model/FileList.md)
- [FiscalError](docs/Model/FiscalError.md)
- [FiscalMemory](docs/Model/FiscalMemory.md)
- [FiscalStatusMemory](docs/Model/FiscalStatusMemory.md)
- [FiscalType](docs/Model/FiscalType.md)
- [Group](docs/Model/Group.md)
- [Image](docs/Model/Image.md)
- [ImageList](docs/Model/ImageList.md)
- [LastOperationNames](docs/Model/LastOperationNames.md)
- [MarkingSellingMode](docs/Model/MarkingSellingMode.md)
- [MarksCheckMode](docs/Model/MarksCheckMode.md)
- [Meta](docs/Model/Meta.md)
- [MetaList](docs/Model/MetaList.md)
- [Metadata](docs/Model/Metadata.md)
- [MinPrice](docs/Model/MinPrice.md)
- [MinimumStockAbstract](docs/Model/MinimumStockAbstract.md)
- [MinimumStockAllWarehouseSame](docs/Model/MinimumStockAllWarehouseSame.md)
- [MinimumStockAllWarehouseSum](docs/Model/MinimumStockAllWarehouseSum.md)
- [MinimumStockWarehouseVaried](docs/Model/MinimumStockWarehouseVaried.md)
- [MinionToMasterType](docs/Model/MinionToMasterType.md)
- [Note](docs/Model/Note.md)
- [Owner](docs/Model/Owner.md)
- [Pack](docs/Model/Pack.md)
- [PaymentTerminal](docs/Model/PaymentTerminal.md)
- [PriceType](docs/Model/PriceType.md)
- [PriorityOfdSend](docs/Model/PriorityOfdSend.md)
- [Product](docs/Model/Product.md)
- [ProductAlcoholic](docs/Model/ProductAlcoholic.md)
- [ProductFolder](docs/Model/ProductFolder.md)
- [ProductList](docs/Model/ProductList.md)
- [Region](docs/Model/Region.md)
- [RetailStore](docs/Model/RetailStore.md)
- [RetailStoreAcquire](docs/Model/RetailStoreAcquire.md)
- [RetailStoreCashiers](docs/Model/RetailStoreCashiers.md)
- [RetailStoreCreateOrderWithState](docs/Model/RetailStoreCreateOrderWithState.md)
- [RetailStoreCustomerOrderStatesInner](docs/Model/RetailStoreCustomerOrderStatesInner.md)
- [RetailStoreOrderToState](docs/Model/RetailStoreOrderToState.md)
- [RetailStoreOrganization](docs/Model/RetailStoreOrganization.md)
- [RetailStoreProductFolders](docs/Model/RetailStoreProductFolders.md)
- [RetailStoreQrAcquire](docs/Model/RetailStoreQrAcquire.md)
- [RetailStoreReceiptTemplate](docs/Model/RetailStoreReceiptTemplate.md)
- [SalePrice](docs/Model/SalePrice.md)
- [Software](docs/Model/Software.md)
- [State](docs/Model/State.md)
- [Status](docs/Model/Status.md)
- [Store](docs/Model/Store.md)
- [StoreBalance](docs/Model/StoreBalance.md)
- [StoreBalanceList](docs/Model/StoreBalanceList.md)
- [StoreSlots](docs/Model/StoreSlots.md)
- [StoreZones](docs/Model/StoreZones.md)
- [Sync](docs/Model/Sync.md)
- [TaxSystem](docs/Model/TaxSystem.md)
- [TobaccoMrcControlType](docs/Model/TobaccoMrcControlType.md)
- [Uom](docs/Model/Uom.md)

## Authorization

Authentication schemes defined for the API:
### basicAuth

- **Type**: HTTP basic authentication

### bearerAuth

- **Type**: Bearer authentication

## Tests

To run the tests, use:

```bash
composer install
vendor/bin/phpunit
```

## Author



## About this package

This PHP package is automatically generated by the [OpenAPI Generator](https://openapi-generator.tech) project:

- API version: `1.0.0`
    - Generator version: `7.14.0`
- Build package: `org.openapitools.codegen.languages.PhpClientCodegen`
