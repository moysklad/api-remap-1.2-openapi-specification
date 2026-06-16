# Project Overview — remap-api-specification

Modular OpenAPI 3.0.3 specification for MoySklad JSON API 1.2 with automated SDK generation, testing, versioning, and multi-repo publishing.

## Repositories

| Repo | Purpose |
|------|---------|
| `git.company.lognex/moysklad/misc/remap-api-specification` (this repo) | OpenAPI spec, SDK generation, CI/CD pipeline, tests |
| `github.com/moysklad/api-remap-1.2-openapi-specification` | Public GitHub mirror (mirrored on master merge) |
| `github.com/moysklad/php-remap-1.2-sdk` | Public PHP SDK (pushed via `push-sdk-php` job) |
| `github.com/moysklad/java-remap-1.2-sdk` | Public Java SDK repository (generated/tested from this spec) |
| `git.company.lognex/moysklad/misc/php-remap-1.2-sdk` | Internal GitLab PHP SDK (managed by `prep-branch-and-mr-php` / `merge-branch-php`) |
| `git.company.lognex/moysklad/misc/remap-1.2-java-sdk` | Internal GitLab Java SDK (managed by `prep-branch-and-mr-java` / `merge-branch-java`) |

## Tech Stack

- **Spec format:** OpenAPI 3.0.3 (YAML, modular: `src/openapi.yaml` is the root)
- **Linting:** Redocly CLI (`npm run validate`)
- **Bundling:** Redocly CLI → `dist/openapi.yaml` / `dist/openapi.json`
- **SDK generation:** OpenAPI Generator CLI (PHP + Java with custom templates in `customtemplates/php/` and `customtemplates/java/`); Java SDK runtime artifact is a self-contained shaded JAR with dependency relocation
- **Custom schema helper generation:** `x-entity-static-builder` is consumed by both PHP and Java custom templates to generate `createWithMeta(...)` helpers on referenceable models with top-level `meta`
- **Testing:** PHPUnit (PHP golden + smoke via openapi-mock), Maven Surefire (Java golden), Schemathesis (contract)
- **Versioning:** `standard-version` + `oasdiff` (breaking change detection); tag format `MAJOR.MINOR.PATCH` (semver)
- **Runtime:** Node.js v24.0.1, npm; Docker + Docker Compose for local reproducibility
- **CI:** GitLab CI/CD (`.gitlab-ci.yml` + included files under `gitlab/`)

## Project Structure (key paths)

```
.cursor/
  PROJECT.md                           # Agent-facing project overview and entry point for project context
  rules/
    project-context.mdc               # Read PROJECT.md first when project context is missing
    clarification-first.mdc           # Intelligent trigger for ambiguous/non-trivial tasks
    quality-gates.mdc                 # Intelligent trigger before finalizing changed work
    change-safety.mdc                 # Intelligent trigger for compatibility-sensitive changes
    security-baseline.mdc             # Intelligent trigger for security-sensitive code/config
    remap-project.mdc                 # Intelligent trigger for documented project behavior changes
    plan-mode.mdc                     # Intelligent trigger for implementation plans
    rule-governance.mdc               # How to create/update rules and resolve rule overlap
    dependency-governance.mdc         # File-scoped dependency change governance
.gitlab-ci.yml                         # Main CI entrypoint
gitlab/
  .gitlab-ci-sdk-validate.yml          # Includes lint, bundle, generate (php/java), golden, smoke, contract jobs
  .gitlab-ci-sdk-php-gen.yml           # push-sdk-php (to GitHub, PUSH_TO_REMOTE=true)
  .gitlab-ci-prepare-sdk-php.yml       # prep-branch-and-mr-php + merge-branch-php (internal GitLab SDK repo)
  .gitlab-ci-prepare-sdk-java.yml      # prep-branch-and-mr-java + merge-branch-java (internal GitLab Java SDK repo)
  .gitlab-ci-deploy-sdk-java.yml       # deploy-to-artifactory + deploy-to-maven (Java artifact publishing)
  .gitlab-ci-github-mirror.yml         # mirror-to-github + create-github-release
  version.gitlab-ci.yml                # version:auto (CHANGELOG, tag, push)
  .gitlab-ci-java-sdk.yml              # Legacy Java SDK (USE_OLD_SDK=true)
  .gitlab-ci-spec-gen.yml              # Legacy PHP via openapi (SPEC_SDK_GENERATION=true)
  sdk/
    lint-openapi.yml
    generate-sdk.yml
    sdk-tests-golden.yml
    sdk-tests-smoke.yml
    sdk-contract.yml
src/openapi.yaml                       # Root spec file
customtemplates/php/                   # Mustache templates for PHP SDK
customtemplates/java/                  # Mustache templates for Java SDK
tests/fixtures/                        # Shared golden fixtures for PHP and Java SDK assertions
tests/php/                             # PHPUnit golden + smoke tests
tests/java/assertions/                 # Maven golden tests for Java SDK
java.Dockerfile                        # Java local test image (Maven + JDK)
pom.xml                                # Root Maven config for Java golden tests
clients/                               # Generated SDK output (gitignored)
dist/                                  # Bundled spec output (gitignored)
Makefile                               # Local build targets (lint, bundle, light-bundle, generate, test)
docker-compose.yml                     # Docker environment for local runs
docker-compose.override.example.yml    # Optional external network for sdk (copy to docker-compose.override.yml)
package.json                           # npm scripts: validate, bundle, generate-php, etc.
.versionrc.json                        # standard-version config
CHANGELOG.md                           # Auto-generated changelog (prepended by version:auto)
```

## CI/CD Pipeline — stages (in order)

| Stage | Key jobs | When |
|-------|----------|------|
| `changes-check` | `check-openapi-changes` | master push |
| `verify` | `lint-openapi`, `bundle-openapi`, `bundle-smoke-openapi`; `deploy-contract-env`, `create-contract-user` for web / master / tag contract pipelines | push / web / master / tags |
| `contract-test` | `sdk-contract` | web / master / tags |
| `generate-sdk` | `generate-sdk-php`, `generate-sdk-java` (python/js remain stubs) | push / web / master |
| `test` | `sdk-golden-php`, `sdk-golden-java`, `sdk-smoke` (php) | push / web / master |
| `version` | `version:auto` | master push |
| `push-sdk` | `push-sdk-php` | web + PUSH_TO_REMOTE=true |
| `mirror` | `mirror-to-github`, `create-github-release` | master push |
| `prepare-sdk-repository` | `prep-branch-and-mr-php`, `prep-branch-and-mr-java`, `merge-branch-php`, `merge-branch-java` | push/web (branch) / master push |
| `deploy-sdk` | `deploy-to-artifactory`, `deploy-to-maven` | branch push/web (Artifactory) / master push (Maven Central) |

Legacy stages (`prepare`, `deploy-for-space`, `create-user`, `build`, `delete-space`) exist for backward-compatible Java SDK pipeline (USE_OLD_SDK=true).

**Merge request pipelines:** disabled in `.gitlab-ci.yml` (`workflow: rules`). Job `merge-request-ci-placeholder` exists only so GitLab’s CI config validation (which runs before `workflow`) sees at least one visible job when `CI_PIPELINE_SOURCE == merge_request_event`; includes do not add jobs in that context.

## Pipeline Scenarios (summary)

1. **Push to branch** — lint, bundle, light-bundle, generate PHP+Java SDK, run golden (PHP+Java) and smoke (PHP), prep branch sync for both internal SDK repos, then publish a branch-scoped Java artifact to Artifactory.
2. **Manual (web) on branch** — same as push + contract test flow (`deploy-contract-env` → `create-contract-user` → `sdk-contract`, optional `remove-contract-env`) + optional `push-sdk-php` (PUSH_TO_REMOTE=true) + the same PHP/Java internal SDK sync and Java Artifactory publish steps.
3. **Master merge/push** — checks, contract test flow, SDK generation + tests, `version:auto` (CHANGELOG + tag), `mirror-to-github` + `create-github-release`, manual internal SDK release sync for PHP+Java, then Java publish to Maven Central.
4. **Tag push** — SDK validate flow including Schemathesis `examples`; release/mirror jobs remain tied to master pushes.

## Key CI Variables

| Variable | Purpose |
|----------|---------|
| `SDK_LANGUAGES` | Comma-separated SDK languages to generate (default: `""` = all available) |
| `PUSH_TO_REMOTE` | Push SDK to GitHub remote repos (`"true"` / `"false"`, default `"false"`) |
| `GIT_PASSWORD` | GitHub token (mirror, push-sdk, GitHub release) |
| `CICD_PAT_PHP` | GitLab token for internal PHP SDK repo (`git.company.lognex/.../php-remap-1.2-sdk`) |
| `CICD_PAT_JAVA` | GitLab token for internal Java SDK repo (`git.company.lognex/.../remap-1.2-java-sdk`) |
| `GIT_USER` / `GIT_MAIL` | Git identity for CI commits |
| `SCHEMATHESIS_HOST` / `_LOGIN` / `_PASSWORD` | Optional overrides for contract tests; by default exported by `deploy-contract-env` / `create-contract-user` in web, master, and tag contract pipelines |
| `SCHEMATHESIS_WORKERS` | Schemathesis parallel workers inside `sdk-contract`; default **auto** |
| `ENV_TTL_MINUTES` | DMS auto-clean delay after `env_prepare` (minutes); default **20** in `utils_python/client.py`; `deploy-contract-env` sets **60** for OpenAPI contract tests unless overridden — see `README_GITLAB_CI.md` |
| `ARTIFACTORY_REPO_URL` | Target Artifactory repository for branch Java artifact deploys |
| `CENTRAL_USER` / `CENTRAL_PASSWORD` / `GPG_SECRET_KEY` | Credentials and signing key for Java release publishing to Maven Central |

## Versioning

- `version:auto` runs on master: detects breaking changes via `oasdiff`, bumps MAJOR or MINOR accordingly.
- Tag format: `MAJOR.MINOR.PATCH` (semver, e.g. `1.2.0`).
- CHANGELOG block is generated from Conventional Commits (`feat`, `fix`, `docs`, `feat!`); if none found, `* технические изменения` is used.
- Annotated tag message = CHANGELOG block for that version.
- Release commit includes `[skip ci]` to avoid retriggering.
- After `version:auto`, the pipeline SHA is stale; downstream jobs (e.g. `merge-branch-php`, `merge-branch-java`, `mirror-to-github`) must re-clone the repo and fetch tags to see the new commit/tag.

## Mock Server for Smoke Tests

Smoke tests use [muonsoft/openapi-mock](https://github.com/muonsoft/openapi-mock) (Go-based) instead of Stoplight Prism. Prism cannot handle this project's large OpenAPI spec with recursive `$ref` definitions — it hangs indefinitely during parsing regardless of version or Node.js heap limits. openapi-mock loads the same spec in ~1-2 seconds.

Key differences from Prism: openapi-mock serves endpoints under the `servers.url` base path (`/api/remap/1.2`), and returns HTTP 500 for deeply recursive schemas (`max recursion level reached`). Both behaviors are accounted for in test code.

**Critical caveat:** openapi-mock loads `dist/openapi.yaml` **once on startup** and caches it in memory — it does **not** watch for file changes. After `make light-bundle` produces an updated smoke bundle, the mock container must be restarted (`docker compose restart mock`) before running smoke tests. Otherwise newly added endpoints return 404.

## Local Development

- `nvm use v24.0.1 && npm install` for Node tooling.
- `npm run validate` / `npm run generate-php` / `npm run generate-java` / `npm run bundle` for quick local checks.
- `docker compose run --rm sdk make <target>` for Docker-based runs (see `make help`).
- Java golden tests locally: `docker compose run --rm java-sdk make test-golden-java` (or `make test-golden LANGUAGES=java`).
- Golden fixtures live in `tests/fixtures/` and are shared by PHP and Java golden tests.
- PHP tests require PHP 8.1+ with extensions: dom, json, mbstring, curl, Composer.
- After `make light-bundle`, always `docker compose restart mock` before running smoke tests (see Fast Smoke Bundle and Mock Server section above).
- Smoke tests are run via `docker compose run --rm sdk make test-smoke`.
- After modifying any YAML schema, regenerate both SDKs before golden tests: `make generate-php` and `make generate-java`.

For detailed local setup and Docker usage see `README_LOCAL.md`.
For detailed CI/CD pipeline docs see `README_GITLAB_CI.md`.

## Cursor Agent Context

- `/.cursor/PROJECT.md` is the first place an agent should read when project-level context is missing.
- Keep `alwaysApply: true` minimal; currently only `project-context.mdc` is always loaded.
- Broad safeguards (`clarification-first`, `quality-gates`, `change-safety`, `security-baseline`, `remap-project`, `plan-mode`) use clear descriptions for intelligent invocation.
- File-scoped rules apply through `globs`: `rule-governance.mdc` for `.cursor/rules/*.mdc`, `dependency-governance.mdc` for dependency manifests and lockfiles.

## Documentation Maintenance

See `remap-project.mdc` for the authoritative list of documents to keep in sync with implementation changes.
