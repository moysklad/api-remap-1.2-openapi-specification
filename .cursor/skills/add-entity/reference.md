# Reference — Adding entities to the OpenAPI spec

## 1. Parse MD description

The `_<entity>.md` file contains:

- **Attribute table** (`#### Атрибуты сущности`) — each row has: Name, Type, Filtration, Description
- **Nested entity tables** (`#### Атрибуты вложенных сущностей`) — sub-objects
- **Endpoint sections** (`### Получить`, `### Создать`, `### Удалить`, `### Изменить`, `### Массовое...`) — each has curl example + JSON response
- **Position table** (documents only, `#### Позиции`) — line-item fields

Extract from MD:
1. Field list with types, readOnly/required markers, descriptions
2. Which endpoints exist (GET list, GET by id, POST create, PUT update, DELETE, POST batch, POST delete)
3. JSON response examples (use for fixtures)

## 1a. Endpoint matrix builder

Before creating path files, build an endpoint matrix from the MD. This is the main guard against silently missing Remap 1.2 operations.

### Extract candidate sections

Use heading search on the source MD:

```bash
rg '^### ' ~/repos/api-remap-1.2-doc/md/<folder>/_<entity>.md
```

Keep only API operation sections. Ignore narrative sections that do not describe a request/response flow, but keep `### Метаданные`, `### Позиции`, `### Массовое ...`, and subtype-specific endpoint groups.

### Matrix columns

Track these columns while implementing:

| Column | Meaning |
|--------|---------|
| `MD section` | Exact `### ...` heading from the MD |
| `API path` | URL path from the curl/example, without server prefix |
| `HTTP` | Method from the MD curl/example |
| `Path file` | YAML file that must contain the operation |
| `operationId` | Expected operationId in local naming style |
| `Smoke test` | Test method that should call this endpoint |
| `Notes` | Required body refs, special query params, empty 404, peer differences |

### Common mapping

| MD signal | API path | HTTP | Path file |
|-----------|----------|------|-----------|
| list section | `/entity/<keyword>` | GET | `<entities>.yaml` |
| create section | `/entity/<keyword>` | POST | `<entities>.yaml` |
| get by ID section | `/entity/<keyword>/{id}` | GET | `<entity>-by-id.yaml` |
| update section | `/entity/<keyword>/{id}` | PUT | `<entity>-by-id.yaml` |
| delete by ID section | `/entity/<keyword>/{id}` | DELETE | `<entity>-by-id.yaml` |
| mass create/update | `/entity/<keyword>/batch` | POST | `<entities>-batch.yaml` |
| mass delete | `/entity/<keyword>/delete` | POST | `<entities>-delete.yaml` |
| metadata | `/entity/<keyword>/metadata` | GET | `<entity>-metadata.yaml` or shared metadata path |
| metadata attributes list/create | `/entity/<keyword>/metadata/attributes` | GET/POST | `<entity>-metadata-attribute.yaml` |
| metadata attribute by ID | `/entity/<keyword>/metadata/attributes/{id}` | GET/PUT/DELETE | `<entity>-metadata-attribute-by-id.yaml` |
| metadata states by ID | `/entity/<keyword>/metadata/states/{id}` | GET/PUT/DELETE | `<entity>-metadata-state-by-id.yaml` |
| positions list/create | `/entity/<keyword>/{id}/positions` | GET/POST | `<entity>-positions.yaml` |
| position by ID | `/entity/<keyword>/{id}/positions/{positionId}` | GET/PUT/DELETE | `<entity>-position-by-id.yaml` |
| positions batch delete | `/entity/<keyword>/{id}/positions/delete` | POST | `<entity>-positions-delete.yaml` |

### Remap-specific traps

- Do not infer metadata states from the word "статус" alone; require `states` in metadata response/examples or a peer-backed pattern.
- Some dictionaries/documents have extra groups (`files`, `images`, `accounts`, `notes`, `storebalances`, security/access actions). Add matrix rows from the exact MD headings and copy the closest peer's file split.
- Batch operation names in MD may say "Массовое создание и обновление"; model it as `/batch` POST with array response.
- DELETE metadata state endpoints should include explicit `404: NotFoundEmpty` when the backend can return empty 404.
- If a path exists in MD but is intentionally skipped, record the reason in the final report.

### Completion check

Before verification, every matrix row must have:

1. A registered path in `src/openapi.yaml`
2. A path YAML operation with matching HTTP method
3. Request/response schemas using `../../../openapi.yaml#/components/schemas/<SchemaName>` where applicable
4. A smoke test method, unless explicitly skipped and reported
5. A peer comparison note for any non-standard operation

## 2. MD → YAML type mapping

| MD Type | YAML type + format | Notes |
|---------|-------------------|-------|
| `UUID` | `type: string`, `format: uuid` | |
| `String(N)` | `type: string`, `maxLength: N` | |
| `String` | `type: string` | |
| `Int` | `type: integer` | Add `minimum`/`maximum` if documented |
| `Float` | `type: number`, `format: float` | For monetary values use `format: double` |
| `Boolean` | `type: boolean` | |
| `DateTime` | `type: string` | Do NOT set `format: date-time` — API uses non-standard format `YYYY-MM-DD HH:MM:SS.mmm` |
| `Object` | `type: object` with inline `properties` | Or `$ref` if it matches a known schema |
| `Array(Object)` | `type: array` with `items` | |
| `Array(String)` | `type: array`, `items: { type: string }` | |
| `Enum` | Open `type: string` field + separate enum component | See "Enum fields and SDK compatibility" below |
| `[Meta]` | Reference to another entity | See `$ref` patterns below |
| `MetaArray` | Embedded list with meta | Use `allOf` + `$ref` to `*List` schema |

### MD description markers → YAML attributes

| Marker in MD description | YAML property |
|-------------------------|---------------|
| `+Только для чтения` | `readOnly: true` |
| `+Обязательное при ответе` | (informational, no YAML effect) |
| `+Необходимо при создании` | (informational, no YAML effect — `required` is rarely set at object level in this spec) |

### Nullable fields

If a field can be `null` (explicit in MD or from JSON examples), add `nullable: true`.

### Enum fields and SDK compatibility

For enum-like API values in entity fields, model the entity property as an open string and create a separate component with known values:

```yaml
rateUpdateType:
  type: string
  description: Способ обновления курса валюты. Известные значения описаны в RateUpdateType
  example: "manual"
```

```yaml
RateUpdateType:
  $ref: './components/schemas/dictionary/rateUpdateType.yaml'
```

```yaml
type: string
description: Известные значения способа обновления курса валюты
enum: [manual, auto]
example: "manual"
```

Rules:

1. Name the separate component like the field in PascalCase (`rateUpdateType` → `RateUpdateType`).
2. Keep the component as a standalone `components.schemas` entry so SDKs can generate constants or enum helpers.
3. Do **not** reference the component from the entity field with `$ref`; that can reintroduce strict enum validation for the property.
4. Do **not** put `enum` inline on the entity field.
5. Use JSON values from the MD enum mapping table, not Russian labels.
6. Mention the separate component in the field `description` so API docs still point users to known values.

## 3. `$ref` patterns

### Direct reference (non-nullable linked entity)
```yaml
group:
  description: Метаданные отдела сотрудника
  $ref: '../../../openapi.yaml#/components/schemas/Group'
```

### Nullable reference (via allOf)
```yaml
owner:
  description: Метаданные владельца (Сотрудника)
  nullable: true
  type: object
  allOf:
    - $ref: '../../../openapi.yaml#/components/schemas/Employee'
```

### Array of references
```yaml
attributes:
  type: array
  description: Коллекция доп. полей
  items:
    $ref: '../../../openapi.yaml#/components/schemas/AttributeAbstract'
```

### MetaArray (files, images)
```yaml
files:
  description: Метаданные массива Файлов
  nullable: true
  type: object
  allOf:
    - $ref: '../../../openapi.yaml#/components/schemas/FileList'
```

### Positions (documents only)
```yaml
positions:
  type: object
  description: Позиции документа
  $ref: '../../../openapi.yaml#/components/schemas/CustomerOrderPositionList'
```

## 4. Handling missing dependency entities

Before writing any `$ref`, check that the target schema exists in `src/openapi.yaml` → `components.schemas`.

### How to check

```bash
# Example: does Organization exist?
rg '^    Organization:' src/openapi.yaml
# If found → check the schema file has more than meta/id/accountId
```

### Stub schema template

If a dependency is completely missing, create a minimal stub file and register it:

**File:** `src/components/schemas/dictionary/<dependency>.yaml`
```yaml
type: object
description: <Название сущности>
properties:
  meta:
    description: Метаданные <сущности>
    $ref: '../common/meta.yaml'
  id:
    type: string
    format: uuid
    description: ID <сущности>
    readOnly: true
  accountId:
    type: string
    format: uuid
    description: ID учетной записи
    readOnly: true
```

**Register in `openapi.yaml`:**
```yaml
    <PascalName>:
      $ref: './components/schemas/dictionary/<camelCase>.yaml'
```

### When to expand a stub

- **Now** — if the user explicitly asks for the dependency entity, or if it has its own `_<entity>.md` and the user wants it fully added
- **Later** — if the dependency is only used as a reference (e.g. `Organization` referenced by `Contract`). A stub is enough for lint/bundle/generate to pass. Report the stub to the user in the summary.

### Detecting stubs vs full schemas

A schema is a stub if it has only `meta`, `id`, `accountId` properties (≤3 properties, no business fields). When expanding a stub, follow the full checklist for that entity.

## 5. Schema templates

### Entity schema (`<entity>.yaml`)

```yaml
type: object
properties:
  meta:
    description: Метаданные <Сущности>
    $ref: '../common/meta.yaml'
  id:
    type: string
    format: uuid
    description: ID <Сущности>
    readOnly: true
  accountId:
    type: string
    format: uuid
    description: ID учетной записи
    readOnly: true
  # ... other fields from MD attribute table ...
  updated:
    type: string
    description: Момент последнего обновления сущности
    readOnly: true
```

### List schema (`<entity>List.yaml`)

```yaml
type: object
description: Список <сущностей>
properties:
  context:
    description: Метаданные о сотруднике, выполнившем запрос
    $ref: '../common/context.yaml'
  meta:
    description: Метаданные о выдаче
    $ref: '../common/metaList.yaml'
  rows:
    type: array
    description: Массив <сущностей>
    items:
      $ref: '../../../openapi.yaml#/components/schemas/<PascalCaseName>'
```

### Position schema (documents only, `<entity>Position.yaml`)

```yaml
type: object
properties:
  id:
    type: string
    format: uuid
    description: ID позиции
    readOnly: true
  accountId:
    type: string
    format: uuid
    description: ID учетной записи
    readOnly: true
  # ... fields from MD position table ...
  assortment:
    description: Метаданные товара/услуги
    $ref: '../../../openapi.yaml#/components/schemas/Assortment'
```

## 6. Path file templates

### Collection (GET list + POST create) — `<entities>.yaml`

```yaml
get:
  operationId: get<PascalPlural>
  tags:
    - <PascalPlural>
  summary: Получить список <сущностей>
  description: Запрос всех <сущностей> на данной учетной записи
  parameters:
    - $ref: '../../../components/parameters.yaml#/limit'
    - $ref: '../../../components/parameters.yaml#/offset'
    - $ref: '../../../components/parameters.yaml#/search'    # only if MD says search is supported
    - $ref: '../../../components/parameters.yaml#/filter'
    - $ref: '../../../components/parameters.yaml#/expand'
    - $ref: '../../../components/parameters.yaml#/order'
    - $ref: '../../../components/headers.yaml#/AcceptHeader'
    - $ref: '../../../components/headers.yaml#/AcceptEncoding'
  responses:
    '200':
      description: Успешный запрос
      content:
        application/json:
          schema:
            $ref: '../../../openapi.yaml#/components/schemas/<PascalSingular>List'
    default:
      $ref: '../../../components/responses.yaml#/CommonError'

post:
  operationId: create<PascalSingular>
  tags:
    - <PascalPlural>
  summary: Создать <сущность>
  description: Создание <сущности>
  parameters:
    - $ref: '../../../components/parameters.yaml#/expand'
    - $ref: '../../../components/headers.yaml#/AcceptHeader'
    - $ref: '../../../components/headers.yaml#/AcceptEncoding'
    - $ref: '../../../components/headers.yaml#/ContentTypeJson'
  requestBody:
    required: true
    content:
      application/json:
        schema:
          $ref: '../../../openapi.yaml#/components/schemas/<PascalSingular>'
  responses:
    '200':
      description: <Сущность> успешно создана
      content:
        application/json:
          schema:
            $ref: '../../../openapi.yaml#/components/schemas/<PascalSingular>'
    default:
      $ref: '../../../components/responses.yaml#/CommonError'
```

### By ID (GET + PUT + DELETE) — `<entity>-by-id.yaml`

```yaml
get:
  operationId: get<PascalSingular>ById
  tags:
    - <PascalPlural>
  summary: Получить <сущность> по ID
  description: Запрос <сущности> с указанным id
  parameters:
    - $ref: '../../../components/parameters.yaml#/entityId'
    - $ref: '../../../components/parameters.yaml#/expand'
    - $ref: '../../../components/headers.yaml#/AcceptHeader'
    - $ref: '../../../components/headers.yaml#/AcceptEncoding'
  responses:
    '200':
      description: Успешный запрос
      content:
        application/json:
          schema:
            $ref: '../../../openapi.yaml#/components/schemas/<PascalSingular>'
    default:
      $ref: '../../../components/responses.yaml#/CommonError'

put:
  operationId: update<PascalSingular>
  tags:
    - <PascalPlural>
  summary: Обновить <сущность>
  description: Обновление <сущности> с указанным id
  parameters:
    - $ref: '../../../components/parameters.yaml#/entityId'
    - $ref: '../../../components/parameters.yaml#/expand'
    - $ref: '../../../components/headers.yaml#/AcceptHeader'
    - $ref: '../../../components/headers.yaml#/AcceptEncoding'
    - $ref: '../../../components/headers.yaml#/ContentTypeJson'
  requestBody:
    required: true
    content:
      application/json:
        schema:
          $ref: '../../../openapi.yaml#/components/schemas/<PascalSingular>'
  responses:
    '200':
      description: <Сущность> успешно обновлена
      content:
        application/json:
          schema:
            $ref: '../../../openapi.yaml#/components/schemas/<PascalSingular>'
    default:
      $ref: '../../../components/responses.yaml#/CommonError'

delete:
  operationId: delete<PascalSingular>
  tags:
    - <PascalPlural>
  summary: Удалить <сущность>
  description: Удаление <сущности> с указанным id
  parameters:
    - $ref: '../../../components/parameters.yaml#/entityId'
    - $ref: '../../../components/headers.yaml#/AcceptHeader'
    - $ref: '../../../components/headers.yaml#/AcceptEncoding'
  responses:
    '200':
      description: <Сущность> успешно удалена
    default:
      $ref: '../../../components/responses.yaml#/CommonError'
```

### Batch delete — `<entities>-delete.yaml`

```yaml
post:
  operationId: delete<PascalPlural>Batch
  tags:
    - <PascalPlural>
  summary: Удалить <сущности>
  description: Массовое удаление <сущностей> по их мета-объектам.
  parameters:
    - $ref: '../../../components/headers.yaml#/AcceptHeader'
    - $ref: '../../../components/headers.yaml#/AcceptEncoding'
    - $ref: '../../../components/headers.yaml#/ContentTypeJson'
  requestBody:
    required: true
    content:
      application/json:
        schema:
          type: array
          items:
            $ref: '../../../openapi.yaml#/components/schemas/<PascalSingular>'
          minItems: 1
          maxItems: 1000
  responses:
    '200':
      description: Результат по каждому элементу (успех или объект ошибки)
      content:
        application/json:
          schema:
            type: array
            items:
              oneOf:
                - $ref: '../../../openapi.yaml#/components/schemas/DeleteInfo'
                - $ref: '../../../openapi.yaml#/components/schemas/Error'
            minItems: 1
            maxItems: 1000
    default:
      $ref: '../../../components/responses.yaml#/CommonError'
```

### Batch create/update — `<entities>-batch.yaml`

```yaml
post:
  operationId: create<PascalPlural>Batch
  tags:
    - <PascalPlural>
  summary: Создать или изменить <сущности>
  description: Создание или изменение нескольких <сущностей>.
  parameters:
    - $ref: '../../../components/parameters.yaml#/expand'
    - $ref: '../../../components/headers.yaml#/AcceptHeader'
    - $ref: '../../../components/headers.yaml#/AcceptEncoding'
    - $ref: '../../../components/headers.yaml#/ContentTypeJson'
  requestBody:
    required: true
    content:
      application/json:
        schema:
          type: array
          items:
            $ref: '../../../openapi.yaml#/components/schemas/<PascalSingular>'
          minItems: 1
          maxItems: 1000
  responses:
    '200':
      description: <Сущности> успешно созданы или изменены
      content:
        application/json:
          schema:
            type: array
            items:
              oneOf:
                - $ref: '../../../openapi.yaml#/components/schemas/<PascalSingular>'
                - $ref: '../../../openapi.yaml#/components/schemas/Error'
            minItems: 1
            maxItems: 1000
    default:
      $ref: '../../../components/responses.yaml#/CommonError'
```

### Metadata state by ID (GET/PUT/DELETE) — `<entity>-metadata-state-by-id.yaml`

**When to create:** if the MD metadata section (`### Метаданные`) includes a `states` field (array of statuses). Check existing peer entities (e.g. `customerorder-metadata-state-by-id.yaml`) to confirm the pattern.

```yaml
get:
  operationId: get<PascalSingular>MetadataStateById
  tags:
    - <PascalPlural>
  summary: Отдельный статус <сущности>
  parameters:
    - $ref: '../../../components/parameters.yaml#/entityId'
    - $ref: '../../../components/headers.yaml#/AcceptHeader'
    - $ref: '../../../components/headers.yaml#/AcceptEncoding'
    - $ref: '../../../components/headers.yaml#/ContentTypeJson'
  responses:
    '200':
      description: Успешный запрос
      content:
        application/json:
          schema:
            $ref: '../../../openapi.yaml#/components/schemas/State'
    default:
      $ref: '../../../components/responses.yaml#/CommonError'
put:
  operationId: update<PascalSingular>MetadataStateById
  tags:
    - <PascalPlural>
  summary: Обновить отдельный статус <сущности>
  parameters:
    - $ref: '../../../components/parameters.yaml#/entityId'
    - $ref: '../../../components/headers.yaml#/AcceptHeader'
    - $ref: '../../../components/headers.yaml#/AcceptEncoding'
    - $ref: '../../../components/headers.yaml#/ContentTypeJson'
  responses:
    '200':
      description: Успешный запрос
      content:
        application/json:
          schema:
            $ref: '../../../openapi.yaml#/components/schemas/State'
    default:
      $ref: '../../../components/responses.yaml#/CommonError'
delete:
  operationId: delete<PascalSingular>MetadataStateById
  tags:
    - <PascalPlural>
  summary: Удалить отдельный статус <сущности>
  parameters:
    - $ref: '../../../components/parameters.yaml#/entityId'
    - $ref: '../../../components/headers.yaml#/AcceptHeader'
    - $ref: '../../../components/headers.yaml#/AcceptEncoding'
    - $ref: '../../../components/headers.yaml#/ContentTypeJson'
  responses:
    '200':
      description: Успешный запрос
    '404':
      $ref: '../../../components/responses.yaml#/NotFoundEmpty'
    default:
      $ref: '../../../components/responses.yaml#/CommonError'
```

### DELETE `404` with empty body — `NotFoundEmpty`

`components/responses.yaml#/CommonError` (used as `default` on many operations) documents JSON/HTML error bodies. **Schemathesis** against the real API will fail if the server returns **404 with an empty body and no `Content-Type`** while only `default: CommonError` is declared — the tool expects JSON per the schema.

**Observed on live API:** `DELETE /entity/{entityName}/metadata/states/{id}` — for **any** `entityName` (dictionary or document), when the state does not exist the server may return **404 with an empty body**. Declare an explicit `404` response on that DELETE in OpenAPI:

```yaml
  responses:
    '200':
      description: Успешный запрос
    '404':
      $ref: '../../../components/responses.yaml#/NotFoundEmpty'
    default:
      $ref: '../../../components/responses.yaml#/CommonError'
```

`NotFoundEmpty` has **no `content`** (OpenAPI-valid empty body). For **every** new entity where the spec adds `.../metadata/states/{id}` with DELETE, reuse this pattern if the backend behaves the same (empty 404). Do **not** treat the rule as limited to entities already in the spec today.

## 7. Registration in `src/openapi.yaml`

### Paths block

Add under `paths:` in the appropriate section. Use a comment header for the group:

```yaml
  # Contracts
  /entity/contract:
    $ref: './paths/dictionaries/contracts/contracts.yaml'
  /entity/contract/{id}:
    $ref: './paths/dictionaries/contracts/contract-by-id.yaml'
  /entity/contract/delete:
    $ref: './paths/dictionaries/contracts/contracts-delete.yaml'
  /entity/contract/batch:
    $ref: './paths/dictionaries/contracts/contracts-batch.yaml'
  /entity/contract/metadata:
    $ref: './paths/dictionaries/contracts/contract-metadata.yaml'
  /entity/contract/metadata/attributes:
    $ref: './paths/dictionaries/contracts/contract-metadata-attribute.yaml'
  /entity/contract/metadata/attributes/{id}:
    $ref: './paths/dictionaries/contracts/contract-metadata-attribute-by-id.yaml'
  /entity/contract/metadata/states/{id}:                                        # if MD metadata has `states`
    $ref: './paths/dictionaries/contracts/contract-metadata-state-by-id.yaml'
```

For documents add positions endpoints as applicable.

### Components block

Add near the closest related schemas in `components.schemas`, following the local grouping/order already used in `src/openapi.yaml`:

```yaml
    Contract:
      $ref: './components/schemas/dictionary/contract.yaml'
    ContractList:
      $ref: './components/schemas/dictionary/contractList.yaml'
```

### Tags block

Add to `tags:` array:

```yaml
  - name: Contracts
    description: Операции с договорами
```

## 8. Fixture file

Create `tests/php/fixtures/<snake_case>.json` using a JSON example from the MD file.

### Choosing the right JSON example

Pick the **richest** single-entity JSON response from the MD — typically from the "Получить <сущность>" or "Получить <сущность> по ID" section. This response includes all server-computed fields and nested objects that the list response may omit.

**Do NOT use:**
- The creation request body (too minimal)
- The minimal list row (often missing computed fields)

**The fixture MUST include:**
- All nested entity references (`owner`, `group`, `agent`, `organization`, `contract`, etc.)
- `positions`/`returnToCommissionerPositions` as MetaArray objects (with `meta.size/limit/offset`) for document entities
- `rate` object with `currency` if the entity has it
- Computed fields like `stock`, `reserve`, `inTransit`, `quantity` (for assortment)
- `operations` array items with both `meta` and `linkedSum` (for cashin/cashout)
- Nested structures matching the exact shape from MD JSON (e.g. `customEntities[].attributes.meta` with `size/limit/offset`)

### Why this matters

Golden tests (`SerializationTest.php`) verify JSON→Model→JSON roundtrip. If the fixture is too sparse, roundtrip will pass trivially — missing fields become `null` in both directions, hiding schema/SDK mismatches. A rich fixture catches:
- Fields present in schema but missing in generated SDK model (forgotten `generate-php`)
- Incorrect nested object structure (e.g. `$ref` to wrong schema)
- Type mismatches (integer vs float, string vs object)

## 9. Golden test registration

In `tests/php/golden/SerializationTest.php`:

1. Add to `FIXTURE_MODEL_MAP`:
```php
'contract' => 'Contract',
```

2. Add any readOnly fields unique to this entity to `IGNORED_FIELDS` if needed.

## 10. Smoke tests

In `tests/php/smoke/ApiEndpointsTest.php`, add a section with test methods for each endpoint. One method per endpoint, covering all HTTP methods from the MD.

For entities with required refs in create body, include them in the smoke test JSON:

```php
// ==================== CONTRACTS ====================

public function testListContracts(): void
{
    $response = $this->client->get(self::API_BASE_PATH . '/entity/contract');
    $this->assertNotEquals(404, $response->getStatusCode(), '...');
}

public function testCreateContract(): void
{
    $response = $this->client->post(self::API_BASE_PATH . '/entity/contract', [
        'json' => [
            'name' => 'Test Contract',
            'ownAgent' => ['meta' => ['href' => '...', 'type' => 'organization', 'mediaType' => 'application/json']],
            'agent' => ['meta' => ['href' => '...', 'type' => 'counterparty', 'mediaType' => 'application/json']],
        ],
    ]);
    $this->assertNotEquals(404, $response->getStatusCode(), '...');
}

// ... GET by id, PUT, DELETE, batch, metadata, attributes
```

## 11. Cross-check against MD

Re-read the source `_<entity>.md` file and verify completeness:

### Fields check
1. Open the `#### Атрибуты сущности` table in the MD
2. For each row, confirm a matching property exists in the YAML schema
3. Verify:
   - `+Только для чтения` → `readOnly: true`
   - `[Meta]` type → correct `$ref` pattern (`allOf` for nullable, direct for non-nullable)
   - `Enum` → open string field + separate PascalCase enum component; use **JSON values** from the mapping table, not Russian labels
   - `String(N)` → `maxLength: N`
   - `Float` used for money → `format: double`; for weight/volume → `format: float`
4. Note any intentionally skipped fields (region-specific like `mod__*`, deprecated) — these are OK to omit

### Endpoints check
1. Scan all `### ` headers in the MD that describe API operations
2. For each one, confirm a matching path file and HTTP method exist
3. Common set: list, create, get by id, update, delete, batch create, batch delete
4. If MD has `### Метаданные` → add metadata + attributes (+ if the MD describes statuses, add `metadata/states/{id}` GET/PUT/DELETE; for DELETE with empty 404 from the API, add `404` → `NotFoundEmpty` — see **DELETE `404` with empty body** under §6)
5. If MD has `### Позиции` (documents) → add positions + position-by-id + positions-delete

### Fixture check
1. The fixture JSON must use the **richest** GET-by-id response from the MD, not a minimal creation response
2. Verify the fixture includes all nested entity references (owner, group, agent, organization, contract, etc.)
3. For document entities, verify `positions` and similar MetaArray fields are included as objects with `meta.size/limit/offset`
4. For entities with computed fields (assortment: `stock/reserve/inTransit/quantity`), verify they are present in the fixture
5. For entities with `operations` arrays (cashin/cashout), verify items include both `meta` and `linkedSum`
6. After any schema change, regenerate the SDK (`make generate-php`) **before** running golden tests — otherwise the SDK models won't have the new fields

### Smoke test check
1. Count test methods = count of distinct endpoint+method combinations
2. POST/PUT tests include required fields from MD in the JSON body

## 12. Verification

**ALWAYS** use Docker make targets. Do NOT use `npm run` directly.

```bash
docker compose run --rm sdk make lint           # Redocly lint — must say "valid"
docker compose run --rm sdk make bundle         # produces dist/openapi.yaml + dist/openapi.json
docker compose run --rm sdk make generate-php   # generates PHP SDK in clients/php/
docker compose restart mock                     # CRITICAL: reload bundled spec in mock server
docker compose run --rm sdk make test-golden-php  # roundtrip JSON ↔ SDK model
docker compose run --rm sdk make test-smoke       # HTTP smoke against openapi-mock
```

`test-smoke` is the canonical local smoke target. `test-smoke-php` exists as a PHP-only shortcut and may be used for narrower reruns.

### Mock server restart — required after every `make bundle`

The openapi-mock container loads `dist/openapi.yaml` **once on startup** and caches it in memory. It does **not** watch for file changes. If you skip the restart, smoke tests will return **404 for any newly added endpoints**.

```bash
docker compose restart mock
```

If `restart` doesn't help (stale container state), recreate:
```bash
docker compose rm -sf mock && docker compose up -d mock
```

### Schema change → SDK regeneration — required before golden tests

After modifying any YAML schema, **always run `make generate-php`** before golden tests. The PHP SDK models are generated code — they won't reflect new/changed properties until regenerated. Golden tests will silently pass with null values for missing fields, giving a false sense of correctness.

### Verification order matters

The correct sequence is:
1. `lint` — catch YAML/OpenAPI syntax issues
2. `bundle` — produce `dist/openapi.yaml`
3. `generate-php` — regenerate SDK models from the updated spec
4. `restart mock` — reload the bundled spec in the mock server
5. `test-golden-php` — verify roundtrip serialization with rich fixtures
6. `test-smoke` — verify endpoint reachability against the mock

Skipping step 3 after schema changes → golden tests pass falsely.
Skipping step 4 after adding endpoints → smoke tests fail with 404.
