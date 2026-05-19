---
name: detect-project
description: Detects and updates the InteraZap project context — stack versions, active bounded contexts, and project-state.yaml. Use when onboarding a new session with stale context, user says "detectar contexto", "qual é a stack atual", "atualizar project-state", "check versions", or after dependency upgrades. Do NOT use for implementing features, writing docs, or any task that already has clear context from an active session.
license: CC-BY-4.0
metadata:
  author: Rafael Silva
  version: 1.0.0
---

# Detect Project

Skill for reading and refreshing InteraZap project context. Run the commands below, then update `.context/ARCHITECTURE/project-state.yaml`.

## Step 1: Read Existing Context

Read these files first to know what was last recorded:

```bash
cat .context/ARCHITECTURE/project-state.yaml
cat .context/ARCHITECTURE/project-brain.yaml
```

## Step 2: Run Detection Commands

```bash
# Stack versions
grep '"laravel/framework"' api/composer.json
grep '"@nestjs/core"' gateway/package.json
grep '"@angular/core"' app/package.json

# PHP version
grep '"php"' api/composer.json

# Bounded contexts (api domain modules)
ls api/src/Domain/

# Active workspaces
ls -d */ | grep -v node_modules | grep -v vendor

# Test stack
grep '"pestphp\|phpunit"' api/composer.json
grep '"jest\|vitest"' gateway/package.json
grep '"vitest\|karma"' app/package.json

# CI/CD pipelines
ls .github/workflows/

# Database driver
grep "DB_CONNECTION" api/.env.example | head -2

# Queue driver
grep "QUEUE_CONNECTION\|REDIS" api/.env.example | head -3
```

## Step 3: Compare and Report Differences

Compare detected versions against what `project-state.yaml` recorded. Report any differences:

```
Stack detected:
  api/: Laravel [version] / PHP [version]
  gateway/: NestJS [version]
  app/: Angular [version]

Changes since last detection:
  [list any version bumps or new modules found]
  [or "no changes"]
```

## Step 4: Update project-state.yaml

If any value has changed, update `.context/ARCHITECTURE/project-state.yaml`:

- Update version strings under `stack`
- Update `last_updated` to today's date
- Do NOT change the `metrics` block (task counts) — those are managed by the CONFIRM phase

```yaml
# Only these fields should be updated:
stack:
  api:
    framework: "Laravel [detected]"
    language: "PHP [detected]"
  gateway:
    framework: "NestJS [detected]"
  app:
    framework: "Angular [detected]"
last_updated: "YYYY-MM-DD"
```

## Step 5: Update context-version.yaml

If `project-state.yaml` was updated, increment its version in `.context/ARCHITECTURE/context-version.yaml`:

```yaml
".context/ARCHITECTURE/project-state.yaml":
  version: "1.0.X"   # bump patch
  last_updated: "YYYY-MM-DD"
  updated_by: "detect-project"
```

## Examples

### Example 1: New session, context seems stale

User: "Antes de começarmos, você pode verificar a stack do projeto?"

Actions:
1. Run all detection commands
2. Compare with project-state.yaml
3. Report: "Stack detectada: Laravel 12.1 / NestJS 11.2 / Angular 20.3. project-state.yaml estava em Laravel 12.0 — atualizado."

### Example 2: After a major dependency update

User: "Acabei de atualizar o Angular de 19 para 20, pode atualizar o contexto?"

Actions:
1. Run `grep '"@angular/core"' app/package.json` to confirm new version
2. Update `stack.app.framework` in project-state.yaml
3. Bump version in context-version.yaml
4. Report: "Angular atualizado para 20.3 em project-state.yaml."

### Example 3: Detect new bounded context

User: "Adicionei um módulo novo chamado Workflows, pode registrar?"

Actions:
1. Run `ls api/src/Domain/` to confirm `Workflows/` exists
2. Add entry to `.context/ARCHITECTURE/modules.yaml`
3. Update `modules_status` in project-state.yaml with `status: "active"` and today's date
4. Confirm: "Módulo Workflows registrado em modules.yaml e project-state.yaml."

## Common Issues

**vendor/ or node_modules/ in workspace list:** The `ls -d */` command may include them. Ignore `vendor/`, `node_modules/`, `.git/`, `storage/` when reporting workspaces.

**composer.json or package.json not found in expected path:** The project uses a monorepo layout — look in `api/composer.json`, `gateway/package.json`, `app/package.json`, not at the root.

**project-state.yaml metrics are wrong:** Do not touch `metrics` (task counts). Those are managed exclusively by the PREVC CONFIRM phase.
