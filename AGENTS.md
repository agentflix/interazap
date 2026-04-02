# AGENTS.md — InteraZap Development Contract

> ⚠️ Read `.context/DOCS/MEMORY/` before starting any work.

**Language**: Responses and docs in PT-BR | Code in EN

## Stack

| Layer           | Technology                                   | Version  |
| --------------- | -------------------------------------------- | -------- |
| Frontend        | Angular + TypeScript + Tailwind              | 20 / 5.9 |
| Gateway         | NestJS + TypeScript + BullMQ + WebSocket     | 11 / 5.7 |
| Backend         | Laravel + PHP + Sanctum + Spatie Permissions | 12 / 8.3 |
| Database        | PostgreSQL + pgvector                        | 17       |
| Cache/Queue     | Redis (Streams, PubSub, Cache)               | 7        |
| Testing         | Pest (BE) / Vitest (FE) / Jest (GW)          | -        |
| Static Analysis | PHPStan L6 + Larastan + Rector               | -        |
| Monitoring      | Prometheus + Grafana + Alertmanager          | -        |
| Container       | Docker Compose                               | -        |

## Rules

### Backend (PHP 8.3 / Laravel 12)

- `declare(strict_types=1)` in every PHP file
- phpDoc mandatory on classes and public methods
- `final class` on Controllers, Actions, and DTOs
- Explicit `$fillable`. Never use `$guarded = []`
- UUID primary keys. Never auto-increment
- DDD: Controller → DTO → Action → Resource
- Tenant isolation: `BelongsToTenant` trait + tenant filters
- `$this->authorize()` in every controller action
- Eager loading. Never N+1
- DTO `readonly` with `fromRequest()` and `fromArray()` when applicable

### Frontend (Angular 20+ / TypeScript 5.9)

#### Mandatory skills for any frontend task

| Skill                                       | Purpose                                             |
| ------------------------------------------- | --------------------------------------------------- |
| `.claude/skills/design/SKILL.md`            | Design tokens, spacing, typography, component rules |
| `.claude/skills/frontend-flow/SKILL.md`     | Full frontend workflow — use as checklist           |
| `.claude/skills/angular-architect/SKILL.md` | Angular 20+ patterns: signals, OnPush, routing      |
| `.claude/skills/coding-guidelines/SKILL.md` | General coding discipline — avoid common LLM errors |

> Read all 4 skills **before writing any code**. `@DESIGNER` must produce spec before `@FRONTEND` starts.

- Never use `any` or `unknown`
- jsDoc on interfaces and exported functions
- `ChangeDetectionStrategy.OnPush` on every component
- `signal()` and `computed()` for local state
- `inject()` instead of constructor injection
- `takeUntilDestroyed` on all subscriptions
- `track` in every `@for`
- Always implement loading, empty, and error states
- Check `http://localhost:4200/ui-kit` before creating new visual components
- `CrudPageComponent` for all CRUD listings
- Never use raw `<table>`, `<button>`, `<input>` or ad-hoc CSS when shared components exist
- Never hardcode state colors; use design tokens
- Never create inline badges; use dedicated status component
- Always explicit light/dark pairs with `neutral-*` and `dark:`

### Gateway (NestJS 11)

- `ValidationPipe` with whitelist on controllers
- Logger per controller and service
- Idempotency in webhooks via Redis SETNX
- Circuit breaker on external calls
- Webhook ACK < 150ms

### Security

- Never log tokens, passwords, or API keys
- Tenant isolation verified on every endpoint
- FormRequest or validation DTO for all external input
- Rate limiting on public endpoints

## Modules

`Ai` | `Auth` | `Billing` | `Chat` | `Configuration` | `CRM` | `Dashboard` | `Gateway` | `Platform` | `Reports` | `Shared`

Golden model for CRUD: `app/src/app/pages/crm/contacts/`

## Shared Components

35+ reusable components in `app/src/app/shared/components/`:

`crud-page` | `modal` | `confirm-modal` | `button` | `loading-button` | `text-input` | `select-input` | `phone-input` | `currency-input` | `document-input` | `masked-input` | `checkbox-input` | `radio-input` | `switch-input` | `color-input` | `file-input` | `textarea-input` | `search-input` | `form-label` | `form-error` | `pagination` | `skeleton-table-row` | `status-badge` | `table-actions` | `empty-state` | `page-title` | `dropdown-button` | `icon-button` | `button-group` | `fab` | `base-list` | `unified-list` | `apexchart`

## Paths

### Backend (`api/`)

| Artifact    | Path                                                                       |
| ----------- | -------------------------------------------------------------------------- |
| Controller  | `src/Domain/{Domain}/Http/Controllers/{Domain}{Entity}Controller.php`      |
| Action      | `src/Domain/{Domain}/Actions/{Verb}{Domain}{Entity}Action.php`             |
| Model       | `src/Domain/{Domain}/Models/{Domain}{Entity}.php`                          |
| DTO         | `src/Domain/{Domain}/DTOs/{Domain}{Entity}DTO.php`                         |
| FormRequest | `src/Domain/{Domain}/Http/Requests/{Domain}{Entity}{Verb}Request.php`      |
| Resource    | `src/Domain/{Domain}/Http/Resources/{Domain}{Entity}Resource.php`          |
| Policy      | `src/Domain/{Domain}/Policies/{Domain}{Entity}Policy.php`                  |
| Migration   | `database/migrations/YYYY_MM_DD_HHMMSS_create_{domain}_{entity}_table.php` |
| Routes      | `src/Domain/{Domain}/Routes/api.php`                                       |
| Test        | `tests/Feature/{Domain}{Entity}Test.php`                                   |

### Frontend (`app/`)

| Artifact         | Path                                               |
| ---------------- | -------------------------------------------------- |
| Page             | `src/app/pages/{domain}/{entity}/{entity}.ts`      |
| Service          | `src/app/core/services/{entity}.service.ts`        |
| Model            | `src/app/shared/models/{entity}.model.ts`          |
| Shared Component | `src/app/shared/components/{name}/{name}.ts`       |
| Test             | `src/app/pages/{domain}/{entity}/{entity}.spec.ts` |

### Gateway (`gateway/`)

| Artifact   | Path                                                      |
| ---------- | --------------------------------------------------------- |
| Controller | `src/domains/{domain}/controllers/{entity}.controller.ts` |
| Service    | `src/domains/{domain}/services/{entity}.service.ts`       |
| Module     | `src/domains/{domain}/{domain}.module.ts`                 |
| DTO        | `src/domains/{domain}/dto/{entity}.dto.ts`                |

## Gates

```bash
# Backend
cd api && composer gate:all

# Frontend
cd app && pnpm run gate:all

# Gateway
cd gateway && pnpm lint && pnpm test
```

**Gates are non-negotiable.** If failed → fix → re-run.

Auto-fix:

```bash
cd api && composer format
cd app && pnpm run format
cd app && pnpm run lint:fix
```

## Workflow

Follows PREVC. No phase can be skipped.

1. **Planning**: Understand spec, decompose task, define approach → `DOCS/PLANS/`
2. **Review**: Validate approach before implementation
3. **Execution**: Code, test, document
4. **Validation**: Gates, QA, and code review (mandatory)
5. **Confirm**: Evidence, semantic commit, closure → `DOCS/TASKS/` updated

Detailed flow: `.context/WORKFLOW/prevc.md`
Validation: `.context/WORKFLOW/validation-flow.md`
Task template: `.context/WORKFLOW/task-template.md`

Exit criteria: code + tests + docs + green gates + review without critical blockers.

## Agents

| Agent           | Role                      | Trigger                                 |
| --------------- | ------------------------- | --------------------------------------- |
| `@ORCHESTRATOR` | Delegates and coordinates | Complex multi-agent tasks               |
| `@DEV`          | Full-stack implementation | Cross-layer features                    |
| `@BACKEND`      | Laravel DDD specialist    | Backend tasks                           |
| `@DESIGNER`     | UI/UX design specialist   | Before any new or significant UI change |
| `@FRONTEND`     | Angular specialist        | Frontend tasks                          |
| `@DBA`          | Database design           | Migrations and schema                   |
| `@ARCHITECT`    | Specs and ADRs            | Major features, structural changes      |
| `@REVIEWER`     | Code review               | After QA                                |
| `@QA`           | Quality audit             | After gates                             |
| `@DEBUG`        | Bug investigation         | Bugs and errors                         |
| `@DOC`          | Documentation             | Docs and context artifacts              |
| `@PM`           | Task decomposition        | Major features                          |
| `@GIT_COMMIT`   | Semantic commits          | After review                            |

For frontend work: `@DESIGNER` defines the spec first, then `@FRONTEND` implements.

## Refs

Mandatory:

- Always read `.context/WORKFLOW/*.md`
- Always read `.claude/skills/`

Optional:

- `.context/WORKFLOW/project-brain.yaml`
- `.context/WORKFLOW/project-state.yaml`

| Document         | Path                                    |
| ---------------- | --------------------------------------- |
| Docs             | `.context/DOCS/`                        |
| Project Brain    | `.context/WORKFLOW/project-brain.yaml`  |
| Modules          | `.context/WORKFLOW/modules.yaml`        |
| Dependencies     | `.context/WORKFLOW/dependencies.yaml`   |
| Project State    | `.context/WORKFLOW/project-state.yaml`  |
| Architecture     | `.context/ARCHETURE/`                   |
| PREVC Workflow   | `.context/WORKFLOW/prevc.md`            |
| Validation Flow  | `.context/WORKFLOW/validation-flow.md`  |
| Task Template    | `.context/WORKFLOW/task-template.md`    |
| Plan Template    | `.context/WORKFLOW/plan-template.md`    |
| Development Flow | `.context/WORKFLOW/development-flow.md` |
| Agents           | `.claude/agents/`                       |
| Skills           | `.claude/skills/`                       |
| Memory           | `.context/DOCS/MEMORY/`                 |
| Changelog        | `.context/DOCS/CHANGELOG/`              |
