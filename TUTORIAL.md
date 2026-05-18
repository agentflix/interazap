# Tutorial: Do Brainstorm à Implementação no InteraZap

Guia completo do workflow PREVC para desenvolvimento com IA.

---

## Índice

1. [Conceitos Fundamentais](#1-conceitos-fundamentais)
2. [Fases do Workflow PREVC](#2-fases-do-workflow-prevc)
3. [Exemplo Prático Completo](#3-exemplo-prático-completo)
4. [Quick Reference](#4-quick-reference)

---

## 1. Conceitos Fundamentais

### Agents (`.context/AGENTS/`)

| Agent | Especialidade | Fase PREVC |
|-------|--------------|------------|
| @PM | Feature docs, escopo, fechamento | Planning, Confirm |
| @PLAN | Planejar abordagem técnica | Planning |
| @ARCHITECT | DDD, decisões de arquitetura | Planning, Review |
| @REVIEWER | Code review, doc review | Review |
| @BACKEND | Laravel 12 / PHP — api/ | Execution |
| @GATEWAY | NestJS 11 / Socket.io — gateway/ | Execution |
| @FRONTEND | Angular 20 / Capacitor — app/ | Execution |
| @DBA | PostgreSQL, migrations | Execution |
| @DEV | Cross-workspace | Execution |
| @QA | Gates, coverage, isolation | Validation |
| @DEBUG | Bug investigation | Execution |
| @DOC | CHANGELOG, MEMORY, docs | Confirm |
| @GIT_COMMIT | Commits semânticos pt-BR | Confirm |
| @DESIGNER | UI/UX, wireframes | Planning |
| @ORCHESTRATOR | Coordenação multi-workspace | Todas |

### Workflow PREVC

```text
PLANNING → REVIEW → EXECUTION → VALIDATION → CONFIRM
```

### Framework T.A.C.E

| Letra | Significado | Pergunta |
|-------|-------------|----------|
| **T** | Tarefa | O QUE fazer? |
| **A** | Arquivo | ONDE fazer? (path exato) |
| **C** | Comportamento | COMO funciona (antes→depois)? |
| **E** | Evidência | COMO SABER que está pronto? |

### Ordem Cross-workspace

```text
DBA (migration) → BACKEND (domain/app/http) → GATEWAY (ws/providers) → FRONTEND (app/)
```

---

## 2. Fases do Workflow PREVC

### Fase 1: PLANNING

```bash
/new-feature nome-da-feature
```

**@PM** cria feature doc em `.context/DOCS/FEATURES/nome.md` com:
- Bounded context afetado
- Complexidade (P/M/G)
- Flags de risco (multi-tenant, billing, WhatsApp)
- Escopo incluído + fora de escopo
- Critérios de aceite verificáveis

### Fase 2: REVIEW

```bash
/review-feature nome-da-feature
# → aprovada?
/decompose nome-da-feature
/validate-tasks nome-da-feature
```

**@REVIEWER + @ARCHITECT** validam a feature doc e geram tasks T.A.C.E em `.context/DOCS/TASKS/`.

### Fase 3: EXECUTION

```bash
/implement-task nome-da-feature TASK-3.1.1
```

Agent correto implementa (BACKEND, GATEWAY, FRONTEND, DBA) respeitando:
- Arquitetura DDD
- Skill especialista do workspace (`.context/SKILLS/[workspace]-especialista/`)
- BelongsToTenant se toca dados por tenant

### Fase 4: VALIDATION

```bash
/validate nome-da-feature TASK-3.1.1
```

**@QA** executa gates do workspace:
- `api/`: `composer gate:all`
- `gateway/`: `pnpm --filter gateway lint test build`
- `app/`: `pnpm --filter app lint test build`

Gates inegociáveis. Falhou → volta para EXECUTION.

### Fase 5: CONFIRM

```bash
/confirm-task nome-da-feature TASK-3.1.1
```

**@DOC + @GIT_COMMIT**:
1. Marcar task ✅ no arquivo de tasks
2. Entrada no CHANGELOG: `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`
3. Entrada em MEMORY (se decisão ou aprendizado)
4. Atualizar `project-state.yaml`
5. Commit semântico (sem push)

---

## 3. Exemplo Prático Completo

**Situação:** "Quero adicionar lembretes de eventos no CRM"

### 1. PLANNING

```bash
/new-feature crm-event-reminders
```

Feature doc criado: `.context/DOCS/FEATURES/crm-event-reminders.md`
- Bounded Context: CRM
- Complexidade: M
- Workspaces: api, app
- Critérios: "Lembrete cria notificação 30min antes, persistido com tenant_id"

### 2. REVIEW

```bash
/review-feature crm-event-reminders
# ✅ Aprovada

/decompose crm-event-reminders
/validate-tasks crm-event-reminders
# ✅ Tasks validadas
```

Tasks criadas: `.context/DOCS/TASKS/crm-event-reminders-tasks.md`

```
TASK-3.1.1 — Migration crm_event_reminders
TASK-3.2.1 — Entity CRMEventReminder
TASK-3.3.1 — Action CreateCRMEventReminderAction
TASK-3.4.1 — Job ProcessCRMEventReminderJob
TASK-3.5.1 — Endpoint POST /crm/event-reminders
TASK-5.1.1 — Componente de lembretes no CRM
TASK-5.2.1 — Integração com API no service
```

### 3. EXECUTION (Backend)

```bash
/implement-task crm-event-reminders TASK-3.1.1   # @DBA
/validate crm-event-reminders TASK-3.1.1          # composer gate:all ✅
/confirm-task crm-event-reminders TASK-3.1.1      # CHANGELOG + commit

/implement-task crm-event-reminders TASK-3.2.1   # @BACKEND
/validate crm-event-reminders TASK-3.2.1          # ✅
/confirm-task crm-event-reminders TASK-3.2.1

# ... continua para TASK-3.3.1 ... TASK-3.5.1

# Review de Fase Backend
/review-phase 3    # @REVIEWER
/validate-phase 3  # @QA: composer gate:all
/confirm-phase 3   # @DOC: CHANGELOG de fase
```

### 4. EXECUTION (Frontend)

```bash
/implement-task crm-event-reminders TASK-5.1.1   # @FRONTEND
/validate crm-event-reminders TASK-5.1.1          # pnpm --filter app lint test build ✅
/confirm-task crm-event-reminders TASK-5.1.1

/review-phase 5
/validate-phase 5
/confirm-phase 5
```

### 5. FEATURE CONCLUÍDA

```bash
/feature-status crm-event-reminders
# Todas tasks ✅ → Feature marcada como Concluída
# CHANGELOG recebe resumo de feature
# project-state.yaml: features_completed++
```

---

## 4. Quick Reference

### Comandos PREVC

| Comando | Fase | Uso |
|---------|------|-----|
| `/new-feature [nome]` | Planning | Criar feature doc |
| `/review-feature [nome]` | Review | Validar feature doc |
| `/decompose [nome]` | Review | Gerar tasks T.A.C.E |
| `/validate-tasks [nome]` | Review | Validar qualidade das tasks |
| `/implement-task [f] [T]` | Execution | Implementar (ex: TASK-3.2.1) |
| `/validate [f] [T]` | Validation | Executar gates |
| `/confirm-task [f] [T]` | Confirm | Fechar + CHANGELOG + MEMORY |
| `/feature-status [nome]` | Qualquer | Ver progresso |
| `/review-phase [N]` | Review | @REVIEWER valida fase |
| `/validate-phase [N]` | Validation | @QA valida gates de fase |
| `/confirm-phase [N]` | Confirm | Fechar fase |

### Estrutura de Pastas

```
.context/
├── AGENTS/         ← 16 agents especializados
├── SKILLS/         ← 6 skills por workspace
├── ARCHITECTURE/   ← DDD, módulos, estado
├── DOCS/
│   ├── FEATURES/   ← Feature docs
│   ├── TASKS/      ← Tasks T.A.C.E
│   ├── PRDS/       ← Requisitos de produto
│   ├── CHANGELOG/  ← Registro diário
│   └── MEMORY/     ← Decisões e aprendizados
├── LAYOUT/         ← Wireframes
└── WORKFLOW/       ← PREVC + validation-flow
```

### Gates por Workspace

| Workspace | Comando gate |
|-----------|-------------|
| `api/` | `composer gate:all` |
| `gateway/` | `pnpm --filter gateway lint test build` |
| `app/` | `pnpm --filter app lint test build` |
| `electron/` | `pnpm --filter electron build` |

### Bounded Contexts

Ai, Auth, Billing, Chat, Configuration, CRM, Dashboard, Gateway, Platform, Reports, Shared

### Status

| Entidade | Status possíveis |
|----------|-----------------|
| Feature | 🟡 Planning → 🟠 Review → 🔄 Execução → ✅ Concluída |
| Task | ⏳ Pendente → 🔄 Em Progresso → ✅ Concluída / ❌ Reprovada |

### Tipos CHANGELOG

`FEAT | FIX | REFACTOR | DOCS | TEST | CHORE | BREAKING`

### Tipos MEMORY

`🧠 Decisão | 📚 Aprendizado | ⚠️ Armadilha | 💡 Insight`
