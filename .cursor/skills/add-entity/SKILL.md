---
name: add-entity
description: Add a new API entity to the MoySklad OpenAPI specification. Use when the user asks to add a new entity, resource, or endpoint to the spec, or when working with _*.md description files from api-remap-1.2-doc to generate YAML schemas, paths, tests, and fixtures.
---

# Adding a new entity to the OpenAPI specification

## Scope

Use this skill when adding a MoySklad API entity, resource, endpoint group, schema, fixture, or smoke/golden coverage from `api-remap-1.2-doc/md/**/_*.md`.

The MD file is the source of truth for field names, types, endpoint sections, required create/update payloads, and JSON examples. Existing entities in this repository are the source of truth for local OpenAPI style and test patterns.

## Entity classification

| Category | Path prefix in spec | MD source folder | Has positions |
|----------|-------------------|------------------|---------------|
| **Dictionary** (справочник) | `paths/dictionaries/<entities>/` | `md/dictionaries/` | No |
| **Document** (документ) | `paths/documents/<entities>/` | `md/documents/` | Usually yes |

## Stop and ask

Ask before editing if any of these are unclear:

- No source MD file was provided and there is no obvious `api-remap-1.2-doc/md/**/_<entity>.md`.
- The API keyword, plural folder name, or schema name cannot be derived confidently from MD and peers.
- The MD endpoint set contradicts the closest existing peer and the difference is not explained by the MD.
- A referenced entity is missing and creating a public stub would be a visible API modeling decision.

## Workflow

Follow this order. Read [reference.md](reference.md) for templates and edge-case rules, and [example.md](example.md) for dictionary and document examples.

1. **Parse MD** — extract entity names, field tables, nested tables, endpoint sections, required request bodies, and richest JSON examples.
2. **Choose peers** — compare with 1-2 existing entities of the same class before creating files. Prefer peers with the same special features: metadata states, positions, files/images, accounts, notes, store balances.
3. **Build an endpoint matrix** — map every MD operation to a path file + HTTP method using the template below. Missing path files usually mean a missed MD section.
4. **Check dependencies** — for each `[Meta]` field, verify the target exists in `src/openapi.yaml` `components.schemas`; expand stubs or create minimal stubs only when safe.
5. **Create schemas** — entity + list schemas; for documents with positions also position + position list schemas. For every schema that has a top-level `meta`, add `x-entity-static-builder` (see "Static builder extension" below).
6. **Create paths** — one YAML per endpoint group. Reference schemas through `../../../openapi.yaml#/components/schemas/<SchemaName>` from request/response bodies.
7. **Register in `src/openapi.yaml`** — paths, `components.schemas`, and tags in the local style used nearby.
8. **Add test data** — create a rich shared fixture in `tests/fixtures/<snake_case>.json`, register it in both PHP and Java `FIXTURE_MODEL_MAP`, and update `IGNORED_FIELDS` only when required.
9. **Add smoke coverage** — one test method per endpoint+method from the endpoint matrix.
10. **Cross-check** — re-read the MD and verify fields, endpoints, refs, nullable values, enums, fixtures, smoke tests, and `x-entity-static-builder` presence on every schema with `meta`.
11. **Verify** — run the Docker make sequence below.
12. **Report** — mention any stubs created or left unexpanded, intentionally skipped MD fields, and checks run.

## Endpoint matrix quick template

Before creating paths, produce and keep an internal matrix like this:

| MD section | API path | HTTP | Path file | Smoke test |
|------------|----------|------|-----------|------------|
| `### Получить ...` | `/entity/<keyword>` | GET | `<entities>.yaml` | `testList<PascalPlural>` |
| `### Создать ...` | `/entity/<keyword>` | POST | `<entities>.yaml` | `testCreate<PascalSingular>` |
| `### Получить ... по ID` | `/entity/<keyword>/{id}` | GET | `<entity>-by-id.yaml` | `testGet<PascalSingular>ById` |
| `### Изменить ...` | `/entity/<keyword>/{id}` | PUT | `<entity>-by-id.yaml` | `testUpdate<PascalSingular>` |
| `### Удалить ...` | `/entity/<keyword>/{id}` | DELETE | `<entity>-by-id.yaml` | `testDelete<PascalSingular>` |

Add rows for batch, metadata, attributes, states, positions, files/images, accounts, notes, storebalances, or other MD-specific endpoint groups. Detailed extraction and classification rules are in [reference.md](reference.md).

## Missing dependency entities

When the new entity references another entity via `[Meta]` / `$ref`, that target entity must exist in `components.schemas` of `openapi.yaml`. Check **before** writing `$ref`:

| Situation | Action |
|-----------|--------|
| Target exists with full schema (has properties beyond `meta`/`id`/`accountId`) | Use `$ref` as-is — no extra work |
| Target exists as a **stub** (only `meta`/`id`/`accountId`) | **Expand the stub** with fields from its `_<target>.md`; no new paths/tests needed now unless user requests |
| Target is **not registered** in `components.schemas` at all | **Create a stub** schema (`meta` + `id` + `accountId`), register in `openapi.yaml`; optionally expand later |

### Rules

1. **Always check** — before creating the entity, scan all `[Meta]` fields in the MD attribute table and verify each target is in `openapi.yaml` `components.schemas`.
2. **Stub is acceptable** — a `$ref` only needs the target to exist as a schema; it does not need endpoints or tests to pass lint/bundle/generate. Stubs contain at minimum `meta`, `id`, `accountId`.
3. **Expand vs stub** — if the user explicitly asks to add the full entity, expand it fully (all steps). Otherwise a stub is enough to unblock the current entity.
4. **Report to user** — after the entity is done, list any stubs that were created or left unexpanded so the user knows what to finish later.

For detailed stub template, see [reference.md](reference.md).

## Cross-check essentials

Before verification, re-read the MD and confirm:

- Every `#### Атрибуты сущности` row has a schema property, unless explicitly skipped and reported.
- `+Только для чтения`, nullable values, enum/open string choices, `String(N)`, and money/weight numeric formats are represented correctly.
- Nested object structures match MD JSON examples exactly.
- Every API operation section maps to a path + method and a smoke test.
- Metadata attributes and `metadata/states/{id}` exist when the MD metadata section requires them.
- Every schema with a top-level `meta` field carries `x-entity-static-builder` (entity → keyword + `id`; position → `parentId` + `id` + `<keyword>position` type).
- The fixture uses the richest single-entity GET response, not a minimal create request.

## Static builder extension (`x-entity-static-builder`)

Custom OpenAPI vendor extension consumed by `customtemplates/php/model_entity_static_builder.mustache` and `customtemplates/java/model_entity_static_builder.mustache` to generate a static `createWithMeta(...)` helper on PHP and Java SDK models. Required on **every** schema that has a top-level `meta`, including stubs.

Conventions for this project:

| Schema kind | `methodParams` | `href` sequence | `type` |
|-------------|----------------|-----------------|--------|
| Entity (dictionary or document) | `["id"]` | `path: "entity"` → `path: "<keyword>"` → `param: "id"` | `"<keyword>"` |
| Position (document) | `["parentId", "id"]` | `path: "entity"` → `path: "<keyword>"` → `param: "parentId"` → `path: "positions"` → `param: "id"` | `"<keyword>position"` |

`<keyword>` is the lowercase URL keyword from MD (matches `meta.type` returned by the API). Place the block at the top of the schema, between `description` and `properties`. Detailed template and rules are in [reference.md](reference.md); peer references: `src/components/schemas/dictionary/customerOrder.yaml`, `src/components/schemas/dictionary/customerOrderPosition.yaml`. Background documentation lives in `src/custom-extension-readme.md`, `customtemplates/php/readme.md`, and `customtemplates/java/readme.md`.

## Enum fields and SDK compatibility

For enum-like values in entity fields, keep the entity field as unrestricted `type: string` and add a separate PascalCase component with the known enum values for SDK constants. Do **not** put `enum` inline on the entity field and do **not** `$ref` the enum component from that field, or SDKs may generate strict enum validation again. Detailed rules and templates are in [reference.md](reference.md).

## Polymorphic list rows (mixed `items`)

If list `rows` contain different entity shapes with overlapping schemas, prefer the existing `anyOf` pattern over `oneOf` unless the branches can be made mutually exclusive without breaking PHP codegen. Example: `src/components/schemas/dictionary/discountList.yaml`. Details are in [reference.md](reference.md).

## Attribute polymorphism (required project pattern)

When an entity contains `attributes`, keep the current `AttributeAbstract` + discriminator + `AttributeBase` pattern from `src/openapi.yaml`. Do not convert it to `oneOf` without explicit need. If adding a new attribute subtype, update discriminator mapping, schema registration, and fixture/golden coverage together. Details are in [reference.md](reference.md).

## Verification

Use Docker make targets for verification. Do not use `npm run` directly for the final check.

```bash
docker compose run --rm sdk make lint           # Redocly lint
docker compose run --rm sdk make bundle         # produces dist/openapi.yaml + dist/openapi.json
docker compose run --rm sdk make generate-php   # generates PHP SDK in clients/php/
docker compose run --rm sdk make generate-java  # generates Java SDK in clients/java/
docker compose restart mock                     # CRITICAL: reload bundled spec in mock server
docker compose run --rm sdk make test-golden-php
docker compose run --rm java-sdk make test-golden-java
docker compose run --rm sdk make test-smoke
```

`test-smoke` is the canonical local smoke target.

## Naming conventions

| Context | Convention | Example (Contract) |
|---------|-----------|---------|
| Schema file | camelCase | `contract.yaml` |
| List schema file | camelCase + `List` | `contractList.yaml` |
| Position schema file | camelCase + `Position` | `customerOrderPosition.yaml` |
| Component name in openapi.yaml | PascalCase | `Contract` |
| Path folder | lowercase plural | `contracts/` |
| Path files | kebab-case | `contract-by-id.yaml` |
| URL in openapi.yaml paths | lowercase keyword from MD | `/entity/contract` |
| Fixture file | snake_case | `contract.json` |
| Golden map key | snake_case | `'contract' => 'Contract'` |
| Tag name | PascalCase plural | `Contracts` |
| operationId | camelCase verb+Entity | `getContracts`, `createContract` |

## MD → YAML field mapping

For detailed rules on converting MD table rows to YAML properties, see [reference.md](reference.md).

## Additional resources

- For detailed templates, `$ref` patterns, and field-type mapping rules, see [reference.md](reference.md)
- For worked examples (dictionary `Contract`, document with positions), see [example.md](example.md)
