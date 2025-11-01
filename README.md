# МойСклад OpenAPI Спецификация

Модульная OpenAPI 3.0.3 спецификация для МойСклад JSON API 1.2

## Быстрый старт

Тестировалось на версии nvm v24.0.2

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

Спецификация следует версионированию МойСклад API:
- Текущая версия: `1.2`
- URL: `https://api.moysklad.ru/api/remap/1.2`

## Лицензия

Спецификация создана на основе официальной документации МойСклад API.
