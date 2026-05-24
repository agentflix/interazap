# InteraZap — AI-First + PREVC V7

**Stack:** Laravel 12 (api) · NestJS 11 (gateway) · Angular 20 (app) · PostgreSQL 17 · Redis 7
**Arquitetura:** Microservices — Presentation → Gateway → Domain/Application → Infrastructure

## 🚫 Regras de Negócio

- Gateway nunca acessa PostgreSQL diretamente — sempre via api REST
- Migrations somente em api/ via `php artisan make:migration`
- BullMQ queues somente em gateway/
- PSR-12 (PHP) · Angular Style Guide (TS) · Conventional Commits (git)
- Tenant isolation obrigatória em toda query
- Testes oficiais da `api/` devem usar Pest com `--parallel` e `--exclude-testsuite=E2E` (via `composer test` e `composer gate:all`); E2E roda separado com `composer test:e2e`
- Durante desenvolvimento em `api/`, IA pode usar `composer analyse:changed` (ou `composer gate:fast`) para feedback rápido; antes de concluir deve executar `composer gate:all`

## ⚙️ Regras de Processo

- REVIEWER executa `code-review-confiavel` ao final de **toda** task — sem exceção
- Todo agent mostra o próximo comando com argumentos reais ao final de cada ação concluída
- Testes por task removidos — testes rodam no `/prevec-phase-close` ao final de cada fase
- Gates completos (`composer gate:all`) obrigatórios antes de fechar última fase
- Frontend: consultar `.context/DESIGN/` antes de qualquer componente/página — obrigatório; Gateway/Backend quando afeta fluxo de usuário — recomendado

## 🗂️ Contexto

| Path | Conteúdo |
|---|---|
| `.context/agents/` | Agents ORCHESTRATOR, PLANNER, BUILDER, REVIEWER |
| `.context/skills/` | Skills PREVEC + code-review-confiavel + brainstorming |
| `.context/ARCHITECTURE/` | Arquitetura, módulos, dependências, brain, snapshot |
| `.context/ARCHITECTURE/canonicals.md` | Canônicos de código Backend + Frontend |
| `.context/DESIGN/` | Wireframes, specs de UI, fluxos visuais |
| `.context/DOCS/` | Features, Tasks, PRDs, Memory |
| `.context/WORKFLOW/` | PREVC.md, validation-flow.md |

## 🗂️ Mapa de Diretórios

### Backend (api/)
| Path | Conteúdo |
|---|---|
| `api/src/Domain/{Feature}/Actions/` | Business logic — ponto de entrada do controller |
| `api/src/Domain/{Feature}/Http/Controllers/` | Controllers REST |
| `api/src/Domain/{Feature}/Http/Resources/` | Transformers de resposta JSON |
| `api/src/Domain/{Feature}/Http/Requests/` | Form requests + validação |
| `api/src/Domain/{Feature}/Models/` | Eloquent models |
| `api/src/Domain/{Feature}/Routes/` | Definição de rotas |
| `api/database/migrations/` | Migrations — nunca criar fora daqui |

### Frontend (app/)
| Path | Conteúdo |
|---|---|
| `app/src/app/pages/{domain}/` | Páginas Angular por domínio |
| `app/src/app/core/models/` | Interfaces TypeScript (Company, Auth, etc.) |
| `app/src/app/core/services/` | Services HTTP |
| `app/src/app/shared/` | Componentes e models compartilhados |
| `app/src/app/pages/platform/` | Área de plataforma (super admin) |
| `app/src/app/pages/ai/` | Área de IA — models em `ai.model.ts` |

## 📖 Glossário de Aliases

| Termo de negócio | Backend | Frontend |
|---|---|---|
| Inquilino / Tenant | `PlatformTenant` (`Domain/Platform`) | `Company` (`core/models/company.model.ts`) |
| Seguimento / Segment | `AiPromptSegment` (`Domain/Ai`) | `SegmentPrompt` (`pages/ai/models/ai.model.ts`) |
| Plano | `PlatformPlan` (`Domain/Platform`) | `PlatformPlan` (`pages/platform/models`) |

## 🤖 Agents

| Agent | Fase PREVC | Modelo | Papel |
|---|---|---|---|
| ORCHESTRATOR | Todas | Sonnet | Coordenação — nunca implementa |
| PLANNER | Planning | Sonnet | BRANDING + PM + ARCHITECT + DESIGNER |
| BUILDER | Execution | Router | Delega para subagents |
| └─ builder-explore | Execution | Haiku | Exploração read-only antes de implementar |
| └─ builder-write | Execution | Sonnet | Implementação com plano claro |
| └─ builder-debug | Execution | Opus | Debugging complexo e causa raiz |
| REVIEWER | Review | Router | Delega para subagents |
| └─ reviewer-doc | Review | Haiku | Valida feature docs e T.A.C.E |
| └─ reviewer-code | Review | Sonnet | Code review com subagents especializados |
| └─ reviewer-confirm | Confirm | Haiku | Fecha task — commit + state files |

> BUILDER e REVIEWER são thin routers — não executam diretamente.
> builder-debug (Opus) ativado apenas quando builder-write falhou ou bug é multifatorial.

## 🔄 Workflow PREVC

```
/prevec-new-plan [ideia]
  → /prevec-decompose-plan [prd]
    → /prevec-decompose-task [feature]
      → Para cada task da fase:
          /prevec-execute-task [feature] TASK-X.Y.Z   ← BUILDER implementa (sem testes)
        → Ao final de cada fase:
          /prevec-phase-close [feature] [N]            ← testes + commit da fase
          → Se última fase: review 7 subagents + gates + Builder fix + PR
```

## ⚡ VIBE-CODER (tasks rápidas)

Para tasks simples sem necessidade do pipeline PREVC completo:

| Escopo | Rota |
|---|---|
| 1-2 arquivos, bounded context único | VIBE-CODER |
| 3+ arquivos ou múltiplos contextos | `/prevec-decompose-plan` |

> VIBE-CODER redireciona automaticamente para `/prevec-decompose-plan` quando detecta task complexa.

## 📋 T.A.C.E

Cada task: **T**arefa · **A**rquivo · **C**omportamento (antes→depois) · **E**vidência verificável

## ❌ Anti-patterns

### Backend
- Nunca criar lógica de negócio em Controller — toda lógica vai em Action
- Nunca reutilizar migration existente — sempre criar nova via artisan
- Nunca fazer query sem filtrar por tenant_id
- Nunca acessar Google AI ou AWS fora do gateway/
- Nunca criar queue processor fora do gateway/

### Frontend
- Nunca acessar api/ ou banco direto do app/ — sempre via service HTTP
- Nunca duplicar interface TypeScript — usar model centralizado em core/models/
- Nunca implementar componente sem consultar .context/DESIGN/ primeiro
- Nunca usar any em TypeScript — tipar explicitamente

### Agents
- Nunca spawnar builder-debug sem antes tentar builder-write
- Nunca chamar ORCHESTRATOR para tasks de 1-2 arquivos — chamar BUILDER direto
- Nunca fazer múltiplas leituras parciais do session file — ler uma vez completo
- Nunca ultrapassar Context Budget sem reportar gap explicitamente
