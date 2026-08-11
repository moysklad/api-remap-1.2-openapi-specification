Шаблоны можно найти тут: https://github.com/OpenAPITools/openapi-generator/tree/master/modules/openapi-generator/src/main/resources/typescript-fetch

Генератор: `typescript-fetch`. Шаблоны, которых нет в этой директории, берутся из стандартных шаблонов генератора.

Почему были добавлены кастомные шаблоны:

* modelGeneric.mustache - реализует проектные расширения `x-polymorphic-parent` и
  `x-polymorphic-discriminator`: объединяет сериализацию полей родительской цепочки,
  выбирает дочерние `FromJSON`/`ToJSON` по вложенному пути (`meta.type`), поддерживает
  mapping `undefined` и batch-error fallback.

* modelGenericInterfaces.mustache - discriminator-поле родителя объявлено как required (`AttributeAbstract.type`),
  но в наследниках по allOf генератор выводит его как optional. TypeScript на это отвечает
  `TS2430: Interface 'AttributeBool' incorrectly extends interface 'AttributeAbstract'`.
  Свойство с `isDiscriminator` всегда выводится как required, поэтому наследники совместимы с родителем.
  Для `x-polymorphic-parent` собственный интерфейс модели объединяется с типом родителя через intersection,
  чтобы сохранить обязательность унаследованных полей даже при конфликтующем allOf.

* package.mustache - стандартный шаблон подставляет заглушки (`author: OpenAPI-Generator`,
  `github.com/GIT_USER_ID/GIT_REPO_ID`) и не описывает состав публикуемого пакета. Кастомный шаблон задаёт
  описание и keywords, ссылки на npm-репозиторий и GitHub, `license`/`author`, `engines.node >= 22`,
  `main`/`module`/`types`/`exports`, `files` и `publishConfig`.
  Шаг сборки `npm run build` дополнительно кладёт `dist/esm/package.json` с `{"type":"module"}`:
  без этого маркера Node читает `dist/esm/*.js` как CommonJS и падает на `export`.

* README.mustache - стандартный README описывает сам генератор. Публикуемому пакету нужны установка,
  импорт (ESM и CommonJS), авторизация, базовый пример запроса и ссылки на npm/GitHub.

* LICENSE.mustache - MIT-лицензия пакета. Подключается через `files:` в `typescript-sdk-config.yaml`
  (стандартный генератор файл лицензии не создаёт).

* npmignore.mustache - стандартный шаблон исключает из пакета только `README.md`. Состав пакета задан
  полем `files` в `package.json`; свой `.npmignore` нужен, чтобы npm не использовал вместо него
  `.gitignore`, в котором исключён `dist`.

Отдельно от шаблонов, в `typescript-sdk-config.yaml` задан `modelNameMappings: Error: ModelError`.
Схема `Error` конфликтует с одноимённым встроенным типом TypeScript: тип генератор переименовывает
в `ModelError`, но имена импортов и хелперов (`instanceOfError`, `ErrorFromJSONTyped`) остаются старыми,
и oneOf-модели не компилируются. Явный маппинг делает имя одинаковым во всех местах.

Там же задан `importFileExtension: ".js"` — относительные импорты в сгенерированном коде получают
расширение `.js`. Без него сборка `dist/esm` непригодна для Node ESM: импорты без расширения
не резолвятся (`ERR_MODULE_NOT_FOUND`).

Версия пакета (`npmVersion`) в конфиге не задаётся: её подставляет `scripts/generate-typescript-sdk.sh`
из semver-версии репозитория спецификации.
