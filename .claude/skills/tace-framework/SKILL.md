---
name: tace-framework
description: Framework for writing InteraZap task entries using T.A.C.E (Tarefa, Arquivo, Comportamento, Evidência) with TASK-X.Y.Z hierarchical numbering. Use when writing individual task blocks, someone says "como estruturo essa task", "escrever task T.A.C.E", "montar task para [feature]", or a task entry needs to be created or improved. Do NOT use for decomposing a full feature into all its tasks (use decompose-feature), writing feature docs (use write-feature), or executing tasks.
license: CC-BY-4.0
metadata:
  author: Rafael Silva
  version: 1.0.0
---

# T.A.C.E Framework

Skill for writing well-structured InteraZap task entries. Each task follows T.A.C.E with hierarchical TASK-X.Y.Z numbering.

## T.A.C.E Fields

| Field | Question | Rule |
|-------|----------|------|
| **T — Tarefa** | O QUE fazer? | Specific verb + object. Never vague ("melhorar X"). |
| **A — Arquivo** | ONDE fazer? | Exact file path. Must be real or clearly-defined future path. |
| **C — Comportamento** | ANTES e DEPOIS? | Concrete state change. Both sides must be measurable. |
| **E — Evidência** | COMO SABER que está pronto? | Verifiable checkboxes. No "funciona corretamente". |

## Numbering System

```
TASK-X.Y.Z
├── X = Phase (1=Planning, 2=Design, 3=Backend/api, 4=Gateway, 5=Frontend/app, 6=Integration)
├── Y = Feature group within phase (1, 2, 3…)
└── Z = Coding step (1, 2, 3…)
```

Standard group order for Backend (phase 3): `3.1 Database → 3.2 Domain → 3.3 Application → 3.4 Infrastructure → 3.5 HTTP`.

## Task Block Format

```markdown
- [ ] **TASK-X.Y.Z** ⏳: [Título conciso]

  **T — Tarefa:** [Verbo específico + o que será criado/modificado]

  **A — Arquivo:** `path/exato/do/arquivo.ext`

  **C — Comportamento:**
  ANTES:
  - [estado atual concreto]

  DEPOIS:
  - [novo estado concreto e mensurável]

  **E — Evidência:**
  - [ ] [critério verificável 1]
  - [ ] [critério verificável 2]
  - [ ] [critério verificável 3]

  **Status:** ⏳ Pendente
```

Status values: `⏳ Pendente` → `🔄 Em Progresso` → `✅ Concluída` / `❌ Reprovada`

## Phase Review Block

Add this at the end of every phase group:

```markdown
### Revisão de Fase X (@REVIEWER + @QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| [critério arquitetural] | @REVIEWER | ⏳ |
| [critério de qualidade] | @QA | ⏳ |
| Gates passam | @QA | ⏳ |

**Gate de Qualidade Fase X:** ⏳ Pendente — `[gate command]`
```

Gate commands: Phase 3 → `composer gate:all` | Phase 4 → `pnpm --filter gateway lint test build` | Phase 5 → `pnpm --filter app lint test build`

## Multi-tenancy Requirement

Any task touching entities with tenant-scoped data MUST include in E — Evidência:

```markdown
- [ ] Teste de isolamento: query do tenant A não retorna dados do tenant B
- [ ] Trait `BelongsToTenant` aplicado ao model
```

## Examples

### Example 1: Database migration

User: "Task para criar a tabela crm_event_reminders"

```markdown
- [ ] **TASK-3.1.1** ⏳: Criar migration `crm_event_reminders`

  **T — Tarefa:** Criar migration que adiciona a tabela `crm_event_reminders` com colunas, FKs e índices.

  **A — Arquivo:** `api/database/migrations/2026_05_17_000000_create_crm_event_reminders_table.php`

  **C — Comportamento:**
  ANTES:
  - Tabela `crm_event_reminders` não existe

  DEPOIS:
  - Tabela com colunas: `id`, `tenant_id`, `crm_contact_id`, `title`, `reminded_at`, timestamps
  - FK `tenant_id` → `platform_tenants.id` on delete cascade
  - FK `crm_contact_id` → `crm_contacts.id` on delete cascade
  - Índice composto em `(tenant_id, reminded_at)`

  **E — Evidência:**
  - [ ] `php artisan migrate --pretend` sem erro
  - [ ] `php artisan migrate:rollback --pretend` sem erro (down() implementado)
  - [ ] `composer analyse` retorna 0 erros

  **Status:** ⏳ Pendente
```

### Example 2: Domain entity

User: "Task para criar a entity CRMEventReminder"

```markdown
- [ ] **TASK-3.2.1** ⏳: Criar Model `CRMEventReminder`

  **T — Tarefa:** Criar Eloquent model `CRMEventReminder` no Domain Layer do bounded context CRM.

  **A — Arquivo:** `api/src/Domain/CRM/Models/CRMEventReminder.php`

  **C — Comportamento:**
  ANTES:
  - Classe `CRMEventReminder` não existe

  DEPOIS:
  - Model com `$fillable`: `title`, `crm_contact_id`, `reminded_at`
  - Trait `BelongsToTenant` aplicado
  - Cast `reminded_at` → `datetime`
  - Relacionamento `contact(): BelongsTo(CRMContact)`

  **E — Evidência:**
  - [ ] Teste `CRMEventReminderTest::test_belongs_to_tenant()` passa
  - [ ] Teste de isolamento: tenant A não vê lembretes do tenant B
  - [ ] `composer analyse` retorna 0 erros

  **Status:** ⏳ Pendente
```

### Example 3: Frontend component

User: "Task para listar lembretes no CRM"

```markdown
- [ ] **TASK-5.1.1** ⏳: Criar `EventRemindersListComponent`

  **T — Tarefa:** Criar componente Angular standalone que lista lembretes de eventos de um contato CRM.

  **A — Arquivo:** `app/src/app/pages/crm/components/event-reminders-list/event-reminders-list.component.ts`

  **C — Comportamento:**
  ANTES:
  - Componente não existe; lembretes não aparecem na tela de contato

  DEPOIS:
  - Lista renderiza título e data formatada de cada lembrete
  - Estado vazio exibe "Nenhum lembrete cadastrado"
  - Emite `(delete)` ao clicar no botão de remoção

  **E — Evidência:**
  - [ ] Vitest: `should render empty state when no reminders` passa
  - [ ] Vitest: `should emit delete event on button click` passa
  - [ ] ESLint: 0 warnings
  - [ ] `pnpm --filter app build` sem erros

  **Status:** ⏳ Pendente
```

## Common Issues

**Evidence is vague:** "funciona corretamente" is not valid. Use specific test names or commands that can be run.

**File path is approximate:** "algum arquivo de CRM" is not valid. Define the exact path even if the file doesn't exist yet.

**Behaviour missing ANTES:** Always include it. Even "classe não existe" is a valid ANTES — it anchors what changed.

**Wrong phase number:** A migration is always phase 3 (backend), not phase 5. Frontend tasks are always phase 5. Cross-check with the numbering system above.
