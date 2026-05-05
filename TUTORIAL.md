# Tutorial: Do Brainstorm à Implementação no InteraZap

> Guia completo do workflow PREVC para desenvolvimento com IA no monorepo InteraZap.

---

## 1. Introdução

Como usar o framework AI-First do InteraZap (Laravel 12 + NestJS 11 + Angular 19 + Electron 33) do ideação à implementação.

### Pré-requisitos

- Claude Code configurado
- Estrutura `.claude/` e `.context/` presente (este setup já fez isso)
- AGENTS.md disponível (via CLAUDE.md symlink)

---

## 2. Conceitos Fundamentais

### 2.1 Agents

| Agent | Responsabilidade | Fase PREVC |
|-------|-----------------|------------|
| @PM | Feature docs, escopo, prioridades | Planning, Confirm |
| @ARCHITECT | Decisões DDD, contratos cross-workspace | Planning, Review |
| @REVIEWER | Code & doc review | Review |
| @BACKEND | Laravel 12 / DDD | Execution |
| @GATEWAY | NestJS 11 / integrações externas | Execution |
| @FRONTEND | Angular 19 / Ionic / Electron | Execution |
| @DEV | Cross-workspace | Execution |
| @DBA | PostgreSQL 17 / pgvector / Redis | Execution |
| @QA | Gates, validação | Validation |
| @DEBUG | Bug investigation | Execution |
| @DOC | CHANGELOG, MEMORY, docs | Confirm |
| @GIT_COMMIT | Commits semânticos | Confirm |
| @DESIGNER | UI/UX | Planning |
| @ORCHESTRATOR | Coordenação | Todas |

### 2.2 Workflow PREVC

```
PLANNING → REVIEW → EXECUTION → VALIDATION → CONFIRM
```

### 2.3 Framework T.A.C.E

| Letra | Significado | Pergunta |
|-------|-------------|----------|
| T | Tarefa | O QUE fazer? |
| A | Arquivo | ONDE fazer? |
| C | Comportamento | COMO funciona (antes→depois)? |
| E | Evidência | COMO SABER que está pronto? |

---

## 3. Fases do PREVC

### Fase 1: PLANNING (PM + ARCHITECT + DESIGNER)

1. Identificar PRD (se existir)
2. Consultar MEMORY
3. Analisar dependências (`.context/ARCHITECTURE/modules.yaml`)
4. Criar feature doc: `/new-feature [nome]`

### Fase 2: REVIEW (REVIEWER + ARCHITECT)

1. `/review-feature [nome]`
2. Se aprovado: `/decompose [nome]`
3. `/validate-tasks [nome]`

### Fase 3: EXECUTION (DEV / BACKEND / GATEWAY / FRONTEND / DBA)

1. Ler task T.A.C.E
2. `/implement-task [feature] [TASK-NNN]`
3. Ordem padrão: DBA → BACKEND → GATEWAY → FRONTEND

### Fase 4: VALIDATION (QA)

1. `/validate [feature] [TASK-NNN]`
2. Rodar gates do workspace tocado
3. Se falhar → volta para EXECUTION

### Fase 5: CONFIRM (PM / DOC / GIT_COMMIT)

1. `/confirm-task [feature] [TASK-NNN]`
2. CHANGELOG + MEMORY
3. Atualizar `project-state.yaml`
4. Commit semântico

---

## 4. Templates

| Template | Local | Quando |
|----------|-------|--------|
| Feature Doc | `.context/DOCS/FEATURES/_TEMPLATE.md` | Planning |
| PRD | `.context/DOCS/PRDS/_TEMPLATE.md` | Planning (alta complexidade) |
| Tasks | `.context/DOCS/TASKS/_TEMPLATE.md` | Review |
| CHANGELOG | `.context/DOCS/CHANGELOG/_TEMPLATE.md` | Confirm |
| MEMORY | `.context/DOCS/MEMORY/_TEMPLATE.md` | Confirm |

---

## 5. Exemplo Prático: "Importar contatos via CSV"

### Passo 1: PLANNING

```bash
/new-feature importar-contatos-csv
```

Cria `.context/DOCS/FEATURES/importar-contatos-csv.md` com:
- Bounded contexts: `Chat`, `CRM`
- Workspaces: `api/`, `app/`
- Complexidade: M

### Passo 2: REVIEW

```bash
/review-feature importar-contatos-csv
/decompose importar-contatos-csv
/validate-tasks importar-contatos-csv
```

Tasks geradas (exemplo simplificado):

```
FASE 3: BACKEND
├── TASK-3.1.1 ⏳ Migration import_jobs
├── TASK-3.2.1 ⏳ Model ImportJob com BelongsToTenant
├── TASK-3.3.1 ⏳ Action ChatImportContactsAction
└── TASK-3.4.1 ⏳ Endpoint POST /api/chat/contacts/import

FASE 5: FRONTEND
├── TASK-5.1.1 ⏳ Componente ImportCsvComponent
├── TASK-5.2.1 ⏳ Página /chat/contatos/importar
└── TASK-5.3.1 ⏳ Service ContactImportService

FASE 6: INTEGRATION
└── TASK-6.1.1 ⏳ E2E manual + regressão
```

### Passo 3: EXECUTION

```bash
/implement-task importar-contatos-csv TASK-3.1.1
# DBA cria migration

/implement-task importar-contatos-csv TASK-3.2.1
# BACKEND implementa Model

# ... continua
```

### Passo 4: VALIDATION

```bash
/validate importar-contatos-csv TASK-3.1.1
# QA roda: cd api && composer gate:all
```

### Passo 5: CONFIRM

```bash
/confirm-task importar-contatos-csv TASK-3.1.1
# DOC adiciona entrada em CHANGELOG do dia
# Se decisão técnica importante → MEMORY
# GIT_COMMIT cria commit semântico:
#   feat(api): adicionar tabela import_jobs com BelongsToTenant
```

---

## 6. Quick Reference

### Estrutura Hierárquica de Tasks

```
TASK-X.Y.Z
├── X = Fase (1-6)
├── Y = Feature dentro da fase
└── Z = Etapa
```

### Comandos

| Comando | Fase |
|---------|------|
| `/new-feature [nome]` | Planning |
| `/review-feature [nome]` | Review |
| `/decompose [nome]` | Review |
| `/validate-tasks [nome]` | Review |
| `/implement-task [f] [T]` | Execution |
| `/validate [f] [T]` | Validation |
| `/confirm-task [f] [T]` | Confirm |
| `/feature-status [nome]` | Qualquer |
| `/review-phase [N]` | Review |
| `/validate-phase [N]` | Validation |
| `/confirm-phase [N]` | Confirm |

### Gates por Workspace

| Workspace | Comando |
|-----------|---------|
| api/ | `cd api && composer gate:all` |
| gateway/ | `pnpm --filter gateway lint && test && build` |
| app/ | `pnpm --filter app lint && test && build` |
| electron/ | `pnpm --filter electron build` |

### Bounded Contexts (api/)

`Ai`, `Auth`, `Billing`, `Chat`, `Configuration`, `CRM`, `Dashboard`, `Gateway`, `Platform`, `Reports`, `Shared`

### Status

| Feature | Task |
|---------|------|
| 🟡 Em Planning | ⏳ Pendente |
| 🟡 Em Review | 🔄 Em Progresso |
| 🔄 Em Execução | ✅ Concluída |
| ✅ Concluída | ❌ Reprovada |

### Tipos de CHANGELOG

`FEAT`, `FIX`, `REFACTOR`, `DOCS`, `TEST`, `CHORE`, `BREAKING`

### Tipos de MEMORY

Decisão / Aprendizado / Armadilha / Insight

---

## 7. Próximos Passos

1. Revisar `AGENTS.md` na raiz — fonte da verdade
2. Explorar `.claude/agents/` para entender as personas
3. Criar sua primeira feature: `/new-feature [nome]`
4. Seguir PREVC do início ao fim

Boas implementações.
