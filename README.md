# МойСклад OpenAPI Спецификация

Модульная OpenAPI 3.0.3 спецификация для МойСклад JSON API 1.2

## Быстрый старт

Тестировалось на версии nvm v24.0.1

```bash
nvm use v24.0.1
npm run generate-php
```

### 1. Установка зависимостей
```bash
npm install
```

### 2. Валидация спецификации
```bash
npm run validate
```

### 3. Генерация документации
```bash
npm run docs
```

### 4. Локальный просмотр документации
```bash
npm run serve-docs
```

### 5. Генерация клиентских SDK
```bash
npm run generate-php
```

## Локальный запуск через Make

Все шаги пайплайна (lint, bundle, генерация SDK, golden-, smoke-тесты, а также schemathesis тесты) можно запускать локально через Make — **напрямую** или **в Docker**.

### Через Docker (рекомендуется)

Требуется только Docker и Docker Compose. Остальное уже есть в образе.

```bash
docker compose run --rm sdk make help          # список целей
docker compose run --rm sdk make lint         # проверка OpenAPI
docker compose run --rm sdk make bundle       # сборка dist/openapi.yaml
docker compose run --rm sdk make generate-php # генерация PHP SDK
docker compose run --rm sdk make test-golden-php  # golden-тесты
docker compose run --rm sdk make test-smoke-php  # smoke-тесты (openapi-mock поднимается автоматически)
docker compose run --rm -e SCHEMATHESIS_HOST=host -e SCHEMATHESIS_LOGIN=login -e SCHEMATHESIS_PASSWORD=pass sdk make schemathesis # schemathesis-тесты на реальном окружении
docker compose run --rm sdk make all          # lint + bundle + generate-php + test-golden + test-smoke
```

При повторных запусках зависимости npm не перекачиваются (пропуск `npm ci`, если `package-lock.json` не менялся). Принудительная переустановка:  
`docker compose run --rm -e NPM_CI_FORCE=1 sdk make lint`

### Локально (без Docker)

На машине должны быть установлены: **Node.js**, **npm**, **PHP ≥8.1** с расширениями **dom**, **json**, **mbstring**, **curl**, **Composer**.

```bash
make help
make lint
make bundle
make generate-php
make test-golden-php   # из корня репо; в tests/php нужен composer install
make test-smoke-php    # нужен запущенный openapi-mock (например на http://localhost:8080)
make schemathesis # в скрипте нужно также задать переменные SCHEMATHESIS_HOST, SCHEMATHESIS_LOGIN, SCHEMATHESIS_PASSWORD
```

Скрипты в `scripts/` определяют корень репозитория по своему пути, поэтому их можно вызывать из любой директории (и из Docker, и локально).

## Добавление новых сущностей

### 1. Создание схем
```bash
# Выбрать из существующих или создать новую папку для описания новой сущности
mkdir components/schemas/{category}

# Создать файлы схем
touch components/schemas/{category}/entity.yaml
touch components/schemas/{category}/entityList.yaml
```

### 2. Создание путей
```bash
# Создать папку для путей
mkdir paths/{category}/{entity}

# Создать файлы путей
touch paths/{category}/{entity}/entities.yaml
touch paths/{category}/{entity}/entity-by-id.yaml
```

### 3. Обновление главного файла
Добавить ссылки в `openapi.yaml`:
```yaml
paths:
  /entity/{entity-name}:
    $ref: './paths/{category}/{entity}/entities.yaml'
  /entity/{entity-name}/{id}:
    $ref: './paths/{category}/{entity}/entity-by-id.yaml'

components:
  schemas:
    NewEntity:
      $ref: './components/schemas/{category}/entity.yaml'
```

## Версионирование

Версионирование спецификации соответствует формату semver `X.Y.Z` (например, `0.3.0`, `1.0.0`)

## Лицензия

Спецификация создана на основе официальной документации МойСклад API..

