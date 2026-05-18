---
name: decompose-feature
description: Decomposes an approved InteraZap feature doc into a hierarchical T.A.C.E task file (.context/DOCS/TASKS/). Use when the PREVC Review phase is complete, /decompose [nome] is called, user says "decompor feature", "criar tasks para", "quebrar em tasks", or "gerar arquivo de tasks". Do NOT use before a feature doc exists and has been reviewed, for writing the feature doc itself (use write-feature), or for writing individual task blocks without a feature context (use tace-framework).
license: CC-BY-4.0
metadata:
  author: Rafael Silva
  version: 1.0.0
---

# Decompose Feature

Skill for turning an approved InteraZap feature doc into a complete hierarchical task file using T.A.C.E.

## When to Run

Run only after `/review-feature [nome]` has been approved. If the feature doc doesn't exist or hasn't been reviewed, stop and ask the user to run `/new-feature` and `/review-feature` first.

## Step 1: Read and Analyse

Read `.context/DOCS/FEATURES/[nome].md` completely. Extract:

- Bounded contexts affected (maps to workspaces and phase groups)
- Workspaces affected: `api/`, `gateway/`, `app/`, `electron/`
- Flags: multi-tenant, billing, WhatsApp, breaking change
- Criteria for acceptance (become the E — Evidência entries)

Before writing tasks, consult `.context/DOCS/MEMORY/` for prior decisions on the same bounded context:

```bash
grep -r "[bounded-context]" .context/DOCS/MEMORY/
```

Also consult `.context/ARCHITECTURE/modules.yaml` to confirm module dependencies.

## Step 2: Plan the Phase Structure

Map workspaces to phases:

| Phase | Workspace | Default agent |
|-------|-----------|---------------|
| 1 | Planning (doc tasks) | @PM |
| 2 | Design (wireframes) | @DESIGNER |
| 3 | Backend — `api/` | @DBA → @BACKEND |
| 4 | Gateway — `gateway/` | @GATEWAY |
| 5 | Frontend — `app/` | @FRONTEND |
| 6 | Integration (E2E, final validation) | @DEV + @QA |

Only include phases that are actually needed. Skip phase 4 if no gateway work. Skip phase 2 if no UI.

Cross-workspace order is always: `DBA → BACKEND → GATEWAY → FRONTEND`.

## Step 3: Write the Task File

Create `.context/DOCS/TASKS/[nome]-tasks.md`.

### File Header

```markdown
# Tasks: [Nome da Feature] (FEAT-NNN)

Feature doc: `.context/DOCS/FEATURES/[nome].md`
Status geral: 🔄 Em Execução | 0/N tasks concluídas

---
```

### Phase Header

```markdown
## FASE X: [NOME] ([workspace/])
```

### Group Header (inside a phase)

```markdown
### X.Y — [Group name, e.g. "Database", "Domain", "HTTP"]
```

### Task Block

Follow the tace-framework SKILL exactly. For the E — Evidência of each task, derive checkboxes directly from the feature's criteria for acceptance.

### Phase Review Block (required at end of each phase)

```markdown
### Revisão de Fase X (@REVIEWER + @QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| [checkpoint 1] | @REVIEWER | ⏳ |
| [checkpoint 2] | @QA | ⏳ |

**Gate de Qualidade Fase X:** ⏳ Pendente — `[gate command]`
```

## Step 4: Write the Progress Table

Append at the end of the file:

```markdown
## Progresso Geral

| Fase | Descrição | Tasks | Concluídas | Gate |
|------|-----------|-------|-----------|------|
| 3 | Backend | N | 0 | ⏳ |
| 5 | Frontend | N | 0 | ⏳ |
| **Total** | | **N** | **0** | |
```

## Step 5: Update the Feature Doc

Open `.context/DOCS/FEATURES/[nome].md` and fill in the Tasks section with a summary table linking to each TASK-X.Y.Z.

## Examples

### Example 1: Backend-only feature

Feature: "Adicionar campo `expires_at` às propostas do CRM"
Workspaces: `api/` only → phases 3 only

Task file structure:
```
FASE 3: BACKEND (api/)
  3.1 — Database
    TASK-3.1.1 — Alter migration crm_proposals add expires_at
  3.2 — Domain
    TASK-3.2.1 — Add expires_at cast and validation to CrmProposal
  3.5 — HTTP
    TASK-3.5.1 — Accept expires_at in CrmProposalRequest
  Revisão Fase 3
FASE 6: Integration
  TASK-6.1.1 — E2E test: proposal expires_at
```

### Example 2: Full-stack feature

Feature: "Lembretes de eventos CRM"
Workspaces: `api/` + `app/` → phases 3 + 5 + 6

Task file structure:
```
FASE 3: BACKEND (api/)
  3.1 — Database:   TASK-3.1.1 migration
  3.2 — Domain:     TASK-3.2.1 entity, TASK-3.2.2 repository contract
  3.3 — Application: TASK-3.3.1 CreateAction, TASK-3.3.2 ProcessJob
  3.5 — HTTP:       TASK-3.5.1 endpoint POST /crm/event-reminders
  Revisão Fase 3
FASE 5: FRONTEND (app/)
  5.1 — Components: TASK-5.1.1 EventRemindersListComponent
  5.2 — Pages:      TASK-5.2.1 integrate into ContactDetailPage
  5.3 — Services:   TASK-5.3.1 CrmEventReminderService
  Revisão Fase 5
FASE 6: Integration
  TASK-6.1.1 — E2E: create reminder → appears in list
```

## Common Issues

**Feature has no bounded context defined:** Stop. Return to Planning and run `/review-feature` — the doc is incomplete.

**Multi-tenant flag is set but tasks have no isolation test in E:** Add `- [ ] Teste de isolamento: tenant A não vê dados do tenant B` to every entity task's E — Evidência.

**WhatsApp flag is set:** Tasks touching `Chat` or `gateway/` must include in E: `- [ ] Testado com conta sandbox UazAPI` and `- [ ] Compatível com Z-API adapter`.

**Billing flag is set:** Add to E of any webhook/event task: `- [ ] Idempotência via Redis (chave única + TTL)` and `- [ ] Validação HMAC`.
