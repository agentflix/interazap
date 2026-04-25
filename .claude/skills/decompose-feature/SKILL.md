---
name: decompose-feature
description: "Decompor features em tasks usando framework T.A.C.E."
license: MIT
metadata:
  domain: planning
---

# Decompose Feature

> Skill para decompor features em tasks usando framework T.A.C.E.

---

## Quando Usar

Após uma feature doc ser **aprovada** na fase de REVIEW.

**Comando:** `/decompose [nome-da-feature]`

---

## Processo

### 1. Identificar Camadas Impactadas

Revise a feature doc e identifique:
- Backend (Laravel): Controllers, Services, Entities, Migrations
- Frontend (Angular): Components, Services, Pages
- Database: Migrations, Constraints, Índices
- Integration: APIs externas, Webhooks

### 2. Mapear Tasks por Fase

```
FASE 3: BACKEND
├── 3.1 — Database/Migration
├── 3.2 — Domain Layer (Entities, Value Objects)
├── 3.3 — Application Layer (Services, DTOs)
└── 3.4 — Presentation Layer (Controllers, Requests)

FASE 4: FRONTEND
├── 4.1 — Components
├── 4.2 — Services
└── 4.3 — Pages

FASE 5: INTEGRATION
├── 5.1 — E2E Tests
└── 5.2 — Final Validation
```

### 3. Aplicar T.A.C.E em Cada Task

Para **cada** task identificada:

- **T (Tarefa):** O que exatamente fazer?
- **A (Arquivo):** Qual arquivo modificar?
- **C (Comportamento):** Antes → Depois?
- **E (Evidência):** Critérios verificáveis?

---

## Estrutura de Output

```markdown
# Tasks: [Nome da Feature]

> Decomposição T.A.C.E das tasks da feature

## Feature: [Nome]
**ID:** FEAT-NNN
**Bounded Context:** [Módulo]
**Total Tasks:** N
**Concluídas:** 0

---

## 🔄 FASE 3: BACKEND

### Tasks

#### TASK-3.1.1 ⏳: [Título]

**T — Tarefa:** [O que fazer]

**A — Arquivo:** [Caminho completo]

**C — Comportamento:**
```
ANTES:
- [comportamento atual]

DEPOIS:
- [novo comportamento]
```

**E — Evidência:**
- [ ] [Critério 1]
- [ ] [Critério 2]

**Status:** ⏳ Pendente

---

## Revisão de Tasks

| Task | Status | Validada por | Data |
|------|--------|--------------|------|
| TASK-3.1.1 | ⏳ | - | - |

---

## Progresso

- [0/N] Tasks concluídas
- [ ] Feature completa
```

---

## Exemplo

**Feature:** Importação CSV de Contatos
**Bounded Context:** CRM

### Tasks identificadas:

1. **TASK-3.1.1** — Criar migration para contacts_imports
2. **TASK-3.2.1** — Criar Entity ContactImport
3. **TASK-3.3.1** — Criar CsvParserService
4. **TASK-3.4.1** — Criar Controller + Request
5. **TASK-4.1.1** — Criar UploadComponent
6. **TASK-4.2.1** — Criar ContactImportService (frontend)
7. **TASK-5.1.1** — E2E: Upload CSV com sucesso
8. **TASK-5.1.2** — E2E: Upload CSV com erro

---

## Regras

1. **Uma task = uma responsabilidade**
   - Não combine "criar migration E service" em uma task

2. **Backend antes de Frontend**
   - Tasks de backend (FASE 3) devem ser concluídas antes de frontend (FASE 4)

3. **Evidência sempre verificável**
   - Critérios devem ter output concreto (teste passando, build succeeds)

4. **Dependências explícitas**
   - Se TASK-B depende de TASK-A, documente isso na task
