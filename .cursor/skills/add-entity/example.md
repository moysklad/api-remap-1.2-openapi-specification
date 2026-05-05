# Worked examples

## Dictionary example — Contract (Договор)

Source: `api-remap-1.2-doc/md/dictionaries/_contract.md`

## Classification

- **Category:** Dictionary (справочник)
- **API keyword:** `contract`
- **PascalCase:** `Contract`
- **snake_case:** `contract`
- **Folder:** `src/paths/dictionaries/contracts/`

## Entity references in the schema

Contract demonstrates the main `$ref` patterns:

| Field | Target entity | Pattern |
|-------|--------------|---------|
| `group` | Group | Direct `$ref` (non-nullable) |
| `ownAgent` | Organization | Direct `$ref` (non-nullable, required) |
| `agent` | Counterparty | Direct `$ref` (non-nullable, required) |
| `owner` | Employee | `nullable: true` + `allOf` + `$ref` |
| `state` | State | `nullable: true` + `allOf` + `$ref` |
| `organizationAccount` | Account | `nullable: true` + `allOf` + `$ref` |
| `agentAccount` | Account | `nullable: true` + `allOf` + `$ref` |
| `rate.currency` | Currency | Nested object with `$ref` inside `properties` |
| `attributes` | AttributeAbstract | `type: array` with `items: $ref` |

## Endpoints from MD

| Endpoint | HTTP | operationId |
|----------|------|-------------|
| `/entity/contract` | GET | `getContracts` |
| `/entity/contract` | POST | `createContract` |
| `/entity/contract/{id}` | GET | `getContractById` |
| `/entity/contract/{id}` | PUT | `updateContract` |
| `/entity/contract/{id}` | DELETE | `deleteContract` |
| `/entity/contract/delete` | POST | `deleteContractsBatch` |
| `/entity/contract/batch` | POST | `createContractsBatch` |
| `/entity/contract/metadata` | GET | `getContractMetadata` |
| `/entity/contract/metadata/attributes` | GET | `getContractMetadataAttributes` |
| `/entity/contract/metadata/attributes` | POST | `createContractMetadataAttribute` |
| `/entity/contract/metadata/attributes/{id}` | GET | `getContractMetadataAttributeById` |
| `/entity/contract/metadata/attributes/{id}` | PUT | `updateContractMetadataAttribute` |
| `/entity/contract/metadata/attributes/{id}` | DELETE | `deleteContractMetadataAttribute` |
| `/entity/contract/metadata/states/{id}` | GET | `getContractMetadataStateById` |
| `/entity/contract/metadata/states/{id}` | PUT | `updateContractMetadataStateById` |
| `/entity/contract/metadata/states/{id}` | DELETE | `deleteContractMetadataStateById` |

## Files created

```
src/components/schemas/dictionary/contract.yaml          # full schema (was stub — expanded)
src/components/schemas/dictionary/contractList.yaml      # list envelope
src/paths/dictionaries/contracts/contracts.yaml          # GET list + POST create
src/paths/dictionaries/contracts/contract-by-id.yaml     # GET/PUT/DELETE by id
src/paths/dictionaries/contracts/contracts-delete.yaml   # POST batch delete
src/paths/dictionaries/contracts/contracts-batch.yaml    # POST batch create/update
src/paths/dictionaries/contracts/contract-metadata.yaml  # GET metadata
src/paths/dictionaries/contracts/contract-metadata-attribute.yaml      # GET/POST attributes
src/paths/dictionaries/contracts/contract-metadata-attribute-by-id.yaml # GET/PUT/DELETE attribute
src/paths/dictionaries/contracts/contract-metadata-state-by-id.yaml    # GET/PUT/DELETE state (MD metadata has `states`)
tests/php/fixtures/contract.json                         # golden fixture (rich JSON)
```

## Files modified

```
src/openapi.yaml                              # paths + ContractList in components.schemas + tag
tests/php/golden/SerializationTest.php         # FIXTURE_MODEL_MAP += 'contract' => 'Contract'
tests/php/smoke/ApiEndpointsTest.php           # 10 new test methods
```

## Key decisions

1. **Schema had a stub** — `Contract` was already registered in `components.schemas` with only `meta`/`id`/`accountId`. We expanded it with all fields from the MD, keeping the same file path.
2. **`ContractList` was new** — added both the file and the registration in `openapi.yaml`.
3. **Enum values** — use JSON values (`Commission`, `Sales`) not Russian labels, as documented in the MD mapping table. In entity fields, keep the property as an open string and expose known values through a separate PascalCase enum component.
4. **`rate` is an inline object** — not a `$ref` because it has a custom shape (`currency` + `value`), same pattern as in `customerOrder.yaml`.
5. **Metadata endpoints** reuse `DocumentMetadata`, `AttributeMetaInfo`, `AttributeMetaInfoList` — shared schemas from `common/`.
6. **States endpoint** — MD metadata section has `states` field → created `contract-metadata-state-by-id.yaml` with GET/PUT/DELETE (DELETE has `404: NotFoundEmpty`). Discovered by comparing with `customerorder` which has the same pattern.
7. **Static builder** — `Contract` has `meta`, so `contract.yaml` carries `x-entity-static-builder` with the dictionary convention (`methodParams: ["id"]`, `href: entity / contract / $id`, `type: "contract"`). The list schema `contractList.yaml` does **not** get the block — only schemas with their own `meta`. After regeneration, `Contract::createWithMeta($id)` is available in the PHP SDK.

## Verification results

| Step | Command | Result |
|------|---------|--------|
| Lint | `docker compose run --rm sdk make lint` | Valid (253 warnings, all pre-existing pattern) |
| Bundle | `docker compose run --rm sdk make bundle` | OK |
| Generate | `docker compose run --rm sdk make generate-php` | `Contract.php`, `ContractList.php` generated |
| **Mock restart** | `docker compose restart mock` | Container restarted, loaded fresh `dist/openapi.yaml` |
| Golden | `docker compose run --rm sdk make test-golden-php` | 36/36 passed |
| Smoke | `docker compose run --rm sdk make test-smoke` | 108/108 passed |

> **Note:** The mock restart step is critical. Without it, smoke tests return 404 for any newly added endpoints because openapi-mock caches the spec in memory on startup and does not reload it automatically. If `docker compose restart mock` doesn't help (stale container), use `docker compose rm -sf mock && docker compose up -d mock`.

## Common pitfalls (learned from past iterations)

| Pitfall | Symptom | Fix |
|---------|---------|-----|
| Missing `docker compose restart mock` | Smoke tests fail with `404 means endpoint path did not match` for new endpoints | Run `docker compose restart mock` (or `rm -sf mock && up -d mock`) after `make bundle` |
| Missing `make generate-php` after schema change | Golden tests pass but fixture fields are `null` (no actual roundtrip coverage) | Always run `make generate-php` after schema changes, before golden tests |
| Sparse fixture (creation request instead of GET response) | Golden test passes trivially — all missing fields round-trip as `null` | Use the richest single-entity JSON from "Получить <сущность>" section in MD |
| Nested structure mismatch (e.g. `attributes` should be an object with `meta`, not a direct `$ref`) | Golden test fails or SDK model has wrong property types | Cross-check fixture JSON against MD JSON examples for nested object shapes |
| Missing `metadata/states/{id}` endpoint | Smoke test for state CRUD fails; MD metadata section has `states` field but path file was not created | Check MD metadata section for `states`; compare endpoint set with peer entities (e.g. `customerorder`) |
| Typo in path folder (`contract/` vs `contracts/`) | `make generate-php` fails with "Unable to load RELATIVE ref" | Path folder must be lowercase **plural** per naming conventions |

## Document example sketch — entity with positions

Use this pattern for `api-remap-1.2-doc/md/documents/_<entity>.md` when the MD has `### Позиции`.

### Classification

- **Category:** Document (документ)
- **API keyword:** from the MD URL, e.g. `customerorder`
- **PascalCase:** e.g. `CustomerOrder`
- **Folder:** `src/paths/documents/<entities>/`
- **Peer candidates:** `customerorder`, `demand`, `invoiceout` depending on positions, states, and related operation arrays

### Endpoint matrix

| Endpoint kind | Required when | Files |
|---------------|---------------|-------|
| Collection | MD has list/create sections | `<entities>.yaml` |
| By ID | MD has get/update/delete by ID | `<entity>-by-id.yaml` |
| Batch create/update | MD has mass create/update | `<entities>-batch.yaml` |
| Batch delete | MD has mass delete | `<entities>-delete.yaml` |
| Metadata | MD has `### Метаданные` | `<entity>-metadata.yaml`, metadata attribute files |
| Metadata states | metadata response includes `states` | `<entity>-metadata-state-by-id.yaml` with explicit `404: NotFoundEmpty` on DELETE |
| Positions | MD has `### Позиции` | `<entity>-positions.yaml`, `<entity>-position-by-id.yaml`, `<entity>-positions-delete.yaml` |

### Files to create

```
src/components/schemas/document/<entity>.yaml
src/components/schemas/document/<entity>List.yaml
src/components/schemas/document/<entity>Position.yaml
src/components/schemas/document/<entity>PositionList.yaml
src/paths/documents/<entities>/<entities>.yaml
src/paths/documents/<entities>/<entity>-by-id.yaml
src/paths/documents/<entities>/<entities>-batch.yaml
src/paths/documents/<entities>/<entities>-delete.yaml
src/paths/documents/<entities>/<entity>-positions.yaml
src/paths/documents/<entities>/<entity>-position-by-id.yaml
src/paths/documents/<entities>/<entity>-positions-delete.yaml
tests/php/fixtures/<snake_case>.json
```

### Key decisions

1. **Positions are separate schemas** — the document has a `positions` MetaArray field, and the position endpoints use `Position` / `PositionList` schemas.
2. **Fixture must include `positions` as a MetaArray object** — include `meta.size`, `meta.limit`, and `meta.offset`, not an inline array unless the MD example really returns one.
3. **Position smoke tests are endpoint-level** — cover list, get by ID, create/update if supported by MD, and batch delete where documented.
4. **Compare against document peers first** — documents often have extra refs (`agent`, `organization`, `contract`, `state`, `rate.currency`) and operation arrays that generic dictionary templates do not show.
5. **Static builder on both schemas with `meta`** — `<entity>.yaml` gets the entity convention (`methodParams: ["id"]`, `type: "<keyword>"`); `<entity>Position.yaml` gets the position convention (`methodParams: ["parentId", "id"]`, `type: "<keyword>position"`). `<entity>List.yaml` and `<entity>PositionList.yaml` do not get the block. Reference peers: `customerOrder.yaml`, `customerOrderPosition.yaml`.
