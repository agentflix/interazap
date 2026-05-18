# Tasks: [Nome da Feature] (FEAT-NNN)

Feature doc: `.context/DOCS/FEATURES/[nome].md`
Status geral: 🔄 Em Execução | 0/N tasks concluídas

---

## FASE 1: PLANNING

### 1.1 — Feature doc

- [ ] **TASK-1.1.1** ⏳: Criar feature doc

  **T — Tarefa:** Criar feature doc completo para [nome da feature].

  **A — Arquivo:** `.context/DOCS/FEATURES/[nome].md`

  **C — Comportamento:**
  ANTES:
  - Feature não documentada

  DEPOIS:
  - Feature doc com todos os campos obrigatórios preenchidos

  **E — Evidência:**
  - [ ] Feature doc tem bounded context, complexidade, escopo e critérios de aceite
  - [ ] Flags de risco marcadas se aplicável
  - [ ] Aprovado por @PM

  **Status:** ⏳ Pendente

---

## FASE 3: BACKEND (api/)

### 3.1 — Database

- [ ] **TASK-3.1.1** ⏳: [Título da migration]

  **T — Tarefa:** [O que fazer]

  **A — Arquivo:** `api/database/migrations/YYYY_MM_DD_HHMMSS_[nome].php`

  **C — Comportamento:**
  ANTES:
  - [tabela/coluna não existe]

  DEPOIS:
  - [tabela/coluna existe com campos: ...]

  **E — Evidência:**
  - [ ] `php artisan migrate --pretend` executa sem erro
  - [ ] `composer analyse` retorna 0 erros
  - [ ] Migration tem `down()` implementado

  **Status:** ⏳ Pendente

---

### 3.2 — Domain

- [ ] **TASK-3.2.1** ⏳: [Título da entity]

  **T — Tarefa:** [O que fazer]

  **A — Arquivo:** `api/src/Domain/[Context]/Models/[Entity].php`

  **C — Comportamento:**
  ANTES:
  - [Entity não existe]

  DEPOIS:
  - [Entity com campos X, Y, Z, trait BelongsToTenant, casts definidos]

  **E — Evidência:**
  - [ ] Teste unitário `[Entity]Test::test_[critério]` passa
  - [ ] `composer analyse` retorna 0 erros
  - [ ] `BelongsToTenant` aplicado (se dados por tenant)

  **Status:** ⏳ Pendente

---

### Revisão de Fase 3 (@REVIEWER + @QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Domain Layer sem imports de infra | @REVIEWER | ⏳ |
| Migrations com up() e down() | @DBA | ⏳ |
| Pest: 0 falhas, coverage >= 80% | @QA | ⏳ |
| PHPStan: 0 erros | @QA | ⏳ |
| BelongsToTenant testado | @QA | ⏳ |

**Gate de Qualidade Fase 3:** ⏳ Pendente — `composer gate:all`

---

## FASE 5: FRONTEND (app/)

### 5.1 — Componentes

- [ ] **TASK-5.1.1** ⏳: [Título do componente]

  **T — Tarefa:** [O que fazer]

  **A — Arquivo:** `app/src/app/[path]/[componente].component.ts`

  **C — Comportamento:**
  ANTES:
  - [componente não existe / não tem funcionalidade X]

  DEPOIS:
  - [componente com funcionalidade X, template Y, evento Z]

  **E — Evidência:**
  - [ ] Vitest: `[Componente]Spec::should_[critério]` passa
  - [ ] ESLint: 0 warnings
  - [ ] Build: `pnpm --filter app build` sucesso

  **Status:** ⏳ Pendente

---

### Revisão de Fase 5 (@REVIEWER + @QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Componentes standalone | @REVIEWER | ⏳ |
| Vitest: 0 falhas, coverage >= 70% | @QA | ⏳ |
| ESLint: 0 warnings | @QA | ⏳ |
| Build limpo | @QA | ⏳ |

**Gate de Qualidade Fase 5:** ⏳ Pendente — `pnpm --filter app lint test build`

---

## Progresso Geral

| Fase | Tasks | Concluídas | Gate |
|------|-------|-----------|------|
| 1 — Planning | 1 | 0 | N/A |
| 3 — Backend | 2 | 0 | ⏳ |
| 5 — Frontend | 1 | 0 | ⏳ |
| **Total** | **4** | **0** | |
