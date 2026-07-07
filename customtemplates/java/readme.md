Шаблоны можно найти тут: https://github.com/OpenAPITools/openapi-generator/tree/master/modules/openapi-generator/src/main/resources/Java

Почему были добавлены кастомные шаблоны:

* ApiClient.mustache - кастомный формат дат
* api.mustache - правильные заголовки Accept: application/json;charset=utf-8
* RequestOptions.mustache, PageOptions.mustache, ListOptions.mustache, AssortmentListOptions.mustache, Filters.mustache, 
  Orders.mustache - удобные overload-методы для query-параметров без длинных позиционных сигнатур
* pojo.mustache - решение проблем с дискриминатором в полиморфных классах
* model_entity_static_builder.mustache появился для удобства создания объектов с заполненной meta. Причина - приходится вручную конструировать 
  и заполнять много полей сперва по созданию meta, затем подстановку этой meta в готовый объект
* pom.mustache - адаптация стандартного pom.xml для проекта.