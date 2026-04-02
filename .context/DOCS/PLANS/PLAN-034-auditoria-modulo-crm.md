# PLAN-034-auditoria-modulo-crm — Auditoria do Módulo CRM

## Objetivo

Auditar o módulo CRM em todas as camadas (Backend + Frontend) em busca de erros, melhorias de performance, refatoração, segurança, economia de fluxo e token. Corrigir issues críticos e padronizar o código conforme AGENTS.md.

## Módulo relacionado

CRM

## PRD relacionado (se existir): N/A

## Escopo

### Incluído

- Auditoria de segurança (autorização faltante em controllers)
- Correção de memory leaks (subscriptions sem takeUntilDestroyed)
- Padronização de padrões Angular 20+ (input signals, inject())
- Refatoração de componentes grandes (negotiations ~1000 linhas)
- Eliminação de type safety issues (unknown types)
- Consolidação de serviços duplicados (funnel.service vs crm-funnel.service)
- Cobertura de testes em componentes sem spec

### Excluído

- Gateway CRM (não existe módulo CRM no gateway)
- Novas features ou funcionalidades
- Mudanças em migrations existentes
- Redesign visual de componentes

---

## Evidências da Codebase

### Backend (CRM) — Nota: A (97%)

**Estrutura:** 157+ artefatos | 18 controllers | 25 actions | 22 models | 19 DTOs | 33 FormRequests | 12 policies

> **Nota:** `CrmProductController` e `CrmReasonLossController` usam o trait `HandlesCrudOperations`, que já injeta `$this->authorize()` internamente em `crudIndex()`, `crudStore()`, `crudShow()`, `crudUpdate()`, `crudDestroy()`. Portanto, NÃO faltam authorize nesses controllers.

#### 🟡 MENOR — Authorization Missing (1 método em 1 controller)

| Controller                    | Método       | Linha | Issue                                    |
| ----------------------------- | ------------ | ----- | ---------------------------------------- |
| `CRMProductListAllController` | `__invoke()` | L22   | Sem `$this->authorize()` — não usa trait |

#### ✅ Backend OK

- 100% `declare(strict_types=1)`
- 100% `final class` em Controllers, Actions, DTOs
- 100% UUID primary keys
- 100% `BelongsToTenant` em todos os 22 models
- 100% `$fillable` explícito (zero `$guarded = []`)
- 100% DTOs `readonly` com `fromRequest()` e `fromArray()`
- 100% Eager loading (sem N+1)
- 100% phpDoc em classes e métodos públicos
- 23 arquivos de teste

---

### Frontend (CRM) — Nota: 7.5/10

**Estrutura:** 13 módulos de página | 7 services | 50+ componentes

#### 🔴 CRÍTICO — Memory Leaks (subscriptions sem cleanup)

| Arquivo                                    | Linhas                   | Qtd | Severidade |
| ------------------------------------------ | ------------------------ | --- | ---------- |
| `proposals/proposal-list/proposal-list.ts` | 69, 87, 133, 147, 154    | 5   | 🔴 CRÍTICO |
| `proposals/proposal-form/proposal-form.ts` | 174                      | 1   | 🔴 CRÍTICO |
| `opening-hours/opening-hours.ts`           | 285, 315                 | 2   | 🔴 CRÍTICO |
| `negotiations/negotiations.ts`             | 294, 298, 318, 419, 510+ | 20+ | 🟡 MÉDIO   |
| `negotiation-show/negotiation-show.ts`     | 258, 302, 397, 412, 445+ | 10+ | 🟡 MÉDIO   |
| `negotiation-tasks/negotiation-tasks.ts`   | 98, 131, 190, 234        | 4   | 🟡 MÉDIO   |

#### 🟡 Anti-patterns

| Issue               | Arquivo                                          | Detalhes                             |
| ------------------- | ------------------------------------------------ | ------------------------------------ |
| `@Input` legado     | `proposal-list.ts`                               | Usar `input()` signal                |
| `OnInit` legado     | `proposal-list.ts`                               | Migrar para `effect()` / constructor |
| `unknown` type      | `contact-form.ts` L256                           | Tipar `FormArray` corretamente       |
| Componente grande   | `negotiations.ts` ~1000 linhas                   | Decompor em sub-componentes          |
| Serviços duplicados | `funnel.service.ts` vs `crm-funnel.service.ts`   | Consolidar                           |
| Serviços duplicados | `contact.service.ts` vs `crm-contact.service.ts` | Verificar e consolidar               |
| Sem spec file       | `companies/`                                     | Criar testes                         |

#### ✅ Frontend OK

- 100% `ChangeDetectionStrategy.OnPush`
- 100% `inject()` (exceto proposal-list)
- 100% `track` em `@for` loops
- 95%+ uso de shared components (af-\*)
- Zero uso de HTML raw (`<table>`, `<button>` direto)
- Zero hardcoded colors (usa design tokens)
- States loading/empty/error implementados
- Lazy loading em todas as rotas CRM

---

### Gateway (CRM) — N/A

Não existe módulo CRM no Gateway. Domínios existentes: `ai/`, `billing/`, `chat/`, `internal/`, `realtime/`, `webhooks/`.

---

## Etapas propostas

### Entrega 1 — Segurança: Authorization Backend (XS)

1. Adicionar `$this->authorize('viewAny', CRMProduct::class)` no `__invoke()` de `CRMProductListAllController`
2. Verificar/atualizar testes de RBAC para cobrir o endpoint corrigido

### Entrega 2 — Memory Leaks: Proposal Components (S)

1. Refatorar `proposal-list.ts`: injetar `DestroyRef`, adicionar `takeUntilDestroyed()` em 5 subscriptions
2. Migrar `@Input` → `input()` signal em `proposal-list.ts`
3. Migrar `OnInit` → constructor/effect em `proposal-list.ts`
4. Refatorar `proposal-form.ts`: injetar `DestroyRef`, pipe `takeUntilDestroyed()` na L174

### Entrega 3 — Memory Leaks: Negotiations & Detail (S)

1. Auditar todas as subscriptions em `negotiations.ts`, adicionar `takeUntilDestroyed()` onde falta
2. Auditar todas as subscriptions em `negotiation-show.ts`, padronizar cleanup
3. Auditar `negotiation-tasks.ts`, adicionar `takeUntilDestroyed()` nas L98, 131, 190, 234

### Entrega 4 — Memory Leaks: Opening Hours (XS)

1. Adicionar `takeUntilDestroyed()` nas subscriptions L285, L315 de `opening-hours.ts`

### Entrega 5 — Type Safety & Refatoração (S)

1. Corrigir `unknown` type em `contact-form.ts` L256 — tipar corretamente o FormArray
2. Investigar e consolidar `funnel.service.ts` vs `crm-funnel.service.ts`
3. Investigar e consolidar `contact.service.ts` vs `crm-contact.service.ts`
4. Remover imports não utilizados detectados

### Entrega 6 — Testes Faltantes (S)

1. Criar spec para `companies/` (crm-companies.spec.ts)
2. Verificar cobertura dos specs existentes vs funcionalidades

### Entrega 7 — Validação Final (XS)

1. Rodar `composer gate:all` em api/
2. Rodar `pnpm run gate:all` em app/
3. Validar zero regressões

---

## Entregas derivadas

**Entregas:** 7 | **Tasks:** 15

| Entrega | Descrição                        | Tasks                       | Esforço | Status      |
| ------- | -------------------------------- | --------------------------- | ------- | ----------- |
| 1       | Segurança: Authorization Backend | TASK-034.1.1 - TASK-034.1.2 | XS      | done        |
| 2       | Memory Leaks: Proposals          | TASK-034.2.1 - TASK-034.2.4 | S       | done        |
| 3       | Memory Leaks: Negotiations       | TASK-034.3.1 - TASK-034.3.3 | S       | n/a (já ok) |
| 4       | Memory Leaks: Opening Hours      | TASK-034.4.1                | XS      | done        |
| 5       | Type Safety & Refatoração        | TASK-034.5.1 - TASK-034.5.4 | S       | done        |
| 6       | Testes Faltantes                 | TASK-034.6.1 - TASK-034.6.2 | S       | done        |
| 7       | Validação Final (Gates)          | TASK-034.7.1 - TASK-034.7.3 | XS      | done        |

## Arquivos a Modificar

### Backend (Laravel)

| Arquivo                     | Ação      | Caminho                                                               |
| --------------------------- | --------- | --------------------------------------------------------------------- |
| CRMProductListAllController | modificar | `api/src/Domain/CRM/Http/Controllers/CRMProductListAllController.php` |
| RbacCrmControllerTest       | modificar | `api/tests/Feature/RbacCrmControllerTest.php`                         |

### Frontend (Angular)

| Arquivo               | Ação               | Caminho                                                                      |
| --------------------- | ------------------ | ---------------------------------------------------------------------------- |
| proposal-list.ts      | modificar          | `app/src/app/pages/crm/proposals/proposal-list/proposal-list.ts`             |
| proposal-form.ts      | modificar          | `app/src/app/pages/crm/proposals/proposal-form/proposal-form.ts`             |
| opening-hours.ts      | modificar          | `app/src/app/pages/crm/opening-hours/opening-hours.ts`                       |
| negotiations.ts       | modificar          | `app/src/app/pages/crm/negotiations/negotiations.ts`                         |
| negotiation-show.ts   | modificar          | `app/src/app/pages/crm/negotiation-show/negotiation-show.ts`                 |
| negotiation-tasks.ts  | modificar          | `app/src/app/pages/crm/negotiation-tasks/negotiation-tasks.ts`               |
| contact-form.ts       | modificar          | `app/src/app/pages/crm/contacts/components/contact-form/crm-contact-form.ts` |
| funnel.service.ts     | investigar/remover | `app/src/app/core/services/funnel.service.ts`                                |
| contact.service.ts    | investigar/remover | `app/src/app/core/services/contact.service.ts`                               |
| crm-companies.spec.ts | criar              | `app/src/app/pages/crm/companies/crm-companies.spec.ts`                      |

## Tarefas Derivadas para Execução Paralela

| Task            | Descrição                                            | Agente    | Paralelo com    |
| --------------- | ---------------------------------------------------- | --------- | --------------- |
| TASK-034.1 (BE) | Authorization missing em CRMProductListAllController | @BACKEND  | TASK-034.2 (FE) |
| TASK-034.2 (FE) | Memory leaks em proposals                            | @FRONTEND | TASK-034.1 (BE) |
| TASK-034.3 (FE) | Memory leaks em negotiations                         | @FRONTEND | TASK-034.4 (FE) |
| TASK-034.4 (FE) | Memory leaks em opening-hours                        | @FRONTEND | TASK-034.3 (FE) |
| TASK-034.5 (FE) | Type safety & consolidação                           | @FRONTEND | —               |
| TASK-034.6 (FE) | Testes faltantes                                     | @QA       | —               |
| TASK-034.7      | Validação final (gates)                              | @QA       | —               |

## Skills Obrigatórias (Leitura antes da implementação)

| Skill                                        | Quando usar                       |
| -------------------------------------------- | --------------------------------- |
| `.claude/skills/design/SKILL.md`             | Se tocar em componentes visuais   |
| `.claude/skills/frontend-flow/SKILL.md`      | Todas as tasks de frontend        |
| `.github/skills/angular-architect/SKILL.md`  | Refatoração Angular (signals, DI) |
| `.github/skills/coding-guidelines/SKILL.md`  | Toda implementação                |
| `.github/skills/laravel-specialist/SKILL.md` | Tasks de backend (authorization)  |

## Validação e Gates

- [ ] Backend: `composer gate:all` em api/
- [ ] Frontend: `pnpm run gate:all` em app/
- [ ] Testes RBAC passando para endpoints corrigidos
- [ ] Zero memory leaks em proposal-list, proposal-form, opening-hours
- [ ] Zero `unknown` types em contact-form

## Riscos e dependências

### Riscos

| Risco                                                             | Probabilidade | Impacto | Mitigação                                    |
| ----------------------------------------------------------------- | ------------- | ------- | -------------------------------------------- |
| Regressão ao adicionar authorize() em CRMProductListAllController | Baixa         | Baixo   | Apenas 1 endpoint; testes RBAC existentes    |
| Consolidação de services quebra imports                           | Média         | Médio   | Buscar todos os imports antes de remover     |
| Refatoração de negotiations.ts (1000 linhas)                      | Média         | Alto    | Focar só em memory leaks, não decompor agora |

### Dependências

- Policies já existem para CRMProduct e CRMReasonLoss (apenas authorize() falta)
- DestroyRef disponível no Angular 20+
- Shared components já cobrem 95%+ do UI

## Estimativa

| Item                          | Valor                             |
| ----------------------------- | --------------------------------- |
| Complexidade                  | Média                             |
| Camadas afetadas              | Backend / Frontend                |
| Migrações necessárias         | Não                               |
| Impacto em módulos existentes | Baixo (correções internas ao CRM) |
