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
* выдать пользователю корпоративный аккаунт (для api-1 [https://admin-api-1.testms-test.lognex.ru](https://admin-api-1.testms-test.lognex.ru/))
* запустить новый пайплайн Remap 1.2 SDK deployer, указав:
    1. ветку пайплайна master - проект нужен только для сборки;
    2. параметр **BRANCH** - имя ветки в репозитории SDK. Ветка должна начинаться на *MC-* или быть *master*;
    3. параметр **API_HOST** - адрес хоста, на котором будут проходить тесты. Пример: `https://online-api-1.testms-test.lognex.ru`;
    4. параметр **API_LOGIN** - логин аккаунта для тестов. Для успешного прохождения тестов у указанного аккаунта должна быть доступна работа с пользовательскими справочниками и должно быть несколько точек продаж (для этого можно сделать у пользователя тариф корпоративный).  Пример: `admin@test123`;
    5. параметр **API_PASSWORD** - пароль от аккаунта для тестов.

[Ссылка на предзаполненный пайплайн](https://git.moysklad.ru/moysklad/misc/api-sdk-builder/-/pipelines/new?ref=master&var[BRANCH]=MC-&var[API_HOST]=https://online-api-1.testms-test.lognex.ru&var[API_LOGIN]=admin@test_user&var[API_PASSWORD]=123123)


### Релиз в публичный maven репозиторий после ревью
* создать тестового пользователя с корпоративным аккаунтом
* запустить новый пайплайн Remap 1.2 SDK deployer, указав:
  1. ветку пайплайна master - проект нужен только для сборки;
  2. параметр **BRANCH** - *release* (зарезервированное название для релизов);
  3. параметр **API_HOST** - адрес хоста, на котором будут проходить тесты. Пример: `https://online-api-1.testms-test.lognex.ru`;
  4. параметр **API_LOGIN** - логин аккаунта для тестов. Для успешного прохождения тестов у указанного аккаунта должна быть доступна работа с пользовательскими справочниками и должно быть несколько точек продаж (для этого можно сделать у пользователя тариф корпоративный).  Пример: `admin@test123`;
  5. параметр **API_PASSWORD** - пароль от аккаунта для тестов.

[Ссылка на предзаполненный релизный пайплайн](https://git.moysklad.ru/moysklad/misc/api-sdk-builder/-/pipelines/new?ref=master&var[BRANCH]=release&var[API_HOST]=https://online-api-1.testms-test.lognex.ru&&var[API_LOGIN]=admin@test_user&var[API_PASSWORD]=123123)

#### Действия после релиза
После завершения релиза необходимо описать изменения релиза в github:
- [Создать новый github релиз](https://github.com/moysklad/java-remap-1.2-sdk/releases/new)
- Указать в tag version последний тэг со [страницы тегов](https://github.com/moysklad/java-remap-1.2-sdk/tags) (появится надпись `Existing tag`)
- Указать в `Release title` и `Describe this release` изменения
