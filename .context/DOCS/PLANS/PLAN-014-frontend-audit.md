# PLAN-014-frontend-audit — Angular 20 Code Audit

## Objetivo

Realizar auditoria técnica completa do código frontend Angular (`app/src/`) em 4 dimensões: (1) Código para reutilização, (2) Oportunidades de refatoração, (3) Bugs e erros, (4) Código morto. Resultado: relatório priorizado com findings categorizados por severidade.

## Módulo relacionado

**Frontend** — Angular 20 / TypeScript 5.9 (`app/src/`)

## PRD relacionado

N/A — tarefa de auditoria de código existente.

## Escopo

### Incluído

- 569 arquivos TypeScript de produção em `app/src/`
- 56 arquivos HTML (templates)
- 14 feature modules: `admin`, `ai`, `auth`, `billing`, `chat`, `configuration`, `crm`, `dashboard`, `platform`, `public`, `reports`, `settings`, `ui-kit`, `welcome`
- Todas as camadas: pages, components, services, models, guards, interceptors, directives, pipes
- Arquivos `*.ts`, `*.html`, `*.scss`

### Excluído

- `node_modules/`, `dist/`, `.angular/`, `.vscode/`
- Arquivos de teste (`*.spec.ts`)
- Arquivos de configuração (`tsconfig.json`, `angular.json`, etc.)
- Boilerplate CLI (`main.ts`, `app.component.ts`)

## Etapas propostas

1. **Partition files** — dividir 569 arquivos em 4 subconjuntos disjuntos (150-160 arquivos cada)
2. **Análise Paralela por Partition** — 4 agentes Explore, cada um cobrindo todas as 4 dimensões nos seus arquivos
3. **Consolidação de achados** — merge por file+line, sem duplicação entre agentes
4. **Geração do relatório** — formato estruturado com métricas e roadmap
5. **Validação do relatório** — revisão por @REVIEWER e @QA

## Agente Strategy — Parallel Execution (Disjoint File Sets)

| Agente  | Partição                               | Arquivos | Dimensões                                    |
| ------- | -------------------------------------- | -------- | -------------------------------------------- |
| Agent A | admin + auth + ai + billing            | ~140     | Dead Code + Reusability + Refactoring + Bugs |
| Agent B | chat + configuration + crm + dashboard | ~140     | Dead Code + Reusability + Refactoring + Bugs |
| Agent C | platform + public + reports + settings | ~140     | Dead Code + Reusability + Refactoring + Bugs |
| Agent D | ui-kit + welcome + core + shared       | ~140     | Dead Code + Reusability + Refactoring + Bugs |

**Cada agente cobre TODAS as 4 dimensões** nos seus arquivos designados. Overlap entre agentes impossível (partições disjuntas).

## Dimensões de Auditoria

### Dimensão 1: Dead Code

- Unused exports (tsc --noUnusedLocals como complemento)
- Unused imports
- Orphan components (declarados mas nunca usados em template ou createComponent)
- Unused route definitions
- Dead SCSS (definido mas nunca aplicado)
- Bare `Subject` sem cleanup

### Dimensão 2: Reusability

- Copy-paste blocks (similar logic in 3+ locations)
- Similar components (>70% structural overlap)
- Repeated HTTP patterns (CRUD boilerplate)
- Repeated form definitions
- Repeated validation logic
- Repeated state management patterns

### Dimensão 3: Refactoring (Angular 20 patterns)

- `*ngIf`/`*ngFor` → `@if`/`@for` (new control flow)
- Missing `track` in `@for`
- Missing `OnPush` change detection
- Missing `takeUntilDestroyed` em subscriptions
- Missing `inject()` (preferido sobre constructor injection)
- Raw `<table>`, `<button>`, `<input>` (deve usar shared components)
- Hardcoded state colors (deve usar design tokens)
- `any` ou `unknown` types
- Missing JSDoc on interfaces/exported functions
- `async` pipe overuse vs signals
- `::ng-deep` usage
- God components (>500 lines)
- Overly complex templates (>10 directives)

### Dimensão 4: Bugs & Errors

- Memory leaks (subscriptions sem unsubscribe)
- Unhandled Promise rejections
- Null/undefined access sem optional chaining
- Missing error handling em HTTP calls
- Race conditions em subscriptions
- Unsafe URL bindings (DomSanitizer)
- Route guard returns false sem redirect
- Form validation bypass

## Findings Schema (output padronizado)

Cada finding segue este formato:

````md
### [FE-{DOMAIN}-{NUMBER}] Finding Title

| Field        | Value                                        |
| ------------ | -------------------------------------------- |
| **Severity** | CRITICAL / HIGH / MEDIUM / LOW               |
| **Category** | Dead Code / Reusability / Refactoring / Bugs |
| **File**     | `app/src/app/...`                            |
| **Line(s)**  | N-N                                          |
| **Effort**   | XS / S / M / L / XL                          |
| **Pattern**  | [pattern-id]                                 |
| **Rule**     | [AGENTS.md rule violated]                    |

**Description:** ...
**Current Code:** `[code]`
**Remediation:** `[fix]`
````

## Tasks derivadas

| Task             | Descrição                                                     | Agente   | Status  |
| ---------------- | ------------------------------------------------------------- | -------- | ------- |
| TASK-FE-AUDIT-01 | Agent A (admin+auth+ai+billing) — all 4 dimensions            | Explore  | pending |
| TASK-FE-AUDIT-02 | Agent B (chat+configuration+crm+dashboard) — all 4 dimensions | Explore  | pending |
| TASK-FE-AUDIT-03 | Agent C (platform+public+reports+settings) — all 4 dimensions | Explore  | pending |
| TASK-FE-AUDIT-04 | Agent D (ui-kit+welcome+core+shared) — all 4 dimensions       | Explore  | pending |
| TASK-FE-AUDIT-05 | Consolidar + gerar relatório final                            | DEV      | pending |
| TASK-FE-AUDIT-06 | Revisão do relatório por @REVIEWER                            | REVIEWER | pending |
| TASK-FE-AUDIT-07 | Validação QA                                                  | QA       | pending |

## Riscos e mitigação

| Risco                               | Probabilidade | Impacto | Mitigação                                                 |
| ----------------------------------- | ------------- | ------- | --------------------------------------------------------- |
| Overlapping findings entre agentes  | Baixa         | Baixa   | Partições disjuntas — cada arquivo em exatamente 1 agente |
| Dead code false positives           | Alta          | Média   | tsc --noUnusedLocals como filtro complementar             |
| God components mal classificados    | Média         | Média   | Linha >500 ng; validação manual em疑似                    |
| AGENTS.md compliance não verificada | Média         | Alta    | Checklist AGENTS.md em cada dimensão                      |

## Estimativa

| Item                          | Valor                   |
| ----------------------------- | ----------------------- |
| Complexidade                  | Crítica                 |
| Camadas afetadas              | Frontend (única camada) |
| Migrações necessárias         | Não                     |
| Impacto em módulos existentes | Não (relatório apenas)  |
| Arquivos analisados           | 569 TS + 56 HTML        |
| Total de achados estimado     | 100+                    |

## Métricas do Audit (output esperado)

| Severidade | Estimativa | Sprint   |
| ---------- | ---------- | -------- |
| CRITICAL   | 3-5        | Sprint 1 |
| HIGH       | 20-25      | Sprint 2 |
| MEDIUM     | 35-45      | Sprint 3 |
| LOW        | 40-50      | Sprint 4 |
| **TOTAL**  | **100+**   | —        |
