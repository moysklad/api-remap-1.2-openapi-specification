Шаблоны можно найти тут: https://github.com/OpenAPITools/openapi-generator/tree/master/modules/openapi-generator/src/main/resources/Java

Почему были добавлены кастомные шаблоны:

* ApiClient.mustache - кастомный формат дат, Jackson 3 (`JsonMapper`). Массивы `BatchResponseEntity` и
  `DeleteRowResult` при 404/412 разбираются с сохранением успехов и ошибок; проверяются структура массива
  и тип результата. Прочие ошибочные ответы дают `ApiException` с исходными статусом и телом.
  `invokeAPIBatch` использует ту же обработку, что и `invokeAPI`.
* api.mustache - правильные заголовки Accept: application/json;charset=utf-8
* api.mustache, ApiClient.mustache, LocationResponse.mustache - операции с `x-location-response` (печать, экспорт)
  отдают ссылку только в заголовке `Location` (202 или 303). Метод возвращает `LocationResponse` вместо `void`,
  редирект отключён, иначе HttpClient уходит по 303 и `Location` теряется.
* RequestOptions.mustache, PageOptions.mustache, ListOptions.mustache, AssortmentListOptions.mustache, Filters.mustache, 
  Orders.mustache - удобные overload-методы для query-параметров без длинных позиционных сигнатур
* pojo.mustache - решение проблем с дискриминатором в полиморфных классах; коллекции создаются
  только при присваивании или добавлении элемента.
* jackson_annotations.mustache - незаданные коллекции не сериализуются; явно заданные пустые списки
  сохраняются для очистки. Пустые readOnly-коллекции исключаются, в том числе внутри `JsonNullable`.
  `EmptyReadOnlyCollectionFilter.mustache` проверяет пустоту коллекции целиком, сохраняя все значения
  внутри непустых карт, включая `null`, пустые строки, списки и карты.
  Явный `null` у writable nullable-полей сохраняется.
* model_entity_static_builder.mustache появился для удобства создания объектов с заполненной meta. Причина - приходится вручную конструировать 
  и заполнять много полей сперва по созданию meta, затем подстановку этой meta в готовый объект.
  `entityTypeParams` (например, у `Account` и `StoreBalance`) передаются строками, ID — как UUID.
* pom.mustache - адаптация стандартного pom.xml для проекта, Jackson 3.
* apiException.mustache - добавлен десериализованный объект ошибок, если ApiException выброшен когда получили ошибку от api
* README.mustache - стандартный README описывает генератор, все эндпоинты и модели и использует координаты
  `openapi-java-client`. Публикуемому SDK нужны координаты Maven Central (включая `-fat`), авторизация и базовый пример запроса.
* RFC3339DateFormat.mustache, RFC3339InstantDeserializer.mustache, RFC3339JavaTimeModule.mustache, BaseApi.mustache, build.gradle.mustache - Jackson 3 пакеты (`tools.jackson`) вместо встроенных шаблонов генератора 7.14.0. 