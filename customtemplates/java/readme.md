Шаблоны можно найти тут: https://github.com/OpenAPITools/openapi-generator/tree/master/modules/openapi-generator/src/main/resources/Java

Почему были добавлены кастомные шаблоны:

* ApiClient.mustache - формат дат, Jackson 3 (`JsonMapper`); общий разбор batch в `invokeAPI`/`invokeAPIBatch`.
  При 404/412 проверяет тип и структуру массивов `BatchResponseEntity` / `DeleteRowResult`, сохраняя успехи и ошибки.
  Остальные ошибочные ответы дают `ApiException` с исходными статусом и телом.
* api.mustache - правильные заголовки Accept: application/json;charset=utf-8
* api.mustache, model_range_validation.mustache - `minimum`/`maximum` проверяются в query-параметрах (`limit`, `offset`)
  и сеттерах моделей (`vat`); при нарушении `IllegalArgumentException`, в том числе при десериализации ответа.
* api.mustache, ApiClient.mustache, LocationResponse.mustache - операции с `x-location-response` (печать, экспорт)
  отдают ссылку только в заголовке `Location` (202 или 303). Метод возвращает `LocationResponse` вместо `void`,
  редирект отключён, иначе HttpClient уходит по 303 и `Location` теряется.
* RequestOptions.mustache, PageOptions.mustache, ListOptions.mustache, AssortmentListOptions.mustache, Filters.mustache, 
  Orders.mustache - удобные overload-методы для query-параметров без длинных позиционных сигнатур
* pojo.mustache - полиморфные дискриминаторы; коллекции инициализируются только при присваивании или добавлении.
* jackson_annotations.mustache, EmptyReadOnlyCollectionFilter.mustache - исключают незаданные коллекции
  и пустые readOnly, включая `JsonNullable`. Явные пустые списки и `null` у writable nullable-полей сохраняются для очистки.
  Содержимое непустых карт сохраняется полностью.
* model_entity_static_builder.mustache - создание объектов с `meta`: `entityTypeParams` — строки, ID — UUID.
* pom.mustache - адаптация стандартного pom.xml для проекта, Jackson 3.
* apiException.mustache - добавлен десериализованный объект ошибок, если ApiException выброшен когда получили ошибку от api
* README.mustache - стандартный README описывает генератор, все эндпоинты и модели и использует координаты
  `openapi-java-client`. Публикуемому SDK нужны координаты Maven Central (включая `-fat`), авторизация и базовый пример запроса.
* RFC3339DateFormat.mustache, RFC3339InstantDeserializer.mustache, RFC3339JavaTimeModule.mustache, BaseApi.mustache, build.gradle.mustache - Jackson 3 пакеты (`tools.jackson`) вместо встроенных шаблонов генератора 7.14.0. 