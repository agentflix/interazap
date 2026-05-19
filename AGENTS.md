# AGENTS.md — InteraZap

**InteraZap** — SaaS multi-tenant · gestão de atendimento via WhatsApp e canais.

| | |
|---|---|
| Backend | PHP 8.3 + Laravel 12 (Octane · Horizon · Reverb · Sanctum) — `api/` |
| Gateway | TypeScript + NestJS 11 (BullMQ · WebSocket) — `gateway/` |
| Frontend | TypeScript + Angular 17 + Capacitor (iOS/Android) — `app/` |
| Database | PostgreSQL 17 + pgvector · Redis 7 |
| Arquitetura | DDD multi-tenant — `Controller → DTO → Action → Resource` |
| Monorepo | pnpm workspaces (app, gateway) |

## Regras Absolutas

1. `declare(strict_types=1)` em **todo** PHP
2. `final class` em Controllers, Actions e DTOs
3. UUID como PK — **nunca** auto-increment
4. `$fillable` explícito — **NUNCA** `$guarded = []`
5. Eager loading obrigatório — **NUNCA** N+1
6. Todo Model com dados de tenant usa `BelongsToTenant`
7. Todo Controller action chama `$this->authorize()`
8. `composer gate:all` antes de qualquer commit (API)
9. Webhook ACK < 150ms no Gateway — processamento via BullMQ
10. **REVIEWER executa `code-review-confiavel` ao final de toda task**

## Mapa de Contexto

| Caminho | Conteúdo |
|---|---|
| `.context/agents/` | ORCHESTRATOR, PLANNER, BUILDER, REVIEWER |
| `.context/skills/` | PREVEC + code-review-confiavel + brainstorming |
| `.context/ARCHITECTURE/` | Diagramas, módulos, dependências, brain |
| `.context/DESIGN/` | Wireframes, specs UI, ux-flows |
| `.context/DOCS/FEATURES/` | Feature docs aprovados |
| `.context/DOCS/TASKS/` | Tasks T.A.C.E |
| `.context/DOCS/PRDS/` | PRDs |
| `.context/DOCS/MEMORY/` | Decisões, aprendizados, armadilhas |
| `.context/WORKFLOW/` | PREVC.md + validation-flow.md |
| `.context/.session/` | Sessions ativos |

> Decisão técnica → consultar `project-brain.yaml` + `architecture.md` primeiro.

## Agents

| Agent | Fase PREVC | Absorve |
|---|---|---|
| ORCHESTRATOR | Todas | Coordenação — nunca implementa |
| PLANNER | Pré-Planning + Planning + Review | BRANDING + PM + ARCHITECT + DESIGNER |
| BUILDER | Execution | BACKEND + GATEWAY + FRONTEND + DBA + DEBUG |
| REVIEWER | Review + Validation + Confirm | REVIEWER + DOC + GIT_COMMIT |

## Workflow PREVC

```
/prevec-new-plan [ideia]                  → PRD aprovado
/prevec-decompose-plan [prd]              → Feature doc
/prevec-decompose-task [feature]          → Tasks T.A.C.E
/prevec-execute-task [feature] TASK-X     → Implementação
/prevec-review-execution [feature] TASK-X → Code review (subagent)
/prevec-finalize-execution [feature] TASK-X → CONFIRM + commit
```

> Task sem REVIEWER aprovado não avança para CONFIRM.

## Architecture

> Todos em `.context/ARCHITECTURE/`

| Arquivo | Descrição |
|---|---|
| `architecture.md` | Diagrama de camadas |
| `modules.md` | Mapa de módulos |
| `user-flow.md` | Fluxos do usuário |
| `modules.yaml` | Módulos/bounded contexts |
| `dependencies.yaml` | Regras de dependência |
| `project-state.yaml` | Stack, métricas, status |
| `project-brain.yaml` | Identidade, decisões, regras |
| `context-version.yaml` | Versionamento |
| `context-snapshot.md` | Cache lean |

## Design · Memory

> **Frontend:** consultar `.context/DESIGN/` antes de qualquer componente ou fluxo.
> **Memory:** REVIEWER registra em `.context/DOCS/MEMORY/` ao detectar decisão técnica.

## T.A.C.E

- **T** — Tarefa: o que fazer (1 frase)
- **A** — Arquivo: path exato — nunca "vários arquivos"
- **C** — Comportamento: antes → depois
- **E** — Evidência: comando verificável
