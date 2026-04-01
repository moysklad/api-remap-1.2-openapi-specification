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

# Сборка bundled спецификации
npm run bundle
npm run bundle-json
```

---

## Локальный запуск (Docker)

Docker-среда поддерживает несколько языков SDK (php, python, java, javascript). Сейчас реализованы генерация и тесты для PHP; для остальных языков нужно добавить скрипты в `package.json` и тесты в `tests/<language>/`.

```bash
# Сборка образа
docker compose build

# Проверка спецификации
docker compose run --rm sdk make lint

# Сборка bundled спецификации
docker compose run --rm sdk make bundle

# Генерация SDK (по умолчанию PHP; можно несколько: LANGUAGES=php,python)
docker compose run --rm sdk make generate
docker compose run --rm sdk make generate-php

# Golden тесты (по умолчанию php)
docker compose run --rm sdk make test-golden
docker compose run --rm sdk make test-golden-php

# Smoke тесты (openapi-mock + тесты по языкам)
docker compose run --rm sdk make test-smoke
docker compose run --rm sdk make test-smoke-php

# Контрактные тесты Schemathesis (один для всех языков)
docker compose run --rm -e SCHEMATHESIS_HOST=host -e SCHEMATHESIS_LOGIN=login -e SCHEMATHESIS_PASSWORD=pass sdk make schemathes

# Полный прогон (lint, bundle, generate-php, test-golden, test-smoke, schemathesis)
docker compose run --rm sdk make all
```

**Языки:** целевой язык задаётся через `LANGUAGES`:  
`docker compose run --rm sdk make test-smoke LANGUAGES=php,python` (когда появятся тесты для python).

**Без сборки образа** (если есть образ из CI):

```bash
docker run --rm -v "$(pwd):/workspace" -w /workspace \
  docker.infra.lognex/docker-openapitools:1.2-release make all
```

**Локальный Docker и Nexus:** если в `package-lock.json` указан корпоративный registry (nexus.infra.lognex), при запуске в Docker задаётся `USE_PUBLIC_NPM_REGISTRY=true`. Скрипт `scripts/npm-ci-public-registry.sh` временно подменяет URL на registry.npmjs.org, чтобы не было ошибки SSL (UNABLE_TO_VERIFY_LEAF_SIGNATURE). Исходный `package-lock.json` после `npm ci` восстанавливается.

**Schemathesis** (контрактные тесты против живого API):

```bash
docker compose run --rm \
  -e SCHEMATHESIS_HOST=https://api.example.com \
  -e SCHEMATHESIS_LOGIN=user \
  -e SCHEMATHESIS_PASSWORD=pass \
  sdk make schemathesis
```

Список целей: `docker compose run --rm sdk make help`.

---

## Структура проекта

```
api-sdk-builder/
├── .gitlab-ci.yml                    # Главный CI файл
├── gitlab/
│   ├── .gitlab-ci-sdk-validate.yml   # Валидация SDK (lint, tests)
│   ├── .gitlab-ci-github-mirror.yml  # Зеркалирование в GitHub
│   ├── .gitlab-ci-prepare-sdk-php.yml# Подготовка внутреннего репозитория PHP SDK (ветки и релиз мастер‑ветки)
│   ├── .gitlab-ci-java-sdk.yml       # Старый Java SDK (обратная совместимость)
│   ├── .gitlab-ci-spec-gen.yml       # Старый PHP через OpenAPI (обратная совместимость)
│   ├── version.gitlab-ci.yml         # Версионирование спецификации
│   └── sdk/
│       ├── lint-openapi.yml          # Job для lint спецификации
│       ├── generate-sdk.yml          # Job'ы для генерации SDK
│       ├── sdk-tests-golden.yml      # Golden тесты
│       ├── sdk-tests-smoke.yml       # Smoke тесты с openapi-mock
│       └── sdk-contract.yml          # Schemathesis контрактные тесты
├── src/
│   └── openapi.yaml                  # Главный файл OpenAPI спецификации
├── customtemplates/
│   └── php/                          # Кастомные шаблоны для PHP SDK
├── tests/
│   └── php/                          # PHP тесты (golden + smoke)
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
tests/<language>/
├── golden/           # Golden тесты (сериализация/десериализация)
├── smoke/            # Smoke тесты (проверка эндпоинтов)
├── fixtures/         # Эталонные JSON файлы
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

Проверяют корректность сериализации и десериализации моделей SDK:

```php
// Пример PHP golden теста
$jsonData = json_decode(file_get_contents('fixtures/product.json'), true);
$product = Product::fromArray($jsonData);

$this->assertEquals($jsonData['id'], $product->getId());
$this->assertEquals($jsonData['name'], $product->getName());
```

#### Smoke тесты (openapi-mock)

Проверяют доступность эндпоинтов через openapi-mock сервер ([muonsoft/openapi-mock](https://github.com/muonsoft/openapi-mock)):

```php
// Пример PHP smoke теста
$response = $client->get('/api/remap/1.2/entity/product');
$this->assertContains($response->getStatusCode(), [200, 401, 500]);
```

> **Примечание:** openapi-mock может возвращать HTTP 500 для эндпоинтов с рекурсивными/глубоко вложенными схемами — это ожидаемое поведение mock-сервера, а не ошибка спецификации.

#### Contract тесты (Schemathesis)

Автоматическое тестирование API на соответствие OpenAPI спецификации:

```bash
schemathesis run dist/openapi.yaml \
  --url "$SCHEMATHESIS_HOST" \
  -H "Authorization: Basic ${AUTH_HEADER}" \
  --max-examples=50 \
  --phases examples,fuzzing,stateful
```

## Ссылки

- [OpenAPI спецификация](src/openapi.yaml)
- [МойСклад API документация](https://dev.moysklad.ru/doc/api/remap/1.2/)
- [PHP SDK репозиторий](https://github.com/moysklad/php-remap-1.2-sdk)
- [OpenAPI Generator](https://openapi-generator.tech/)
- [Redocly CLI](https://redocly.com/docs/cli/)
- [Schemathesis](https://schemathesis.readthedocs.io/)
- [openapi-mock](https://github.com/muonsoft/openapi-mock)
