# Java SDK Tests

Тесты для Java SDK МойСклад API.

## Структура

```
tests/java/
├── pom.xml                           # Maven конфигурация
├── src/
│   └── test/
│       ├── java/
│       │   └── ru/moysklad/sdk/
│       │       ├── golden/           # Golden тесты
│       │       │   ├── ProductSerializationTest.java
│       │       │   └── CounterpartySerializationTest.java
│       │       └── smoke/            # Smoke тесты
│       │           ├── ProductApiTest.java
│       │           └── CounterpartyApiTest.java
│       └── resources/
│           └── fixtures/             # Эталонные JSON файлы
│               ├── product.json
│               └── counterparty.json
```

## Настройка

1. Добавить скрипт генерации в `package.json`:

```json
{
  "scripts": {
    "generate-java": "openapi-generator-cli generate -i src/openapi.yaml -g java -o clients/java --additional-properties=library=okhttp-gson"
  }
}
```

2. Собрать тесты:

```bash
cd tests/java
mvn test-compile
```

3. Запуск тестов:

```bash
# Все тесты
mvn test

# Только golden тесты
mvn test -Dtest=**/golden/**

# Только smoke тесты  
mvn test -Dtest=**/smoke/**
```

## Переменные окружения

- `PRISM_BASE_URL` - URL Prism mock сервера (по умолчанию: http://localhost:4010)

## Добавление новых тестов

### Golden тесты

Golden тесты проверяют корректность сериализации и десериализации моделей с использованием Jackson/Gson:

```java
@Test
void testDeserializeProduct() throws Exception {
    String json = loadFixture("product.json");
    Product product = objectMapper.readValue(json, Product.class);
    
    assertEquals("expected-id", product.getId());
    assertEquals("expected-name", product.getName());
}
```

### Smoke тесты

Smoke тесты проверяют доступность эндпоинтов через Prism mock сервер:

```java
@Test
void testListProducts() {
    Response response = client.target(PRISM_BASE_URL)
        .path("/api/remap/1.2/entity/product")
        .request()
        .get();
    
    assertTrue(List.of(200, 401).contains(response.getStatus()));
}
```
