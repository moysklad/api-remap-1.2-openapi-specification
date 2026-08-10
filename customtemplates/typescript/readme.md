Шаблоны можно найти тут: https://github.com/OpenAPITools/openapi-generator/tree/master/modules/openapi-generator/src/main/resources/typescript-fetch

Генератор: `typescript-fetch`. Шаблоны, которых нет в этой директории, берутся из стандартных шаблонов генератора.

Почему были добавлены кастомные шаблоны:

* modelGenericInterfaces.mustache - discriminator-поле родителя объявлено как required (`AttributeAbstract.type`),
  но в наследниках по allOf генератор выводит его как optional. TypeScript на это отвечает
  `TS2430: Interface 'AttributeBool' incorrectly extends interface 'AttributeAbstract'`.
  Свойство с `isDiscriminator` всегда выводится как required, поэтому наследники совместимы с родителем.

Отдельно от шаблонов, в `typescript-sdk-config.yaml` задан `modelNameMappings: Error: ModelError`.
Схема `Error` конфликтует с одноимённым встроенным типом TypeScript: тип генератор переименовывает
в `ModelError`, но имена импортов и хелперов (`instanceOfError`, `ErrorFromJSONTyped`) остаются старыми,
и oneOf-модели не компилируются. Явный маппинг делает имя одинаковым во всех местах.
