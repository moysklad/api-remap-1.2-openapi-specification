Шаблоны можно найти тут: https://github.com/OpenAPITools/openapi-generator/blob/master/modules/openapi-generator/src/main/resources/php/api.mustache

Почему были добавлены кастомные шаблоны:
* ObjectSerializer и model_generic. Неправильно работает discriminator для php. 
  Есть открытый запрос в github https://github.com/OpenAPITools/openapi-generator/issues/11432. 
  Возможно в будущем это поправят и кастомные шаблоны можно будет удалить
* ObjectSerializer. В OpenApi нельзя задать дефолтный маппер когда придет неизвестный тип для маппинга. 
  Для сохранения ОС для старых версий сдк нужно добавить маппер на новые неизвестные типы. 
  Если в будущем в OpeanApi будет добавлен дефолтный тип маппера то данные правки можно убрать
* ObjectSerializer. Заменен формат даты
* Кастомный шаблон model_constant больше не используется.
  Известные значения для open-string полей перенесены в отдельные enum-компоненты OpenAPI, чтобы SDK генерировал константы из спецификации, а не из PHP-only шаблона.
