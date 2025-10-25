<<<<<<< HEAD
# Remap 1.2 SDK deployer

Проект для сборки SDK Remap 1.2.
Проект поддерживает команда АПИ.

#### Зачем нужен Remap 1.2 SDK deployer ?
Для сборки новых версий Remap 1.2 SDK.

#### Где находится проект SDK?
Проект SDK располагаются в нашем [GitHub-репозитории](https://github.com/moysklad/java-remap-sdk-1.2).

#### Версионирование
* при тестировании из ветки, артефакту назначается версия по номеру тикета и пайплайна `ветка`-`номер пайплайна`-SNAPSHOT
* при релизе выставляется версия из pom.xml, затем увеличивается минорная версия (`1.1-SNAPSHOT` выпускается как `1.1-RELEASE` с изменением версии на `1.2-SNAPSHOT`)
* в коммитах, ломающих обратную совместимость, необходимо изменять мажорную версию в pom.xml (`2.4-SNAPSHOT` -> `3.0-SNAPSHOT`)


### Сборка из ветки для тестирования
* задеплоить изменения в основном проекте в тестовое окружение
* создать пользователя в тестовом окружении из шага 1 (для api-1 [https://online-api-1.testms-test.lognex.ru](https://online-api-1.testms-test.lognex.ru/app/))
* выдать пользователю корпоративный аккаунт, добавить ему дополнительно опцию с 15-ю точками продаж и подключить опцию "Управление производством" (для api-1 [https://admin-ms-api-1.test-okd.infra.lognex](https://admin-ms-api-1.test-okd.infra.lognex))
* запустить новый пайплайн Remap 1.2 SDK deployer, указав:
    1. ветку пайплайна master - проект нужен только для сборки;
    2. параметр **BRANCH** - имя ветки в репозитории SDK. Ветка должна начинаться на *MC-* или быть *master*;
    3. параметр **API_HOST** - адрес хоста, на котором будут проходить тесты. Пример: `https://api-api-1.testms-test.lognex.ru`;
    4. параметр **API_LOGIN** - логин аккаунта для тестов. Для успешного прохождения тестов у указанного аккаунта должна быть доступна работа с пользовательскими справочниками и должно быть несколько точек продаж (для этого можно сделать у пользователя тариф корпоративный).  Пример: `admin@test123`;
    5. параметр **API_PASSWORD** - пароль от аккаунта для тестов.

[Ссылка на предзаполненный пайплайн](https://git.company.lognex/moysklad/misc/api-sdk-builder/-/pipelines/new?ref=master&var[BRANCH]=MC-&var[API_HOST]=https://api-api-1.testms-test.lognex.ru&var[API_LOGIN]=admin@test_user&var[API_PASSWORD]=123123)


### Релиз в публичный maven репозиторий после ревью
Релиз нужно делать заранее, так как артефакт может стать доступным в mvnrepository далеко не сразу.
* создать тестового пользователя с корпоративным аккаунтом, добавить ему дополнительно опцию с 15-ю точками продаж и подключить опцию "Управление производством"
* запустить новый пайплайн Remap 1.2 SDK deployer, указав:
  1. ветку пайплайна master - проект нужен только для сборки;
  2. параметр **BRANCH** - *release* (зарезервированное название для релизов);
  3. параметр **API_HOST** - адрес хоста, на котором будут проходить тесты. Пример: `https://api-api-1.testms-test.lognex.ru`;
  4. параметр **API_LOGIN** - логин аккаунта для тестов. Для успешного прохождения тестов у указанного аккаунта должна быть доступна работа с пользовательскими справочниками и должно быть несколько точек продаж (для этого можно сделать у пользователя тариф корпоративный).  Пример: `admin@test123`;
  5. параметр **API_PASSWORD** - пароль от аккаунта для тестов.
* тегнуть team-api-group в канале team-api, чтобы ответственный завершил релиз через https://central.sonatype.com/publishing/deployments

[Ссылка на предзаполненный релизный пайплайн](https://git.company.lognex/moysklad/misc/api-sdk-builder/-/pipelines/new?ref=master&var[BRANCH]=release&var[API_HOST]=https://api-api-1.testms-test.lognex.ru&&var[API_LOGIN]=admin@test_user&var[API_PASSWORD]=123123)

#### Действия после релиза
После завершения релиза необходимо описать изменения релиза в github:
- [Создать новый github релиз](https://github.com/moysklad/java-remap-1.2-sdk/releases/new)
- Указать в tag version последний тэг со [страницы тегов](https://github.com/moysklad/java-remap-1.2-sdk/tags) (появится надпись `Existing tag`)
- Указать в `Release title` и `Describe this release` изменения
=======
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
>>>>>>> github/master
