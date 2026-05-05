# AGENTS.md — Fonte da Verdade do InteraZap

> Lido automaticamente pelo Claude Code via symlink CLAUDE.md.

---

## Identidade

- **Nome:** InteraZap
- **Descrição:** Plataforma SaaS multi-tenant de automação de WhatsApp com CRM integrado, gateway de integrações e IA para classificação e streaming de mensagens
- **Stack:** Laravel 12 (PHP 8.3) + NestJS 11 (TS 5.7) + Angular 19 (Ionic + Capacitor) + Electron 33 (Angular 20) + PostgreSQL 17 (pgvector) + Redis 7
- **Arquitetura:** DDD (Controller → DTO → Action → Resource) por bounded context
- **Workspaces:** `api/`, `gateway/`, `app/`, `electron/`, `landing/`, `infra/`, `observability/`
- **Repositório:** monorepo (pnpm workspaces + Composer)

---

## Regras Absolutas

1. Sempre responder em português brasileiro
2. Nunca apagar ou sobrescrever arquivos sem confirmação
3. Seguir estrutura DDD existente (`api/src/Domain/{Context}/...`)
4. Todo código novo DEVE ter testes (Pest no backend; Vitest no app; spec.ts no gateway)
5. Commits: Conventional Commits (pt-BR aceito)
6. Nunca expor segredos no código
7. Verificar feature doc em `.context/DOCS/FEATURES/` antes de implementar
8. Tasks seguem framework T.A.C.E
9. Workflow PREVC é obrigatório (`.context/WORKFLOW/PREVC.md`)
10. Gates de validação são inegociáveis (`.context/WORKFLOW/validation-flow.md`)
11. **Toda task concluída gera entrada em `.context/DOCS/CHANGELOG/`**
12. **Toda decisão relevante gera entrada em `.context/DOCS/MEMORY/`**
13. **`.context/ARCHITECTURE/project-state.yaml` é atualizado a cada feature concluída**
14. **Multi-tenancy:** toda query passa por `BelongsToTenant` (zero exceções não documentadas)
15. **UUID primary keys** em toda nova tabela
16. `declare(strict_types=1)` em todo arquivo PHP
17. Comunicação API ↔ Gateway via **Redis Streams idempotentes**
18. Frontends NUNCA acessam DB direto

---

## Mapa de Contexto

| Pasta | Propósito | Quando Consultar |
|-------|-----------|-----------------|
| `.claude/agents/` | Personas especializadas | Expertise por domínio |
| `.claude/commands/` | Slash commands | Workflows padronizados |
| `.claude/skills/` | Frameworks e métodos | T.A.C.E, decomposição |
| `.claude/hooks/` | Router automático | Roteamento de tarefas |
| `.context/ARCHITECTURE/` | Arquitetura, módulos, estado | Decisões estruturais |
| `.context/DOCS/FEATURES/` | Feature docs (humanos) | Antes de implementar |
| `.context/DOCS/TASKS/` | Tasks T.A.C.E (IA) | Durante implementação |
| `.context/DOCS/PRDS/` | Requisitos de produto | Requisitos de negócio |
| `.context/DOCS/CHANGELOG/` | **Registro diário de mudanças** | **Fase CONFIRM do PREVC** |
| `.context/DOCS/MEMORY/` | **Decisões e aprendizados** | **Antes de decidir qualquer coisa** |
| `.context/LAYOUT/` | Referências visuais | Tarefas de UI/UX |
| `.context/WORKFLOW/` | PREVC + Validation | Processo obrigatório |
| `api/` | Laravel 12 / DDD / PostgreSQL / Redis | Backend |
| `gateway/` | NestJS 11 / BullMQ / Redis Streams / Socket.io | Integrações externas e real-time |
| `app/` | Angular 19 + Ionic + Capacitor | Frontend web/mobile |
| `electron/` | Electron 33 + Angular 20 | Frontend desktop |
| `landing/` | Site marketing | Landing page |
| `infra/` | Ansible + nginx | IaC |
| `observability/` | Métricas/logs | Observabilidade |

---

## Bounded Contexts (API)

| Context | Responsabilidade |
|---------|------------------|
| `Ai` | OpenAI, classificação, embeddings (pgvector) |
| `Auth` | Sanctum, RBAC (Spatie) |
| `Billing` | Asaas (pagamentos brasileiros), webhooks |
| `Chat` | Mensageria WhatsApp multi-provedor |
| `Configuration` | Configs por tenant, feature flags |
| `CRM` | Pipeline, deals, leads, atendentes, tags |
| `Dashboard` | Visão consolidada |
| `Gateway` | Bridge para Gateway-NestJS via Redis Streams |
| `Platform` | Multi-tenancy, isolamento |
| `Reports` | Relatórios assíncronos |
| `Shared` | Kernel: traits, base classes, VOs |

---

## Workflow PREVC

```text
PLANNING → REVIEW → EXECUTION → VALIDATION → CONFIRM
```

| Fase | Responsável | Output | Registros |
|------|------------|--------|-----------|
| Planning | PM / ARCHITECT / DESIGNER | Feature doc | — |
| Review | REVIEWER / ARCHITECT | Aprovação → Tasks | — |
| Execution | DEV / BACKEND / GATEWAY / FRONTEND / DBA | Código + Testes | — |
| Validation | QA / REVIEWER | Gates passam | — |
| **Confirm** | **PM / DOC / GIT_COMMIT** | **Task done** | **CHANGELOG + MEMORY** |

> Detalhes: `.context/WORKFLOW/PREVC.md`

---

## CHANGELOG — Registro de Mudanças

- **Onde:** `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`
- **Quando:** Fase CONFIRM de cada task
- **O quê:** Registro FACTUAL — o que mudou, arquivos afetados, refs
- **Template:** `.context/DOCS/CHANGELOG/_TEMPLATE.md`

## MEMORY — Decisões e Aprendizados

- **Onde:** `.context/DOCS/MEMORY/YYYY-MM-DD-titulo.md`
- **Quando:** Sempre que uma decisão for tomada ou algo for aprendido
- **O quê:** Decisões com alternativas, aprendizados, armadilhas, insights
- **Template:** `.context/DOCS/MEMORY/_TEMPLATE.md`
- **REGRA:** Antes de tomar qualquer decisão técnica, consultar MEMORY primeiro

---

## Convenções

### PHP / Laravel (api/)
- `declare(strict_types=1)` em todos os arquivos
- phpDoc em classes e métodos públicos
- `final class` em Controllers, Actions, DTOs
- `$fillable` explícito (NUNCA `$guarded = []`)
- UUID primary keys
- Eager loading (sem N+1)
- Trait `BelongsToTenant` em Models multi-tenant
- `actingAs()` em testes Pest com tenant scope
- PHPStan L6 + Pint + Rector

### TypeScript / NestJS (gateway/)
- `strict: true`
- Módulos NestJS bem organizados
- Circuit breaker + retry exponencial em integrações externas
- Webhooks idempotentes via Redis (chave + TTL)
- HMAC em webhooks externos
- Logs estruturados (JSON)

### TypeScript / Angular (app/, electron/)
- Standalone components
- Signals para estado simples
- Control flow novo (`@if`, `@for`, `@switch`)
- Sem acesso direto a DB
- WebSocket via Socket.io (cliente do Gateway)

### Git
- Conventional Commits (pt-BR aceito): `feat(escopo): descrição`
- Escopos: `api`, `gateway`, `app`, `electron`, `landing`, `infra`, `db`, `ci`, `docs`, `repo`
- Branches: `feature/FEAT-NNN-descricao` | `fix/BUG-NNN-descricao`

---

## Agents

| Agent | Fase PREVC | Quando Usar |
|-------|-----------|-------------|
| ORCHESTRATOR | Todas | Coordenação de features complexas |
| PM | Planning, Confirm | Feature docs, escopo, fechamento |
| ARCHITECT | Planning, Review | Decisões DDD, contratos cross-workspace |
| REVIEWER | Review | Code review, doc review |
| BACKEND | Execution | Laravel 12 / DDD / `api/` |
| GATEWAY | Execution | NestJS 11 / `gateway/` |
| FRONTEND | Execution | Angular 19 / Ionic / Electron |
| DEV | Execution | Cross-workspace |
| DBA | Execution | PostgreSQL / pgvector / Redis |
| QA | Validation | Gates, testes |
| DEBUG | Execution | Bugs |
| DOC | Confirm | CHANGELOG, MEMORY, docs |
| GIT_COMMIT | Confirm | Commits semânticos |
| DESIGNER | Planning | UI/UX para App e Electron |

---

## Framework T.A.C.E

| Letra | Significado | Pergunta |
|-------|-------------|----------|
| **T** | Tarefa | O QUE fazer? |
| **A** | Arquivo | ONDE fazer? |
| **C** | Comportamento | COMO funciona (antes→depois)? |
| **E** | Evidência | COMO SABER que está pronto? |

> Skill: `.claude/skills/tace-framework/tace-framework.md`

---

## Comandos Rápidos

```bash
# Backend (Laravel)
cd api && composer gate:all                    # format + analyse + test + refactor

# Gateway (NestJS)
pnpm --filter gateway test && pnpm --filter gateway build

# App (Angular)
pnpm --filter app test && pnpm --filter app build

# Electron
pnpm --filter electron build
```

---

## Slash Commands PREVC

| Comando | Fase | Uso |
|---------|------|-----|
| `/new-feature [nome]` | Planning | Criar feature doc |
| `/review-feature [nome]` | Review | Validar feature doc |
| `/decompose [nome]` | Review | Gerar tasks T.A.C.E |
| `/validate-tasks [nome]` | Review | Validar qualidade das tasks |
| `/implement-task [f] [T]` | Execution | Implementar (ex: TASK-3.2.1) |
| `/validate [f] [T]` | Validation | Rodar gates |
| `/confirm-task [f] [T]` | Confirm | Fechar + CHANGELOG + MEMORY |
| `/feature-status [nome]` | Qualquer | Ver progresso |
| `/review-phase [N]` | Review | Revisão de fase |
| `/validate-phase [N]` | Validation | Gate de fase |
| `/confirm-phase [N]` | Confirm | Fechar fase |
