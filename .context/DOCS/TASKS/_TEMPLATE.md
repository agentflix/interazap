# Tasks: [Nome da Feature]

> Decomposição T.A.C.E hierárquica (TASK-X.Y.Z) para a feature.
> Feature doc: `../FEATURES/[feature].md`

---

## Estrutura Hierárquica

| Nível | Significado |
|-------|-------------|
| X | Fase: 1=Planning, 2=Design, 3=Backend, 4=Gateway, 5=Frontend, 6=Integration |
| Y | Feature dentro da fase |
| Z | Etapa de codificação |

---

## FASE 1: PLANNING

### Features desta Fase

#### 1.1 — Documentação

- [ ] **TASK-1.1.1** ⏳: Feature doc criada e aprovada

  **T — Tarefa:** Criar feature doc usando `_TEMPLATE.md`
  **A — Arquivo:** `.context/DOCS/FEATURES/[feature].md`
  **C — Comportamento:**
  - ANTES: feature não documentada
  - DEPOIS: doc com escopo, CA, deps, riscos
  **E — Evidência:**
  - [ ] Doc criado
  - [ ] REVIEWER aprovou
  **Status:** Pendente

### Revisão de Fase 1 (REVIEWER)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Feature doc completa | REVIEWER | aguardando |
| Bounded contexts identificados | ARCHITECT | aguardando |

---

## FASE 2: DESIGN (se UI)

#### 2.1 — Wireframes / componentes

- [ ] **TASK-2.1.1** ⏳: Wireframe da tela X

  **T — Tarefa:** Especificar wireframe
  **A — Arquivo:** `.context/LAYOUT/[feature]/wireframe.md`
  **C — Comportamento:** N/A (especificação)
  **E — Evidência:**
  - [ ] Wireframe revisado por PM
  **Status:** Pendente

---

## FASE 3: BACKEND (api/)

#### 3.1 — Database / Migration (DBA)

- [ ] **TASK-3.1.1** ⏳: [Título]

  **T — Tarefa:** Criar migration
  **A — Arquivo:** `api/database/migrations/YYYY_MM_DD_HHMMSS_create_[tabela]_table.php`
  **C — Comportamento:**
  - ANTES: tabela inexistente
  - DEPOIS: tabela criada com tenant_id, UUID PK, FKs, índices
  **E — Evidência:**
  - [ ] `php artisan migrate` ok
  - [ ] `php artisan migrate:rollback` ok (down implementado)
  **Status:** Pendente

#### 3.2 — Domain layer (BACKEND)

- [ ] **TASK-3.2.1** ⏳: Criar Entity / Model

  **T — Tarefa:** Criar Model com BelongsToTenant
  **A — Arquivo:** `api/src/Domain/[Context]/Models/[Context][Entity].php`
  **C — Comportamento:**
  - ANTES: model inexistente
  - DEPOIS: model com fillable explícito, BelongsToTenant, UUID
  **E — Evidência:**
  - [ ] Teste unit em `tests/Unit/[Context]/[Entity]ModelTest.php`
  - [ ] PHPStan L6 limpo
  **Status:** Pendente

#### 3.3 — Application layer (Actions / DTOs)

- [ ] **TASK-3.3.1** ⏳: Criar Action

  **T — Tarefa:** ...
  **A — Arquivo:** `api/src/Domain/[Context]/Actions/[Context][Entity]Action.php`
  **C — Comportamento:** ...
  **E — Evidência:** ...
  **Status:** Pendente

#### 3.4 — Presentation (Controller / Routes)

- [ ] **TASK-3.4.1** ⏳: Endpoint REST

  **T — Tarefa:** ...
  **A — Arquivo:** `api/src/Domain/[Context]/Http/Controllers/[Context][Entity]Controller.php`
  **C — Comportamento:** ...
  **E — Evidência:** ...
  **Status:** Pendente

### Revisão de Fase 3 (REVIEWER + QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Migrations reviewed | DBA | aguardando |
| Domain layer puro PHP | REVIEWER | aguardando |
| `BelongsToTenant` aplicado | REVIEWER | aguardando |
| `composer gate:all` passa | QA | aguardando |
| Coverage >= 80% | QA | aguardando |

---

## FASE 4: GATEWAY (gateway/)

#### 4.1 — Módulo / integração

- [ ] **TASK-4.1.1** ⏳: ...

### Revisão de Fase 4 (REVIEWER + QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Circuit breaker / idempotência | REVIEWER | aguardando |
| pnpm --filter gateway test | QA | aguardando |
| pnpm --filter gateway build | QA | aguardando |

---

## FASE 5: FRONTEND (app/, electron/)

#### 5.1 — Componente / página

- [ ] **TASK-5.1.1** ⏳: ...

### Revisão de Fase 5 (REVIEWER + QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Standalone component | REVIEWER | aguardando |
| Vitest passa | QA | aguardando |
| Build limpo | QA | aguardando |

---

## FASE 6: INTEGRATION

#### 6.1 — E2E / validação final

- [ ] **TASK-6.1.1** ⏳: Teste end-to-end

### Revisão de Fase 6 (PM)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Critérios de aceite atendidos | PM | aguardando |
| CHANGELOG atualizado | DOC | aguardando |
| MEMORY (se aplicável) | DOC | aguardando |
| project-state.yaml atualizado | DOC | aguardando |
