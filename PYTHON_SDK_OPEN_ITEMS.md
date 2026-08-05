# Python SDK: внешние параметры и открытые вопросы

Этот документ содержит только данные, которых нет в репозитории и которые нельзя
безопасно определить из существующих Java/PHP flow.

## Подтверждённые имена и репозитории

- PyPI distribution: `moysklad-remap-12-sdk`.
- Python import package: `moysklad_remap_12_sdk`.
- GitLab: `https://git.company.lognex/moysklad/misc/remap-1.2-python-sdk`.
- GitHub: `https://github.com/moysklad/remap-1.2-python-sdk`.

## Обязательные параметры до включения repository sync

- `CICD_PAT_PYTHON`
  - значение: Project access token с минимальными `write_repository` и `api`;
  - настройка: masked; protected — если один токен используется для release;
  - блокирует: push веток, MR, обновление master и тегов SDK.

## Обязательные параметры до публикации

- Создать отдельные аккаунты на PyPI и TestPyPI, включить 2FA.
- Выполнить первую публикацию, после чего заменить account-scoped bootstrap token
  на project-scoped токены:
  - `TEST_PYPI_API_TOKEN` — masked;
  - `PYPI_API_TOKEN` — masked и protected.
- Ограничить GitLab environment `pypi-production` разрешёнными deployers.
- Решить, заменяются ли токены на Trusted Publishing. Для self-managed GitLab
  требуется отдельный onboarding PyPI; текущий pipeline реализует безопасный
  fallback с project-scoped token.

## Репозитории и зеркало

- В GitLab SDK-проекте настроить односторонний push mirror в GitHub для `master`
  и тегов. GitHub не должен быть источником обратной синхронизации.
- Добавить deploy key/token зеркала согласно внутреннему туториалу `ATK-115`.

## Release policy, которую нужно утвердить

- версия Python SDK равна semver-тегу спецификации;
- production upload выполняется только после `merge-branch-python`;
- один раз загруженная версия не перезаписывается;
- исправление выпускается новой patch-версией, проблемный релиз помечается
  `yanked`, а не удаляется;
- определить список пользователей, которым разрешён manual release.

## Документация вне этого репозитория

- Нужен путь или репозиторий исходников dev-портала.
- Нужен владелец публикации страницы Python SDK.
- На странице должны быть: `pip install`, Basic/Bearer auth, минимальный пример,
  поддерживаемые Python 3.10–3.14 и ссылки на PyPI/GitHub.
