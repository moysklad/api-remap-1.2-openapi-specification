# МойСклад OpenAPI SDK Builder

Проект для генерации SDK на различных языках программирования из OpenAPI спецификации МойСклад JSON API 1.2.

## Содержание

- [Быстрый старт](#быстрый-старт)
- [Локальный запуск (Docker)](#локальный-запуск-docker)
- [Структура проекта](#структура-проекта)
- [Добавление нового языка SDK](#добавление-нового-языка-sdk)
- [Тестирование](#тестирование)

---

## Быстрый старт

```bash
# Установите соответствующую версию nvm
nvm use v24.0.1

# Установка зависимостей
npm install

# Валидация спецификации
npm run validate

# Генерация PHP SDK
npm run generate-php

# Генерация TypeScript SDK (результат — собираемый npm-пакет в clients/typescript)
npm run generate-typescript

# Сборка npm-пакета TypeScript SDK
make build-typescript

# Golden тесты TypeScript SDK (общие fixtures из tests/fixtures)
make test-golden-typescript

# Сборка bundled спецификации
npm run bundle
npm run bundle-json
```

---

## Локальный запуск (Docker)

Docker-среда поддерживает несколько языков SDK (php, java, typescript, python, javascript). Генерация и golden-тесты реализованы для PHP, Java и TypeScript; для остальных языков нужно добавить скрипты в `package.json` и тесты в `tests/<language>/`. Для TypeScript CI-джобов пока нет — генерация и тесты запускаются локально.

Контейнеры `sdk` и `java-sdk` запускаются под UID/GID пользователя хоста (`${UID:-1000}:${GID:-1000}`), поэтому сгенерированные файлы в `clients/` остаются доступными текущему пользователю. Если ранее SDK уже генерировались контейнером от root, один раз исправьте владельца:

```bash
sudo chown -R "$(id -u):$(id -g)" clients
```

```bash
# Сборка образа
docker compose build

# Проверка спецификации
docker compose run --rm sdk make lint

# Сборка bundled спецификации
docker compose run --rm sdk make bundle

# Сборка облегчённой спецификации для быстрых smoke-тестов
docker compose run --rm sdk make light-bundle

# Генерация SDK (по умолчанию PHP; можно несколько: LANGUAGES=php,python)
docker compose run --rm sdk make generate
docker compose run --rm sdk make generate-php
docker compose run --rm sdk make generate-java
docker compose run --rm sdk make generate-typescript

# Сборка TypeScript SDK (после generate-typescript) и состав npm-пакета
docker compose run --rm sdk make build-typescript
docker compose run --rm sdk make pack-typescript

# Golden тесты (по умолчанию php; падение любого языка из LANGUAGES роняет цель)
docker compose run --rm sdk make test-golden
docker compose run --rm sdk make test-golden LANGUAGES=php,typescript
docker compose run --rm sdk make test-golden-php
docker compose run --rm sdk make test-golden-typescript
docker compose run --rm java-sdk make test-golden-java

# Сборка Java SDK (основной runtime-артефакт — self-contained shaded JAR с relocation, после generate-java)
docker compose run --rm java-sdk bash -lc "cd clients/java && mvn clean package"

# Smoke тесты (openapi-mock + тесты по языкам)
# ВАЖНО: после make bundle/light-bundle перезапустите mock — он кэширует спецификацию при старте
docker compose restart mock
docker compose run --rm sdk make test-smoke

# Контрактные тесты Schemathesis (один для всех языков)
docker compose run --rm -e SCHEMATHESIS_HOST=host -e SCHEMATHESIS_LOGIN=login -e SCHEMATHESIS_PASSWORD=pass sdk make schemathesis

# Полный прогон (lint, bundle, generate-php, test-golden, test-smoke)
docker compose run --rm sdk make all
```

Smoke-тесты выполняются Java-набором (`tests/java/assertions/.../smoke/ApiEndpointsTest.java`) через:
`docker compose run --rm sdk make test-smoke`.

**Без сборки образа** (если есть образ из CI):

```bash
docker run --rm -v "$(pwd):/workspace" -w /workspace \
  docker.infra.lognex/docker-openapitools:1.2-release make all
```

**Локальный Docker и Nexus:** если в `package-lock.json` указан корпоративный registry (nexus.infra.lognex), при запуске в Docker задаётся `USE_PUBLIC_NPM_REGISTRY=true`, и URL временно подменяется на registry.npmjs.org, чтобы не было ошибки SSL (UNABLE_TO_VERIFY_LEAF_SIGNATURE). Исходный `package-lock.json` после установки восстанавливается. За это отвечают `scripts/npm-ci-public-registry.sh` (корневой проект) и `scripts/npm-install-deps.sh` (подпроекты `clients/typescript` и `tests/typescript`; там же пропускается повторная установка, если `node_modules` уже соответствует `package.json` и lock-файлу — принудительно `NPM_CI_FORCE=1`).

**Schemathesis** (контрактные тесты против живого API):

```bash
docker compose run --rm \
  -e SCHEMATHESIS_HOST=https://api.example.com \
  -e SCHEMATHESIS_LOGIN=user \
  -e SCHEMATHESIS_PASSWORD=pass \
  sdk make schemathesis
```

Список целей: `docker compose run --rm sdk make help`.

`make lint` через Redocly запрещает `example` внутри `Schema`. Для фазы Schemathesis `examples` оставляйте примеры только в `src/paths/**` в request-секциях (`requestBody.content.<media-type>.example`); component schema, parameter schema и header schema examples должны отсутствовать.

---

## TypeScript SDK

Генерация запускается через `make generate-typescript` (или `npm run generate-typescript`) и выполняет `scripts/generate-typescript-sdk.sh`. Скрипт пересоздаёт `clients/typescript` с нуля, поэтому после каждой генерации в пакете нужно заново выполнить `npm install`.

**Версия пакета** не захардкожена в шаблонах: скрипт берёт её из версии репозитория спецификации в порядке `SDK_VERSION` → ближайший git-тег → `version` из корневого `package.json` (его же выставляет `version:auto`), и прерывает генерацию, если значение не является semver `MAJOR.MINOR.PATCH[-prerelease]`. Ручная сборка prerelease-версии:

```bash
SDK_VERSION=0.18.0-rc.1 npm run generate-typescript
```

**Метаданные пакета** задаются кастомными шаблонами в `customtemplates/typescript/` и конфигом `typescript-sdk-config.yaml`: имя `@moysklad/remap-1.2-sdk`, лицензия MIT (файл `LICENSE`), author `Lognex Dev Team`, `engines.node >= 22`, `main`/`module`/`types`/`exports`, `files`, ссылки на npm и GitHub-репозиторий `remap-1.2-typescript-sdk`. README пакета содержит установку, импорт, авторизацию и базовый пример запроса. Подробности и причины каждого шаблона — в `customtemplates/typescript/readme.md`.

**Проверка собираемого пакета:**

```bash
make build-typescript   # scripts/build-typescript-sdk.sh: npm install + dist/ (CommonJS) и dist/esm/ (ESM) с *.d.ts
make pack-typescript    # собирает пакет и печатает состав будущего npm-архива (npm pack --dry-run)

cd clients/typescript
npm pack                # .tgz для проверки установки в чистом проекте
tar -tzf moysklad-remap-1.2-sdk-*.tgz
```

В пакет попадают только `dist/`, `README.md`, `LICENSE` и `package.json`: исходники, tsconfig и служебные файлы генератора исключены.

**Детерминированность.** Повторная генерация из того же коммита даёт побайтово одинаковый вывод. Служебные файлы генератора (`.openapi-generator/FILES`, `.openapi-generator/VERSION`, `.openapi-generator-ignore`) удаляются из вывода: они не относятся к SDK и меняются при обновлении генератора.

Версия генератора зафиксирована в `openapitools.json` (`7.14.0`), поэтому локальная генерация и генерация в CI дают одинаковый результат. Проверено побайтово (`diff -r`) в трёх средах: хост, локальный образ `sdk` из `Dockerfile` и CI-образ `docker-openapitools-common:1.4-release`. В CI checkout выполняется без тегов, поэтому версия пакета берётся из `version` в корневом `package.json` — то же значение, что и у ближайшего тега локально (их синхронно обновляет `version:auto`).

### Golden тесты TypeScript SDK

Тесты живут в `tests/typescript` и работают с собранным пакетом (`clients/typescript/dist/esm`), то есть проверяют ровно то, что публикуется в npm.

```bash
make test-golden-typescript                    # scripts/local-test-golden-typescript.sh
docker compose run --rm sdk make test-golden-typescript
```

Скрипт сам собирает пакет, если `dist/` ещё нет, ставит зависимости тестов (`npm ci`) и прогоняет их. В отличие от `scripts/local-test-golden.sh` пропусков нет: отсутствие сгенерированного SDK, файлов тестов, fixtures, итогов прогона (`# pass` / `# fail` в TAP-выводе) или наличие пропущенных тестов — ошибка. Полный вывод прогона сохраняется в `tests/typescript/build/golden-tests.log`.

По каждой fixture из `tests/fixtures` (те же файлы, что у PHP и Java golden-тестов) выполняется roundtrip `fixture → <Model>FromJSON → <Model>ToJSON` и проверяется, что значения не искажены, лишних ключей нет, а массивы сохранили длину. Соответствие fixture ↔ модель задано в `FIXTURE_MODEL_MAP` (`tests/typescript/golden/serialization.test.ts`), поэтому новая fixture без записи в маппинге роняет тест.

Генератор `typescript-fetch` не сериализует `readOnly`-поля и объявляет их в сигнатуре `<Model>ToJSONTyped(value?: Omit<Model, 'id'|...>)`. Тест читает этот список из декларации собранного пакета, а не из захардкоженного перечня полей: пропуск поля допускается только если оно объявлено `readOnly` (на верхнем уровне — в самой модели, во вложенных объектах — хотя бы в одной модели SDK, так как модель вложенного поля в рантайме неизвестна).

**Известные пробелы.** Шаблоны `customtemplates/typescript/` пока не реализуют расширения спецификации `x-polymorphic-parent` и `x-polymorphic-discriminator` (в PHP и Java это делают кастомные шаблоны). Из-за этого модель не наследует поля родителя и не выбирается по `meta.type`: например, `AgentToJSON` возвращает пустой объект, `FinanceInOperationDemand` теряет `linkedSum`, а `Discount` — `name`/`active`/`allAgents`. Все такие потери перечислены по путям полей в `tests/typescript/golden/knownSerializationGaps.ts` и сравниваются точно: новая потеря роняет тест, а исчезнувшая требует удалить запись из реестра. Искажение значений не допускается ни для одной fixture.

---

## Структура проекта

```
api-sdk-builder/
├── .gitlab-ci.yml                    # Главный CI файл
├── gitlab/
│   ├── .gitlab-ci-sdk-validate.yml   # Валидация SDK (lint, tests)
│   ├── .gitlab-ci-github-mirror.yml  # Зеркалирование в GitHub
│   ├── .gitlab-ci-prepare-sdk-php.yml# Подготовка внутреннего репозитория PHP SDK (ветки и релиз мастер‑ветки)
│   ├── .gitlab-ci-prepare-sdk-java.yml# Подготовка внутреннего репозитория Java SDK (ветки и релиз мастер‑ветки)
│   ├── .gitlab-ci-deploy-sdk-java.yml# Публикация Java SDK в Artifactory/Maven
│   ├── .gitlab-ci-java-sdk.yml       # Старый Java SDK (обратная совместимость)
│   ├── .gitlab-ci-spec-gen.yml       # Старый PHP через OpenAPI (обратная совместимость)
│   ├── version.gitlab-ci.yml         # Версионирование спецификации
│   └── sdk/
│       ├── validate.yml          # Job для lint спецификации и проверки кастомных расширений
│       ├── generate-sdk.yml          # Job'ы для bundle-openapi, bundle-smoke-openapi и генерации SDK
│       ├── sdk-tests-golden.yml      # Golden тесты
│       ├── sdk-tests-smoke.yml       # Smoke тесты с openapi-mock
│       └── sdk-contract.yml          # Schemathesis контрактные тесты
├── src/
│   └── openapi.yaml                  # Главный файл OpenAPI спецификации
├── customtemplates/
|   ├── java/                         # Кастомные шаблоны для Java SDK
│   ├── php/                          # Кастомные шаблоны для PHP SDK
│   └── typescript/                   # Кастомные шаблоны для TypeScript SDK (package.json, README, LICENSE, .npmignore)
├── typescript-sdk-config.yaml        # Конфигурация генератора TypeScript SDK
├── openapitools.json                 # Зафиксированная версия OpenAPI Generator (одна для локали и CI)
├── scripts/
│   ├── generate-typescript-sdk.sh    # Генерация TypeScript SDK с версией из semver-тега
│   ├── build-typescript-sdk.sh       # Сборка npm-пакета TypeScript SDK (dist + dist/esm)
│   ├── npm-install-deps.sh           # npm-зависимости подпроектов (registry для Docker, кеш установки)
│   ├── local-test-golden.sh          # Golden тесты php/python/java/javascript
│   └── local-test-golden-typescript.sh # Golden тесты TypeScript (без пропусков)
├── tests/
│   ├── fixtures/                     # Общие эталонные JSON для golden тестов всех языков
│   ├── java/                         # Java тесты (golden)
│   ├── php/                          # PHP тесты (golden + smoke)
│   └── typescript/                   # TypeScript golden тесты (node:test + tsc)
└── clients/                          # Сгенерированные SDK (создаётся при генерации)
```

---

## Добавление нового языка SDK

### 1. Добавить скрипт генерации в package.json

```json
{
  "scripts": {
    "generate-python": "openapi-generator-cli generate -i src/openapi.yaml -g python -o clients/python",
    "generate-java": "openapi-generator-cli generate -i src/openapi.yaml -g java -o clients/java",
    "generate-javascript": "openapi-generator-cli generate -i src/openapi.yaml -g javascript -o clients/javascript"
  }
}
```

### 2. Создать тесты

Создайте папку `tests/<language>/` со структурой:

```
fixtures/             # Эталонные JSON файлы
tests/<language>/
├── golden/           # Golden тесты (сериализация/десериализация)
└── README.md         # Инструкции
```

### 3. Обновить CI job'ы

Job'ы для новых языков уже созданы как заглушки в:
- `gitlab/sdk/generate-sdk.yml` — генерация
- `gitlab/sdk/sdk-tests-golden.yml` — golden тесты
- `gitlab/sdk/sdk-tests-smoke.yml` — smoke тесты

### 4. Добавить кастомные шаблоны (опционально)

```
customtemplates/<language>/
├── model.mustache
└── api.mustache
```

Обновите скрипт генерации с флагом `-t customtemplates/<language>`.

---

## Тестирование

### Типы тестов

#### Golden тесты

Проверяют корректность сериализации и десериализации моделей SDK на общих эталонных JSON из `tests/fixtures/` — одни и те же файлы используют PHP, Java и TypeScript:

```php
// Пример PHP golden теста
$jsonData = json_decode(file_get_contents('fixtures/product.json'), true);
$product = Product::fromArray($jsonData);

$this->assertEquals($jsonData['id'], $product->getId());
$this->assertEquals($jsonData['name'], $product->getName());
```

Запуск по языкам: `make test-golden-php`, `make test-golden-java`, `make test-golden-typescript` или сразу несколько — `make test-golden LANGUAGES=php,typescript`. Про особенности TypeScript-набора см. [Golden тесты TypeScript SDK](#golden-тесты-typescript-sdk).

#### Smoke тесты (openapi-mock)

Проверяют доступность эндпоинтов через openapi-mock сервер ([muonsoft/openapi-mock](https://github.com/muonsoft/openapi-mock)):

```php
// Пример smoke теста
$response = $client->get('/api/remap/1.2/entity/product');
$this->assertContains($response->getStatusCode(), [200, 401, 500]);
```

> **Примечание:** openapi-mock может возвращать HTTP 500 для эндпоинтов с рекурсивными/глубоко вложенными схемами — это ожидаемое поведение mock-сервера, а не ошибка спецификации.

> **Важно:** openapi-mock загружает `dist/openapi.yaml` **один раз при старте** и кэширует в памяти. После `make light-bundle` необходимо перезапустить mock-сервер: `docker compose restart mock`. Без перезапуска новые эндпоинты будут возвращать 404. Если `restart` не помогает, пересоздайте контейнер: `docker compose rm -sf mock && docker compose up -d mock`.

#### Contract тесты (Schemathesis)

**При добавлении или существенном изменении сущности** после локальных lint/bundle/golden/smoke рекомендуется дополнительно прогнать targeted `coverage` только по путям этой сущности. `<keyword>` — URL-ключ сущности из спецификации (например, `product`, `customerorder`).

Локально (Docker):

```bash
docker compose run --rm \
  -e SCHEMATHESIS_HOST=https://api.example.com \
  -e SCHEMATHESIS_LOGIN=user \
  -e SCHEMATHESIS_PASSWORD=pass \
  -e SCHEMATHESIS_PHASES=coverage \
  -e SCHEMATHESIS_INCLUDE_PATH_REGEX='^/entity/<keyword>(/|$)' \
  sdk make schemathesis
```

Автоматическое тестирование API на соответствие OpenAPI спецификации:

```bash
schemathesis run dist/openapi.yaml \
  --url "$SCHEMATHESIS_HOST" \
  -H "Authorization: Basic ${AUTH_HEADER}" \
  --max-examples=50 \
  --phases examples
```

Локальный `make schemathesis` по умолчанию: фаза `examples`, `--mode positive`, проверка `positive_data_acceptance` (валидные example → **2XX**). Без `requestBody.example` операция в examples не тестируется (у Schemathesis `fill-missing` выключен по умолчанию).

Для ручного targeted coverage по конкретной изменённой сущности задайте фильтры:

```bash
SCHEMATHESIS_PHASES=coverage \
SCHEMATHESIS_INCLUDE_PATH_REGEX='^/entity/product(/|$)' \
SCHEMATHESIS_INCLUDE_METHOD=POST \
make schemathesis
```

Для точечной проверки добавленного example используйте `operationId`:

```bash
SCHEMATHESIS_PHASES=examples \
SCHEMATHESIS_INCLUDE_OPERATION_ID=createProduct \
make schemathesis
```

Отладка: `SCHEMATHESIS_REPEAT=2` — два прогона подряд; `SCHEMATHESIS_SEED` — другой seed (по умолчанию `1`).

## Ссылки

- [OpenAPI спецификация](src/openapi.yaml)
- [МойСклад API документация](https://dev.moysklad.ru/doc/api/remap/1.2/)
- [PHP SDK репозиторий](https://github.com/moysklad/php-remap-1.2-sdk)
- [OpenAPI Generator](https://openapi-generator.tech/)
- [Redocly CLI](https://redocly.com/docs/cli/)
- [Schemathesis](https://schemathesis.readthedocs.io/)
- [openapi-mock](https://github.com/muonsoft/openapi-mock)
