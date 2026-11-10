Шаблоны можно найти тут: https://github.com/OpenAPITools/openapi-generator/blob/master/modules/openapi-generator/src/main/resources/php/api.mustache

Почему были добавлены кастомные шаблоны:
* ObjectSerializer и model_generic. Неправильно работает discriminator для php. 
  Есть открытый запрос в github https://github.com/OpenAPITools/openapi-generator/issues/11432. 
  Возможно в будущем это поправят и кастомные шаблоны можно будет удалить
* ObjectSerializer. В OpenApi нельзя задать дефолтный маппер когда придет неизвестный тип для маппинга. 
  Для сохранения ОС для старых версий сдк нужно добавить маппер на новые неизвестные типы. 
  Если в будущем в OpeanApi будет добавлен дефолтный тип маппера то данные правки можно убрать
* ObjectSerializer. Заменен формат даты
* api.mustache - явно заданные заголовки имеют приоритет над HeaderSelector без учёта регистра имени.
  Значения Accept `application/json;charset=utf-8` и Accept-Encoding `gzip` приходят из параметров спецификации.
* api.mustache - поддержка смешанных batch-ответов `BatchResponseEntity[]` и `DeleteRowResult[]`
  при HTTP 404/412 в синхронных и асинхронных методах. Глобальный объект ошибки и остальные ошибочные
  статусы остаются `ApiException`, тело и HTTP-статус сохраняются даже при некорректном контейнере `errors`.
* Configuration.mustache - стандартный шаблон генератора 7.14.0 с единственным изменением:
  boolean query-параметры по умолчанию передаются как `true`/`false` (`BOOLEAN_FORMAT_STRING`).
* model_generic.mustache, model_composed_types.mustache, ObjectSerializer.mustache - выбор моделей
  `oneOf`/`anyOf`, в том числе вложенных в `allOf`, по `meta.type` и типам из `x-entity-static-builder`.
  Поддержаны ассортимент, ассортимент скидок и вложенные платежи. В batch-удалении выбираются `DeleteInfo` / `Errors`,
  при наличии ошибок `Errors` имеет приоритет над `info`.
  Поля составной схемы, отсутствующие в выбранной модели (например, остатки Assortment у Product),
  сохраняются через ArrayAccess и при сериализации; удаление через `unset()` также учитывается. Неизвестные поля не добавляются.
* ObjectSerializer.mustache - `property_exists()` различает отсутствующее nullable-поле и явный `null`:
  отсутствующее поле не добавляется при сериализации, явный `null` сохраняется для очистки допполей и файла.
* api.mustache, LocationResponse.mustache - операции с `x-location-response` (печать, экспорт)
  отдают ссылку только в заголовке `Location` (202 или 303). Метод возвращает `LocationResponse` вместо `void`,
  `allow_redirects = false` в синхронном и асинхронном вызове, иначе Guzzle уходит по 303 и `Location` теряется.
* Кастомный шаблон model_constant больше не используется.
  Известные значения для open-string полей перенесены в отдельные enum-компоненты OpenAPI, чтобы SDK генерировал константы из спецификации, а не из PHP-only шаблона.
* README.mustache - стандартный README описывает генератор, все эндпоинты и модели. Публикуемому SDK нужны
  установка через Composer, авторизация и базовый пример запроса.
* model_entity_static_builder появился для удобства создания объектов с заполненной meta. Причина - приходится вручную конструировать 
  и заполнять много полей сперва по созданию meta, затем подстановку этой meta в готовый объект
