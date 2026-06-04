# МойСклад OpenAPI SDK Builder — GitLab CI/CD

Этот документ описывает GitLab CI/CD пайплайн для проекта OpenAPI спецификации МойСклад JSON API 1.2.

## GitLab CI/CD Pipeline

Автоматические пайплайны по событию merge request отключены в `.gitlab-ci.yml` (`workflow: rules`). Все рабочие job’ы подключаются через `include` только для `push` и `web`; без отдельного технического job’а `merge-request-ci-placeholder` GitLab при проверке конфигурации для MR выдавал бы ошибку «jobs config should contain at least one visible job» (валидация выполняется до применения `workflow: rules`).

### Сценарии запуска

#### 1. Push в ветку (автоматический)

При каждом push в любую ветку (кроме тегов) запускаются проверки SDK:

| Job                      | Описание                                                                 |
|--------------------------|--------------------------------------------------------------------------|
| `lint-openapi`           | Проверка OpenAPI спецификации с помощью Redocly                          |
| `bundle-openapi`         | Сборка bundled версии спецификации                                       |
| `generate-sdk-php`       | Генерация PHP SDK                                                        |
| `generate-sdk-java`      | Генерация Java SDK (заглушка)                                            |
| `sdk-golden-php`         | Golden тесты для PHP (сериализация/десериализация)                       |
| `sdk-smoke-php`          | Smoke тесты для PHP с openapi-mock сервером                              |
| `sdk-golden-java`        | Golden тесты для Java (заглушка)                                         |
| `sdk-smoke-java`         | Smoke тесты для Java (заглушка)                                          |
| `prep-branch-and-mr-php` | Cоздание/обновление ветки и mr по сгенерированному sdk в репозитории sdk |

#### 2. Ручной запуск (web) на ветке

При ручном запуске пайплайна (`CI_PIPELINE_SOURCE == "web"`, без тэга) выполняются те же SDK‑job'ы, что и при push, плюс контрактные тесты и опциональный пуш SDK:

| Job                               | Описание                                                                 |
|-----------------------------------|--------------------------------------------------------------------------|
| `lint-openapi` / `bundle-openapi` | Проверка и сборка спецификации                                           |
| `generate-sdk-*`                  | Генерация SDK                                                            |
| `sdk-golden-*` / `sdk-smoke-*`    | Golden и smoke тесты SDK                                                 |
| `deploy-contract-env`             | Подготовка окружения для schemathesis (ветка **stable** сервиса)         |
| `create-contract-user`            | Создание пользователя для contract‑тестов, экспорт кредов                |
| `sdk-contract`                    | Schemathesis контрактные тесты (стадия `contract-test`)                  |
| `remove-contract-env`             | Очистка окружения после contract‑тестов (manual, allow_failure)          |
| `prep-branch-and-mr-php`          | Cоздание/обновление ветки и mr по сгенерированному sdk в репозитории sdk |

#### 3. Merge/push в master

При push/merge в `master` запускается полный релизный flow: проверки, версионирование и зеркалирование. Contract-тесты Schemathesis и их обвязка в master pipeline не включаются.

| Job                     | Описание                                                                                          |
|-------------------------|---------------------------------------------------------------------------------------------------|
| `check-openapi-changes` | Проверка изменений OpenAPI относительно последнего тега в текущем репо                            |
| `lint-openapi`          | Проверка спецификации                                                                             |
| `bundle-openapi`        | Сборка bundled версии                                                                             |
| `generate-sdk-*`        | Генерация SDK                                                                                     |
| `sdk-golden-*`          | Golden тесты                                                                                      |
| `sdk-smoke-*`           | Smoke тесты                                                                                       |
| `version:auto`          | Автоматическое версионирование и выпуск тега                                                      |
| `mirror-to-github`      | Зеркалирование в GitHub без internal CI/release tooling (`gitlab`, `.gitlab-ci.yml`, CI README, `.versionrc.json`, `scripts/generate-diff-changelog.js` и др.) |
| `create-github-release` | Создание GitHub Release на основе CHANGELOG                                                       |
| `merge-branch-php`      | Обновление ветки master на удаленном gitlab sdk репозитории по сгенерированному sdk и выпуск тэга |

---

#### 4. Сборка ветки на облегчённом окружении для тестирования java-remap-1.2-sdk
* запустить новый пайплайн Remap 1.2 SDK deployer, указав:
  1. ветку пайплайна master - проект нужен только для сборки;
  2. параметр **BRANCH** - имя ветки в репозитории SDK;
  3. параметр **PARAM_VERSION** - номер сборки окружения, берётся из DMS. (Например stable-402731, можно получить зайдя в дмс и при подготовке к накату окружения в версии moysklad будет нужное значение);
  4. параметр **USE_OLD_SDK** - "true" для включения java-sdk написанного без использована openapi.

После прохождения пайпа, для удаления созданного окружения нужно запустить джобу "remove-space".
Окружение для java-sdk flow очищается в DMS через **ENV_TTL_MINUTES** минут (если не задана — **20** по умолчанию в `utils_python/client.py`). Для контрактных тестов по OpenAPI (Schemathesis) джоба `deploy-contract-env` выставляет **60** минут, пока не переопределить `ENV_TTL_MINUTES` в GitLab.

[Ссылка на предзаполненный пайплайн](https://git.company.lognex/moysklad/misc/api-sdk-builder/-/pipelines/new?ref=master&var[BRANCH]=MC-&var[PARAM_VERSION]=stable-402731&var[USE_OLD_SDK]=true)

## Переменные окружения

### Основные переменные

| Переменная       | Описание                                             | Значение по умолчанию |
|------------------|------------------------------------------------------|-----------------------|
| `SDK_LANGUAGES`  | Языки для генерации SDK (через запятую без пробелов) | `""` (все доступные)  |

**Примеры SDK_LANGUAGES:**
- `""` или не задана — генерируются все доступные SDK (сейчас только PHP)
- `"php"` — только PHP
- `"php,python"` — PHP и Python
- `"php,python,java,javascript"` — все языки

### Переменные подготовки тестового окружения (DMS)

| Переменная         | Описание                                                                 | Значение по умолчанию |
|--------------------|---------------------------------------------------------------------------|------------------------|
| `ENV_TTL_MINUTES`  | Через сколько минут DMS удалит окружение после `env_prepare` (`auto-clean-delay-minutes`) | В `utils_python/client.py` по умолчанию **20** (`DEFAULT_ENV_TTL_MINUTES`). Джоба `deploy-contract-env` (Schemathesis) экспортирует **60**, если переменная не задана в GitLab. |

### Переменные для Schemathesis тестов

Используются для ручных contract-тестов в pipeline `web`. По умолчанию `SCHEMATHESIS_HOST`, `SCHEMATHESIS_LOGIN` и `SCHEMATHESIS_PASSWORD` экспортируются из `deploy-contract-env` / `create-contract-user`; при необходимости их можно переопределить вручную:

| Переменная                    | Описание                      | Пример               |
|-------------------------------|-------------------------------|----------------------|
| `SCHEMATHESIS_HOST`           | URL API сервера               | `host`               |
| `SCHEMATHESIS_LOGIN`          | Логин для Basic auth          | `admin@test_user`    |
| `SCHEMATHESIS_PASSWORD`       | Пароль для Basic auth         | `password123`        |
| `SCHEMATHESIS_PHASES`         | Режимы тестирования           | `examples` (по умолчанию) |
| `SCHEMATHESIS_MAX_EXAMPLES`   | Максимум примеров на эндпоинт | `50` (по умолчанию)  |
| `SCHEMATHESIS_WORKERS`        | Количество parallel workers внутри job | `auto` (по умолчанию) |
| `SCHEMATHESIS_REPEAT`         | Сколько раз подряд повторить прогон (отладка) | `1` (по умолчанию) |
| `SCHEMATHESIS_SEED`           | Seed для `--generation-deterministic` | `1` (по умолчанию) |
| `SCHEMATHESIS_INCLUDE_PATH_REGEX` | Опциональный path-фильтр для ручного targeted coverage | Не задано |
| `SCHEMATHESIS_INCLUDE_METHOD` | Опциональный HTTP method-фильтр для ручного targeted coverage | Не задано |
| `SCHEMATHESIS_INCLUDE_OPERATION_ID` | Опциональный operationId-фильтр для ручной проверки конкретного example | Не задано |


**Режимы тестирования (SCHEMATHESIS_PHASES):**
- `examples` - базовые тесты
- `coverage` - расширенное покрытие
- `fuzzing` - случайные данные
- `stateful` - изменение данных

**По умолчанию:** `examples`, `--mode positive`, `positive_data_acceptance` (example → **2XX**).

`sdk-contract` запускается одной job. Полный `coverage` не входит в общий pipeline,
потому что на сложных схемах документов он генерирует слишком много комбинаций и
упирается в timeout.

**При добавлении или существенном изменении сущности** в спецификации запускайте ручной pipeline (`web`) на ветке с `deploy-contract-env` → `create-contract-user` → `sdk-contract` и задайте targeted `coverage` только для изменённой сущности (подставьте URL-ключ сущности вместо `<keyword>`):

| Переменная | Значение (пример) |
|------------|-------------------|
| `SCHEMATHESIS_PHASES` | `coverage` |
| `SCHEMATHESIS_INCLUDE_PATH_REGEX` | regex для `/entity/<keyword>` (см. bash-блок ниже) |

Опционально сузьте прогон: `SCHEMATHESIS_INCLUDE_METHOD=POST` или `SCHEMATHESIS_INCLUDE_OPERATION_ID=create<Entity>`. Те же переменные работают при локальном `make schemathesis` — см. [README_LOCAL.md](README_LOCAL.md#contract-тесты-schemathesis).

Для ручной точечной проверки новых или изменённых сущностей переопределяйте фазы и фильтры:

```bash
SCHEMATHESIS_PHASES=coverage
SCHEMATHESIS_INCLUDE_PATH_REGEX='^/entity/product(/|$)'
SCHEMATHESIS_INCLUDE_METHOD=POST
```

Для точечной проверки добавленного example удобнее фильтровать по `operationId`:

```bash
SCHEMATHESIS_PHASES=examples
SCHEMATHESIS_INCLUDE_OPERATION_ID=createProduct
```

Для отладки воспроизводимости на одном окружении: `SCHEMATHESIS_REPEAT=2`.

### Переменные для Push/Mirror

| Переменная     | Описание                                                                                                                                |
|----------------|-----------------------------------------------------------------------------------------------------------------------------------------|
| `GIT_PASSWORD` | Токен для доступа к GitHub                                                                                                              |
| `GIT_USER`     | Имя пользователя для git commits                                                                                                        |
| `GIT_MAIL`     | Email для git commits                                                                                                                   |
| `CICD_PAT`     | GitLab token для доступа к внутреннему репозиторию Remap Api Specification (`git.company.lognex/moysklad/misc/remap-api-specification`) |
| `CICD_PAT_PHP` | GitLab token для доступа к внутреннему репозиторию PHP SDK (`git.company.lognex/moysklad/misc/php-remap-1.2-sdk`)                       |

### Переменные для обратной совместимости

| Переменная                              | Описание                                                     |
|-----------------------------------------|--------------------------------------------------------------|
| `SPEC_SDK_GENERATION`                   | `"true"` для нового OpenAPI пайплайна, иначе старый Java SDK |
| `BRANCH`                                | Ветка SDK репозитория (старый пайплайн)                      |
| `API_HOST`, `API_LOGIN`, `API_PASSWORD` | Параметры для старого пайплайна                              |

---

## Stages (стадии)

### Текущие стадии

| Стадия                   | Описание                                                                                                                           |
|--------------------------|------------------------------------------------------------------------------------------------------------------------------------|
| `changes-check`          | Проверка изменений OpenAPI в `src/` относительно последнего тега (теги из текущего репо)                                           |
| `verify`                 | Проверка спецификации, bundling; в ручном pipeline `web` сюда также входят `deploy-contract-env` и `create-contract-user` |
| `contract-test`          | Контрактные тесты Schemathesis (`sdk-contract`) — только в ручном pipeline `web`, после verify, до generate-sdk                    |
| `generate-sdk`           | Генерация SDK                                                                                                                      |
| `test`                   | Тестирование (golden, smoke)                                                                                                       |
| `version`                | Автоматическое версионирование и подготовка CHANGELOG/тегов                                                                        |
| `mirror`                 | Зеркалирование в GitHub и GitHub Release                                                                                           |
| `prepare-sdk-repository` | Подготовка внутреннего репозитория PHP SDK (ветки и релиз мастер‑ветки по текущим изменениям)                                      |

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
  - формат версии semver `MAJOR.MINOR.PATCH` (пример: `1.2.0`);
  - читает Conventional Commits (`feat`, `fix`, `docs`, `feat!`);
- формирует блок изменений для новой версии из сообщений коммитов (`feat`, `fix`, `docs`) и дописывает его в начало `CHANGELOG.md`;
  - если подходящих коммитов нет, в блок версии добавляется строка по умолчанию: **\* технические изменения**;
- создаёт аннотированный тег: **сообщение тега совпадает с блоком изменений** для этой версии (тем же текстом, что записан в `CHANGELOG.md`);
- в сообщение release-коммита добавляется **`[skip ci]`**, чтобы push из `version:auto` не запускал второй пайплайн проверок;
- пушит новый тег и текущую ветку.

При отсутствии тегов versioning стартует с `0.y.0` (MINOR).
