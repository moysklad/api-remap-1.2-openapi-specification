Шаблоны можно найти тут: https://github.com/OpenAPITools/openapi-generator/tree/master/modules/openapi-generator/src/main/resources/Java

Почему были добавлены кастомные шаблоны:

* ApiClient.mustache - кастомный формат дат, Jackson 3 (`JsonMapper`), добавлена поддержка batch-ответов с частичными ошибками: стандартный шаблон обрабатывает ответ как один успешный объект или одну ошибку, что не подходит для batch-операций API.
* api.mustache - правильные заголовки Accept: application/json;charset=utf-8
* RequestOptions.mustache, PageOptions.mustache, ListOptions.mustache, AssortmentListOptions.mustache, Filters.mustache, 
  Orders.mustache - удобные overload-методы для query-параметров без длинных позиционных сигнатур
* pojo.mustache - решение проблем с дискриминатором в полиморфных классах
* model_entity_static_builder.mustache появился для удобства создания объектов с заполненной meta. Причина - приходится вручную конструировать 
  и заполнять много полей сперва по созданию meta, затем подстановку этой meta в готовый объект
* pom.mustache - адаптация стандартного pom.xml для проекта, Jackson 3.
* apiException.mustache - добавлен десериализованный объект ошибок, если ApiException выброшен когда получили ошибку от api
* RFC3339DateFormat.mustache, RFC3339InstantDeserializer.mustache, RFC3339JavaTimeModule.mustache, BaseApi.mustache, build.gradle.mustache - Jackson 3 пакеты (`tools.jackson`) вместо встроенных шаблонов генератора 7.14.0. 