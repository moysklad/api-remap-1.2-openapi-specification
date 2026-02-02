# JavaScript SDK Tests

Тесты для JavaScript/TypeScript SDK МойСклад API.

## Структура

```
tests/javascript/
├── package.json              # npm конфигурация
├── jest.config.js           # Jest конфигурация
├── golden/                  # Golden тесты (сериализация/десериализация)
│   ├── product.test.js
│   └── counterparty.test.js
├── smoke/                   # Smoke тесты (проверка эндпоинтов)
│   ├── product-api.test.js
│   └── counterparty-api.test.js
└── fixtures/                # Эталонные JSON файлы
    ├── product.json
    └── counterparty.json
```

## Настройка

1. Добавить скрипт генерации в корневой `package.json`:

```json
{
  "scripts": {
    "generate-javascript": "openapi-generator-cli generate -i src/openapi.yaml -g javascript -o clients/javascript"
  }
}
```

Или для TypeScript:

```json
{
  "scripts": {
    "generate-javascript": "openapi-generator-cli generate -i src/openapi.yaml -g typescript-fetch -o clients/javascript"
  }
}
```

2. Установить зависимости:

```bash
cd tests/javascript
npm install
```

3. Запуск тестов:

```bash
# Все тесты
npm test

# Только golden тесты
npm run test:golden

# Только smoke тесты
npm run test:smoke
```

## Переменные окружения

- `PRISM_BASE_URL` - URL Prism mock сервера (по умолчанию: http://localhost:4010)
- `SDK_PATH` - Путь к сгенерированному SDK (по умолчанию: ../../clients/javascript)

## Добавление новых тестов

### Golden тесты

Golden тесты проверяют корректность сериализации и десериализации моделей:

```javascript
const { Product } = require('../../clients/javascript');
const productFixture = require('./fixtures/product.json');

describe('Product Serialization', () => {
  test('should deserialize product from JSON', () => {
    const product = Product.constructFromObject(productFixture);
    
    expect(product.id).toBe(productFixture.id);
    expect(product.name).toBe(productFixture.name);
  });
});
```

### Smoke тесты

Smoke тесты проверяют доступность эндпоинтов через Prism mock сервер:

```javascript
const axios = require('axios');

describe('Product API', () => {
  test('should list products', async () => {
    const response = await axios.get(
      `${process.env.PRISM_BASE_URL}/api/remap/1.2/entity/product`,
      { validateStatus: () => true }
    );
    
    expect([200, 401]).toContain(response.status);
  });
});
```
