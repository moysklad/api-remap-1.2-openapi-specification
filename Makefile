# Локальный запуск шагов pipeline (аналог GitLab CI)
# Поддерживаемые языки: php, python, java, javascript (сейчас реализован только php)
# Запуск: docker compose run --rm sdk make <target>
# Языки: make generate LANGUAGES=php или LANGUAGES=php,python (по умолчанию php)

LANGUAGES ?= php
# Список языков для генерации/тестов (через запятую без пробелов)
LANGUAGES_LIST := $(subst $(comma), ,$(LANGUAGES))
comma := ,

.PHONY: help lint bundle generate generate-php generate-python generate-java generate-javascript \
	test-smoke test-golden test-golden-php test-golden-java test-golden-javascript test-golden-python \
	schemathesis all

help:
	@echo "Targets (docker compose run --rm sdk make <target>)"
	@echo "  lint              - redocly lint src/openapi.yaml"
	@echo "  bundle            - bundle OpenAPI spec to dist/"
	@echo "  generate          - generate SDK for LANGUAGES (default: php). Example: make generate LANGUAGES=php,python"
	@echo "  generate-php      - generate PHP SDK only"
	@echo "  generate-python   - generate Python SDK only (if script in package.json)"
	@echo "  generate-java     - generate Java SDK only (if script in package.json)"
	@echo "  generate-javascript - generate JavaScript SDK only (if script in package.json)"
	@echo "  test-smoke        - smoke tests (openapi-mock + tests)."
	@echo "  test-golden       - golden tests for LANGUAGES. Default: php"
	@echo "  schemathesis      - contract tests (SCHEMATHESIS_HOST, _LOGIN, _PASSWORD)"
	@echo "  all               - lint + bundle + generate (php) + test-golden + test-smoke"

lint:
	@echo "==> npm ci..."
	sh scripts/npm-ci-public-registry.sh
	@echo "==> lint (redocly validate)..."
	npm run validate

bundle:
	@echo "==> npm ci..."
	sh scripts/npm-ci-public-registry.sh
	@echo "==> bundle OpenAPI spec..."
	npm run bundle
	npm run bundle-json

bundle-test:
	@echo "==> npm ci..."
	sh scripts/npm-ci-public-registry.sh
	@echo "==> bundle OpenAPI spec..."
	npm run bundle-test

# Генерация: все языки из LANGUAGES или по одному
generate: npm-ci
	@for lang in $(LANGUAGES_LIST); do $(MAKE) generate-$$lang || true; done

generate-php:
	npm run generate-php

generate-python:
	@npm run generate-python 2>/dev/null || echo "Skipping generate-python: script not in package.json"

generate-java:
	@npm run generate-java 2>/dev/null || echo "Skipping generate-java: script not in package.json"

generate-javascript:
	@npm run generate-javascript 2>/dev/null || echo "Skipping generate-javascript: script not in package.json"

npm-ci:
	sh scripts/npm-ci-public-registry.sh

# Требует dist/openapi.yaml (сделайте make bundle перед первым запуском)
test-smoke:
	sh scripts/local-test-smoke.sh php

test-golden:
	@for lang in $(LANGUAGES_LIST); do $(MAKE) test-golden-$$lang || true; done

test-golden-php:
	sh scripts/local-test-golden.sh php

test-golden-python:
	sh scripts/local-test-golden.sh python

test-golden-java:
	sh scripts/local-test-golden.sh java

test-golden-javascript:
	sh scripts/local-test-golden.sh javascript

schemathesis:
	sh scripts/local-schemathesis.sh

# Полный прогон: один раз npm ci, затем все шаги (без повторного npm ci)
all:
	@echo "==> [1/6] npm ci..."
	sh scripts/npm-ci-public-registry.sh
	@echo "==> [2/6] lint..."
	npm run validate
	@echo "==> [3/6] bundle..."
	npm run bundle
	npm run bundle-json
	@echo "==> [4/6] generate-php..."
	npm run generate-php
	@echo "==> [5/6] test-golden-php..."
	$(MAKE) test-golden-php
	@echo "==> [6/6] test-smoke..."
	$(MAKE) test-smoke
	@echo "Done: lint, bundle, generate-php, test-golden-php, test-smoke"
