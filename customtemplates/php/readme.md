Шаблоны можно найти тут: https://github.com/OpenAPITools/openapi-generator/blob/master/modules/openapi-generator/src/main/resources/php/api.mustache

Почему были добавлены кастомные шаблоны:
* ObjectSerializer и model_generic. Неправильно работает discriminator для php. 
  Есть открытый запрос в github https://github.com/OpenAPITools/openapi-generator/issues/11432. 
  Возможно в будущем это поправят и кастомные шаблоны можно будет удалить
* ObjectSerializer. В OpenApi нельзя задать дефолтный маппер когда придет неизвестный тип для маппинга. 
  Для сохранения ОС для старых версий сдк нужно добавить маппер на новые неизвестные типы. 
  Если в будущем в OpeanApi будет добавлен дефолтный тип маппера то данные правки можно убрать
* ObjectSerializer. Заменен формат даты
* api.mustache - явные заголовки приоритетнее HeaderSelector независимо от регистра; значения Accept
  и Accept-Encoding по умолчанию берутся из спецификации.
* api.mustache - смешанные batch-ответы `BatchResponseEntity[]` / `DeleteRowResult[]` при 404/412
  разбираются синхронно и асинхронно. Остальные ошибки остаются `ApiException` с исходными статусом и телом.
* Configuration.mustache - boolean query-параметры передаются как `true`/`false` (`BOOLEAN_FORMAT_STRING`).
* model_generic.mustache, model_composed_types.mustache, ObjectSerializer.mustache - выбор `oneOf`/`anyOf`
  по `meta.type`, включая вложенные `allOf`; в batch-удалении `Errors` приоритетнее `DeleteInfo`.
  Поля составной схемы сохраняются через ArrayAccess и при сериализации, с учётом `unset()`.
* ObjectSerializer.mustache - `property_exists()` различает отсутствие поля и явный `null`:
  лишние null-поля не добавляются, очистка допполей и файла сохраняется.
* api.mustache, LocationResponse.mustache - операции с `x-location-response` (печать, экспорт)
  отдают ссылку только в заголовке `Location` (202 или 303). Метод возвращает `LocationResponse` вместо `void`,
  `allow_redirects = false` в синхронном и асинхронном вызове, иначе Guzzle уходит по 303 и `Location` теряется.
* Кастомный шаблон model_constant больше не используется.
  Известные значения для open-string полей перенесены в отдельные enum-компоненты OpenAPI, чтобы SDK генерировал константы из спецификации, а не из PHP-only шаблона.
* README.mustache - стандартный README описывает генератор, все эндпоинты и модели. Публикуемому SDK нужны
  установка через Composer, авторизация и базовый пример запроса.
* model_entity_static_builder появился для удобства создания объектов с заполненной meta. Причина - приходится вручную конструировать 
  и заполнять много полей сперва по созданию meta, затем подстановку этой meta в готовый объект
