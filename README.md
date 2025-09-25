# МойСклад OpenAPI Спецификация

Модульная OpenAPI 3.0.3 спецификация для МойСклад JSON API 1.2

## Структура проекта

```
├── openapi.yaml                    # Главный файл спецификации
├── components/                     # Переиспользуемые компоненты
│   ├── security-schemes.yaml       # Схемы аутентификации
│   ├── parameters.yaml             # Общие параметры
│   ├── responses.yaml              # Общие ответы
│   └── schemas/                    # Схемы данных
│       ├── common/                 # Общие схемы
│       │   ├── meta.yaml           # Метаданные
│       │   └── error.yaml          # Ошибки
│       ├── products/               # Схемы товаров
│       ├── counterparties/         # Схемы контрагентов
│       ├── organizations/          # Схемы юрлиц
│       ├── documents/              # Схемы документов
│       │   ├── demands/            # Отгрузки
│       │   └── supplies/           # Приемки
│       ├── dictionaries/           # Справочники
│       └── reports/                # Отчеты
└── paths/                          # Определения путей API
    ├── auth/                       # Аутентификация
    ├── products/                   # Товары
    ├── counterparties/             # Контрагенты
    ├── organizations/              # Юрлица
    ├── documents/                  # Документы
    │   ├── demands/                # Отгрузки
    │   └── supplies/               # Приемки
    ├── dictionaries/               # Справочники
    ├── metadata/                   # Метаданные
    └── reports/                    # Отчеты
```

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

## Добавление новых сущностей

### 1. Создание схем
```bash
# Создать папку для новой сущности
mkdir components/schemas/new-entity

# Создать файлы схем
touch components/schemas/new-entity/entity.yaml
touch components/schemas/new-entity/entity-create.yaml
touch components/schemas/new-entity/entity-update.yaml
touch components/schemas/new-entity/entity-list.yaml
```

### 2. Создание путей
```bash
# Создать папку для путей
mkdir paths/new-entity

# Создать файлы путей
touch paths/new-entity/entities.yaml
touch paths/new-entity/entity-by-id.yaml
```

### 3. Обновление главного файла
Добавить ссылки в `openapi.yaml`:
```yaml
paths:
  /entity/new-entity:
    $ref: './paths/new-entity/entities.yaml'
  /entity/new-entity/{id}:
    $ref: './paths/new-entity/entity-by-id.yaml'

components:
  schemas:
    NewEntity:
      $ref: './components/schemas/new-entity/entity.yaml'
```

## Соглашения

### Именование файлов
- Используйте kebab-case для имен файлов
- Схемы: `entity.yaml`, `entity-create.yaml`, `entity-update.yaml`, `entity-list.yaml`
- Пути: `entities.yaml`, `entity-by-id.yaml`

### Структура схем
- Всегда указывайте `type: object` для объектов
- Используйте `required` для обязательных полей
- Добавляйте `description` для всех полей
- Используйте `$ref` для ссылок на другие схемы

### Ссылки
- Относительные пути от текущего файла
- Используйте `../` для перехода на уровень выше
- Всегда указывайте расширение `.yaml`

## Теги и группировка

Endpoints группируются по тегам:
- `Authentication` - аутентификация
- `Products` - товары
- `Counterparties` - контрагенты
- `Organizations` - юрлица
- `Documents` - документы
- `Dictionaries` - справочники
- `Reports` - отчеты
- `Metadata` - метаданные

## Версионирование

Спецификация следует версионированию МойСклад API:
- Текущая версия: `1.2`
- URL: `https://api.moysklad.ru/api/remap/1.2`

## Лицензия

Спецификация создана на основе официальной документации МойСклад API.
