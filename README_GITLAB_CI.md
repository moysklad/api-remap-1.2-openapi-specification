# МойСклад OpenAPI SDK Builder — GitLab CI/CD

Этот документ описывает GitLab CI/CD пайплайн для проекта OpenAPI спецификации МойСклад JSON API 1.2.

## GitLab CI/CD Pipeline

### Сценарии запуска

#### 1. Push в ветку (автоматический)

При каждом push в любую ветку (кроме тегов) запускаются проверки SDK:

| Job                | Описание                                           |
|--------------------|----------------------------------------------------|
| `lint-openapi`     | Проверка OpenAPI спецификации с помощью Redocly    |
| `bundle-openapi`   | Сборка bundled версии спецификации                 |
| `generate-sdk-php` | Генерация PHP SDK                                  |
| `generate-sdk-java`| Генерация Java SDK (заглушка)                      |
| `sdk-golden-php`   | Golden тесты для PHP (сериализация/десериализация) |
| `sdk-prism-php`    | Smoke тесты для PHP с Prism mock сервером          |
| `sdk-golden-java`  | Golden тесты для Java (заглушка)                   |
| `sdk-prism-java`   | Smoke тесты для Java (заглушка)                    |

#### 2. Ручной запуск (web) на ветке

При ручном запуске пайплайна (`CI_PIPELINE_SOURCE == "web"`, без тэга) выполняются те же SDK‑job'ы, что и при push, плюс контрактные тесты и опциональный пуш SDK:

| Job                      | Описание                                                           |
|--------------------------|--------------------------------------------------------------------|
| `lint-openapi` / `bundle-openapi` | Проверка и сборка спецификации                           |
| `generate-sdk-*`        | Генерация SDK                                                      |
| `sdk-golden-*` / `sdk-prism-*` | Golden и smoke тесты SDK                                   |
| `deploy-contract-env`   | Подготовка окружения для schemathesis (ветка **stable** сервиса)   |
| `create-contract-user`  | Создание пользователя для contract‑тестов, экспорт кредов          |
| `sdk-contract`          | Schemathesis контрактные тесты (стадия `contract-test`)            |
| `remove-contract-env`   | Очистка окружения после contract‑тестов (manual, allow_failure)    |
| `push-sdk-php`          | Push PHP SDK в GitHub репозиторий (если `PUSH_TO_REMOTE=true`)     |

#### 3. Merge/push в master

При push/merge в `master` запускается полный релизный flow: проверки, контрактные тесты, версионирование и зеркалирование:

| Job                     | Описание                                    |
|-------------------------|---------------------------------------------|
| `check-openapi-changes` | Проверка изменений OpenAPI относительно последнего тега в текущем репо |
| `lint-openapi`          | Проверка спецификации                       |
| `bundle-openapi`        | Сборка bundled версии                       |
| `deploy-contract-env`   | Подготовка окружения для schemathesis       |
| `create-contract-user`  | Создание пользователя и экспорт SCHEMATHESIS_* переменных |
| `sdk-contract`          | Контрактные тесты Schemathesis              |
| `remove-contract-env`   | Очистка окружения (manual, allow_failure)   |
| `generate-sdk-*`        | Генерация SDK                               |
| `sdk-golden-*`          | Golden тесты                                |
| `sdk-prism-*`           | Smoke тесты                                 |
| `version:auto`          | Автоматическое версионирование и выпуск тега|
| `mirror-to-github`      | Зеркалирование в GitHub (без GitLab‑файлов и CI README) |
| `create-github-release` | Создание GitHub Release на основе CHANGELOG |

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

| Переменная                    | Описание                      | Пример               |
|-------------------------------|-------------------------------|----------------------|
| `SCHEMATHESIS_HOST`           | URL API сервера               | `host`               |
| `SCHEMATHESIS_LOGIN`          | Логин для Basic auth          | `admin@test_user`    |
| `SCHEMATHESIS_PASSWORD`       | Пароль для Basic auth         | `password123`        |
| `SCHEMATHESIS_PHASES`         | Режимы тестирования           | `examples,coverage`  |
| `SCHEMATHESIS_MAX_EXAMPLES`   | Максимум примеров на эндпоинт | `50` (по умолчанию)  |


**Режимы тестирования (SCHEMATHESIS_PHASES):**
- `examples` - базовые тесты
- `coverage` - расширенное покрытие
- `fuzzing` - случайные данные
- `stateful` - изменение данных

**По умолчанию:** `examples,coverage`


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

| Стадия          | Описание                                           |
|-----------------|----------------------------------------------------|
| `changes-check` | Проверка изменений OpenAPI в `src/` относительно последнего тега (теги из текущего репо) |
| `verify`        | Проверка спецификации, bundling; подготовка окружения для contract (deploy-contract-env на ветке **stable**, create-contract-user) |
| `contract-test` | Контрактные тесты Schemathesis (`sdk-contract`) — после verify, до generate-sdk |
| `generate-sdk`  | Генерация SDK                                     |
| `test`          | Тестирование (golden, smoke)                      |
| `version`       | Автоматическое версионирование и подготовка CHANGELOG/тегов |
| `push-sdk`      | Push SDK в удалённые репозитории                  |
| `mirror`        | Зеркалирование в GitHub и GitHub Release          |

### Стадии для обратной совместимости (старый Java SDK)

| Стадия             | Описание                        |
|--------------------|---------------------------------| 
| `prepare`          | Подготовка старого пайплайна    |
| `deploy-for-space` | Деплой тестового окружения      |
| `create-user`      | Создание тестового пользователя |
| `build`            | Сборка старого Java SDK         |
| `delete-space`     | Удаление тестового окружения    |

---

## Версионирование и CHANGELOG

Job `version:auto`:

- проверяет обратную совместимость с помощью `oasdiff`:
  - генерирует `breaking-changes.json` и `breaking-changes.md`;
  - если есть breaking changes, поднимает MAJOR версию, иначе MINOR;
- использует `standard-version` с конфигурацией из `.versionrc.json`:
  - формат версии `MAJOR.MINOR` с суффиксом `-release` (пример: `1.2-release`);
  - читает Conventional Commits (`feat`, `fix`, `docs`, `feat!`);
- формирует блок изменений для новой версии из сообщений коммитов (`feat`, `fix`, `docs`) и дописывает его в начало `CHANGELOG.md`;
  - если подходящих коммитов нет, в блок версии добавляется строка по умолчанию: **\* технические изменения**;
- создаёт аннотированный тег: **сообщение тега совпадает с блоком изменений** для этой версии (тем же текстом, что записан в `CHANGELOG.md`);
- в сообщение release-коммита добавляется **`[skip ci]`**, чтобы push из `version:auto` не запускал второй пайплайн проверок;
- пушит новый тег и текущую ветку.

При отсутствии тегов versioning стартует с `0.y-release` (MINOR).


