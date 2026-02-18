# МойСклад OpenAPI SDK Builder

Проект для генерации SDK на различных языках программирования из OpenAPI спецификации МойСклад JSON API 1.2.

## Содержание

- [Быстрый старт](#быстрый-старт)
- [Локальный запуск (Docker)](#локальный-запуск-docker)
- [Структура проекта](#структура-проекта)
- [GitLab CI/CD Pipeline](#gitlab-cicd-pipeline)
- [Переменные окружения](#переменные-окружения)
- [Stages (стадии)](#stages-стадии)
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

# Smoke тесты (Prism + тесты по языкам)
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
  docker.infra.lognex/docker-openapitools:1.0-release make all
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
│   ├── .gitlab-ci-sdk-php-gen.yml    # Push SDK в удалённые репозитории
│   ├── .gitlab-ci-github-mirror.yml  # Зеркалирование в GitHub
│   ├── .gitlab-ci-java-sdk.yml       # Старый Java SDK (обратная совместимость)
│   ├── .gitlab-ci-spec-gen.yml       # Старый PHP через OpenAPI (обратная совместимость)
│   └── sdk/
│       ├── lint-openapi.yml          # Job для lint спецификации
│       ├── generate-sdk.yml          # Job'ы для генерации SDK
│       ├── sdk-tests-golden.yml      # Golden тесты
│       ├── sdk-tests-prism.yml       # Smoke тесты с Prism
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

## GitLab CI/CD Pipeline

### Сценарии запуска

#### 1. Push в ветку (автоматический)

При каждом push в любую ветку (кроме тегов) запускаются:

| Job                | Описание                                           |
|--------------------|----------------------------------------------------|
| `lint-openapi`     | Проверка OpenAPI спецификации с помощью Redocly    |
| `bundle-openapi`   | Сборка bundled версии спецификации                 |
| `generate-sdk-php` | Генерация PHP SDK                                  |
| `sdk-golden-php`   | Golden тесты для PHP (сериализация/десериализация) |
| `sdk-prism-php`    | Smoke тесты для PHP с Prism mock сервером          |

#### 2. Ручной запуск (web) на ветке

При ручном запуске на ветке (не master/release/tag) доступны дополнительно:

| Job            | Описание                                                           |
|----------------|--------------------------------------------------------------------|
| `sdk-contract` | Schemathesis контрактные тесты (требуют SCHEMATHESIS_* переменные) |
| `push-sdk-php` | Push PHP SDK в GitHub репозиторий (если `PUSH_TO_REMOTE=true`)     |

#### 3. Merge в master

При merge в master запускаются все тесты + зеркалирование:

| Job                | Описание                                    |
|--------------------|---------------------------------------------|
| `lint-openapi`     | Проверка спецификации                       |
| `bundle-openapi`   | Сборка bundled версии                       |
| `generate-sdk-*`   | Генерация SDK                               |
| `sdk-golden-*`     | Golden тесты                                |
| `sdk-prism-*`      | Smoke тесты                                 |
| `mirror-to-github` | Зеркалирование в GitHub (без gitlab файлов) |

---

## Переменные окружения

### Основные переменные

| Переменная       | Описание                                             | Значение по умолчанию |
|------------------|------------------------------------------------------|-----------------------|
| `SDK_LANGUAGES`  | Языки для генерации SDK (через запятую без пробелов) | `""` (все доступные)  |
| `PUSH_TO_REMOTE` | Пушить SDK в удалённые репозитории                   | `"false"`             |

**Примеры SDK_LANGUAGES:**
- `""` или не задана — генерируются все доступные SDK (сейчас только PHP)
- `"php"` — только PHP
- `"php,python"` — PHP и Python
- `"php,python,java,javascript"` — все языки

### Переменные для Schemathesis тестов

Обязательны при ручном запуске (web):

| Переменная              | Описание              | Пример            |
|-------------------------|-----------------------|-------------------|
| `SCHEMATHESIS_HOST`     | URL API сервера       | `host`            |
| `SCHEMATHESIS_LOGIN`    | Логин для Basic auth  | `admin@test_user` |
| `SCHEMATHESIS_PASSWORD` | Пароль для Basic auth | `password123`     |

### Переменные для Push/Mirror

| Переменная     | Описание                         |
|----------------|----------------------------------|
| `GIT_PASSWORD` | Токен для доступа к GitHub       |
| `GIT_USER`     | Имя пользователя для git commits |
| `GIT_MAIL`     | Email для git commits            |

### Переменные для обратной совместимости

| Переменная                              | Описание                                                     |
|-----------------------------------------|--------------------------------------------------------------|
| `SPEC_SDK_GENERATION`                   | `"true"` для нового OpenAPI пайплайна, иначе старый Java SDK |
| `BRANCH`                                | Ветка SDK репозитория (старый пайплайн)                      |
| `API_HOST`, `API_LOGIN`, `API_PASSWORD` | Параметры для старого пайплайна                              |

---

## Stages (стадии)

### Текущие стадии

| Стадия         | Описание                               | Используется в |
|----------------|----------------------------------------|----------------|
| `verify`       | Проверка спецификации, bundling        | Новый пайплайн |
| `generate-sdk` | Генерация SDK                          | Новый пайплайн |
| `test`         | Тестирование (golden, smoke, contract) | Новый пайплайн |
| `push-sdk`     | Push SDK в удалённые репозитории       | Новый пайплайн |
| `mirror`       | Зеркалирование в GitHub                | Новый пайплайн |

### Стадии для обратной совместимости (можно удалить после миграции)

| Стадия             | Описание                        | Статус        |
|--------------------|---------------------------------|---------------|
| `prepare`          | Подготовка старого пайплайна    | Можно удалить |
| `deploy-for-space` | Деплой тестового окружения      | Можно удалить |
| `create-user`      | Создание тестового пользователя | Можно удалить |
| `build`            | Сборка старого Java SDK         | Можно удалить |
| `delete-space`     | Удаление тестового окружения    | Можно удалить |

> **Примечание:** Стадии для обратной совместимости можно удалить после полной миграции на новый пайплайн. Они используются только файлами `.gitlab-ci-java-sdk.yml` и `.gitlab-ci-spec-gen.yml`.

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
- `gitlab/sdk/sdk-tests-prism.yml` — smoke тесты
- `gitlab/.gitlab-ci-sdk-php-gen.yml` — push в репозиторий

Нужно:
1. Настроить реальные репозитории в `push-sdk-*` job'ах
2. Раскомментировать скрипты push после создания репозиториев

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

#### Smoke тесты (Prism)

Проверяют доступность эндпоинтов через Prism mock сервер:

```php
// Пример PHP smoke теста
$response = $client->get('/api/remap/1.2/entity/product');
$this->assertContains($response->getStatusCode(), [200, 401]);
```

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
- [Prism Mock Server](https://stoplight.io/open-source/prism)
