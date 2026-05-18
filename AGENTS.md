# AGENTS.md — Fonte da Verdade do InteraZap

> Lido automaticamente pelo Claude Code via symlink CLAUDE.md.

---

## Identidade

- **Nome:** InteraZap
- **Descrição:** Plataforma SaaS multi-tenant de automação de WhatsApp com CRM, AI autopilot, billing e atendimento em tempo real.
- **Stack:** Laravel 12 (api) + NestJS 11 (gateway) + Angular 20 + Capacitor + Electron
- **Database:** PostgreSQL
- **Arquitetura:** DDD + Clean Architecture (Domain → Application → Infrastructure → HTTP)
- **Infra:** GitHub Actions, Docker, Redis, ASAAS, UazAPI, Z-API, OpenAI

---

## Regras Absolutas

1. Sempre responder em português brasileiro
2. Nunca apagar ou sobrescrever sem confirmação explícita
3. Seguir a estrutura de pastas e convenções de cada workspace
4. Todo código novo DEVE ter testes (Pest, Vitest ou `spec.ts`)
5. Commits: Conventional Commits em pt-BR, sem push automático
6. Nunca expor segredos, tokens ou credenciais no código
7. Verificar feature doc em `.context/DOCS/FEATURES/` antes de implementar
8. Tasks seguem o framework T.A.C.E (Tarefa, Arquivo, Comportamento, Evidência)
9. Workflow PREVC é obrigatório — ver `.context/WORKFLOW/PREVC.md`
10. Gates de validação são inegociáveis — ver `.context/WORKFLOW/validation-flow.md`
11. **Toda task concluída → entrada em `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`**
12. **Toda decisão relevante → entrada em `.context/DOCS/MEMORY/`**
13. **`.context/ARCHITECTURE/project-state.yaml` atualizado a cada feature concluída**
14. Multi-tenancy: `BelongsToTenant` obrigatório + teste de isolamento entre tenants
15. Domain Layer (api) NÃO importa Laravel nem dependências de infraestrutura
16. Ordem padrão cross-workspace: DBA → BACKEND → GATEWAY → FRONTEND

---

## Mapa de Contexto

| Pasta | Propósito | Quando Consultar |
|-------|-----------|-----------------|
| `.context/AGENTS/` | Personas especializadas (16 agents) | Expertise por domínio |
| `.context/SKILLS/` | Frameworks e regras por workspace | Antes de implementar em cada workspace |
| `.claude/commands/` | Slash commands PREVC | Workflows padronizados |
| `.claude/hooks/` | Router automático de tarefas | Roteamento por tipo de demanda |
| `.context/ARCHITECTURE/` | Arquitetura, módulos, estado do projeto | Decisões estruturais, antes de nova feature |
| `.context/DOCS/FEATURES/` | Feature docs (funcional) | Antes de qualquer implementação |
| `.context/DOCS/TASKS/` | Tasks T.A.C.E decompostas | Durante implementação |
| `.context/DOCS/PRDS/` | Product Requirements Documents | Requisitos de produto detalhados |
| `.context/DOCS/CHANGELOG/` | **Registro diário de mudanças** | **Fase CONFIRM do PREVC** |
| `.context/DOCS/MEMORY/` | **Decisões e aprendizados persistentes** | **Antes de qualquer decisão técnica** |
| `.context/LAYOUT/` | Referências visuais e wireframes | Tarefas de UI/UX |
| `.context/WORKFLOW/` | PREVC + Validation Flow | Processo obrigatório |

---

## Workflow PREVC

```text
PLANNING → REVIEW → EXECUTION → VALIDATION → CONFIRM
```

| Fase | Responsável | Output | Registros |
|------|------------|--------|-----------|
| Planning | PM + ARCHITECT | Feature doc | — |
| Review | REVIEWER + ARCHITECT | Aprovação + Tasks T.A.C.E | — |
| Execution | BACKEND / GATEWAY / FRONTEND / DBA | Código + Testes | — |
| Validation | QA + REVIEWER | Gates passam | — |
| **Confirm** | **PM + DOC + GIT_COMMIT** | **Task/Feature fechada** | **CHANGELOG + MEMORY + commit** |

> Detalhes completos: `.context/WORKFLOW/PREVC.md`

---

## CHANGELOG — Registro de Mudanças

- **Onde:** `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`
- **Quando:** Fase CONFIRM de cada task
- **O quê:** Registro FACTUAL — o que mudou, arquivos, refs
- **Template:** `.context/DOCS/CHANGELOG/_TEMPLATE.md`

## MEMORY — Decisões e Aprendizados

- **Onde:** `.context/DOCS/MEMORY/YYYY-MM-DD-titulo.md`
- **Quando:** Qualquer decisão técnica, aprendizado ou armadilha
- **O quê:** Decisão com alternativas, aprendizado, armadilha, insight
- **Template:** `.context/DOCS/MEMORY/_TEMPLATE.md`
- **REGRA:** Consultar MEMORY antes de qualquer decisão técnica

---

## Convenções por Workspace

### `api/` — Laravel 12 / PHP 8.2+
- PSR-4 autoloading, namespaces: `App\`, `Domain\`, `Shared\`
- Formatação: Laravel Pint (`composer format`)
- Análise estática: PHPStan L6 via Larastan (`composer analyse`)
- Testes: Pest com `--coverage` (`composer test`)
- Refactoring: Rector (`composer refactor`)
- Gate completo: `composer gate:all`
- Classes: PascalCase | Métodos: camelCase | Constantes: UPPER_SNAKE_CASE

### `gateway/` — NestJS 11 / TypeScript
- Lint: ESLint + Prettier (`pnpm --filter gateway lint`)
- Testes: Jest (`pnpm --filter gateway test`)
- Build: `pnpm --filter gateway build`
- Path aliases: `@app/*`, `@shared/*`

### `app/` — Angular 20 / TypeScript
- Lint: ESLint (`pnpm --filter app lint`)
- Testes: Vitest (`pnpm --filter app test`)
- Build: `pnpm --filter app build`
- Componentes standalone; Services para lógica de negócio

### `electron/`
- Build: `pnpm --filter electron build`

---

## Bounded Contexts

| Contexto | Path (api) | Responsabilidade |
|----------|-----------|-----------------|
| Ai | `src/Domain/Ai/` | Autopilot, embeddings, tools, RAG |
| Auth | `src/Domain/Auth/` | Autenticação, autorização, tokens |
| Billing | `src/Domain/Billing/` | ASAAS, invoices, planos |
| Chat | `src/Domain/Chat/` | WhatsApp, tickets, quick answers |
| Configuration | `src/Domain/Configuration/` | Config do tenant, sistema |
| CRM | `src/Domain/CRM/` | Companies, contacts, proposals, tags |
| Dashboard | `src/Domain/Dashboard/` | Analytics, métricas |
| Gateway | `src/Domain/Gateway/` | Circuit breaker, provider adapters |
| Platform | `src/Domain/Platform/` | Multi-tenant, workspace |
| Reports | `src/Domain/Reports/` | Exports, analytics |
| Shared | `src/Domain/Shared/` | DTOs, events, concerns cross-cutting |

---

## Agents

| Agent | Fase PREVC | Quando Usar |
|-------|-----------|-------------|
| ORCHESTRATOR | Todas | Coordenar features complexas multi-workspace |
| PM | Planning, Confirm | Feature docs, escopo, fechamento |
| PLAN | Planning | Planejar abordagem técnica e escopo detalhado |
| ARCHITECT | Planning, Review | Decisões de arquitetura DDD |
| REVIEWER | Review | Code review, doc review, checklists |
| BACKEND | Execution | Laravel 12, PHP, DDD, Pest |
| GATEWAY | Execution | NestJS 11, Socket.io, Jest |
| FRONTEND | Execution | Angular 20, Capacitor, Vitest |
| DBA | Execution | PostgreSQL, migrations, schema |
| DEV | Execution | Cross-workspace (DBA→BACKEND→GATEWAY→FRONTEND) |
| QA | Validation | Gates obrigatórios, coverage, multi-tenant isolation |
| DEBUG | Execution | Investigação de bugs |
| DOC | Confirm | CHANGELOG, MEMORY, documentação |
| GIT_COMMIT | Confirm | Commits semânticos Conventional Commits pt-BR |
| DESIGNER | Planning | UI/UX, wireframes em `.context/LAYOUT/` |
| VIBE-CODER | Execution | Tarefas criativas e exploratórias |

> Agents em: `.context/AGENTS/`

---

## Framework T.A.C.E

| Letra | Significado | Pergunta |
|-------|-------------|----------|
| **T** | Tarefa | O QUE fazer? |
| **A** | Arquivo | ONDE fazer? (path exato) |
| **C** | Comportamento | COMO funciona (antes → depois)? |
| **E** | Evidência | COMO SABER que está pronto? (critérios verificáveis) |

> Skill: `.claude/skills/tace-framework/tace-framework.md`

---

## Sub-project AGENTS.md

- Backend: `api/AGENTS.md`
- Gateway: `gateway/AGENTS.md`
