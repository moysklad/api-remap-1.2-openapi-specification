# Python SDK Tests

Тесты для Python SDK МойСклад API.

## Структура

```
tests/python/
├── requirements.txt      # Зависимости для тестов
├── conftest.py          # Pytest конфигурация и fixtures
├── golden/              # Golden тесты (сериализация/десериализация)
│   ├── test_product.py
│   └── test_counterparty.py
├── smoke/               # Smoke тесты (проверка эндпоинтов)
│   ├── test_product_api.py
│   └── test_counterparty_api.py
└── fixtures/            # Эталонные JSON файлы
    ├── product.json
    └── counterparty.json
```

## Настройка

1. Добавить скрипт генерации в `package.json`:

```json
{
  "scripts": {
    "generate-python": "openapi-generator-cli generate -i src/openapi.yaml -g python -o clients/python"
  }
}
```

2. Установить зависимости:

```bash
cd tests/python
pip install -r requirements.txt
```

3. Запуск тестов:

```bash
# Все тесты
pytest

# Только golden тесты
pytest golden/ -v

# Только smoke тесты
pytest smoke/ -v
```

## Переменные окружения

- `PRISM_BASE_URL` - URL Prism mock сервера (по умолчанию: http://localhost:4010)
- `SDK_PATH` - Путь к сгенерированному SDK (по умолчанию: ../../clients/python)

## Добавление новых тестов

### Golden тесты

Golden тесты проверяют корректность сериализации и десериализации моделей:

```python
def test_deserialize_product():
    with open('fixtures/product.json') as f:
        data = json.load(f)
    
    product = Product.from_dict(data)
    assert product.id == data['id']
    assert product.name == data['name']
```

### Smoke тесты

Smoke тесты проверяют доступность эндпоинтов через Prism mock сервер:

```python
def test_list_products(client):
    response = client.get('/api/remap/1.2/entity/product')
    assert response.status_code in [200, 401]
```
