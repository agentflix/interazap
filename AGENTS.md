# InteraZap — AI-First + PREVC V7

**Stack:** Laravel 12 (api) · NestJS 11 (gateway) · Angular 20 (app) · PostgreSQL 17 · Redis 7
**Arquitetura:** Microservices — Presentation → Gateway → Domain/Application → Infrastructure

## Regras Absolutas

- REVIEWER executa `code-review-confiavel` ao final de **toda** task — sem exceção
- Todo agent mostra o próximo comando com argumentos reais ao final de cada ação concluída
- Gateway nunca acessa PostgreSQL diretamente — sempre via api REST
- Migrations somente em api/ via `php artisan make:migration`
- BullMQ queues somente em gateway/
- PSR-12 (PHP) · Angular Style Guide (TS) · Conventional Commits (git)
- Tenant isolation obrigatória em toda query

## 🗂️ Contexto

| Path | Conteúdo |
|---|---|
| `.context/agents/` | Agents ORCHESTRATOR, PLANNER, BUILDER, REVIEWER |
| `.context/skills/` | Skills PREVEC + code-review-confiavel + brainstorming |
| `.context/ARCHITECTURE/` | Arquitetura, módulos, dependências, brain, snapshot |
| `.context/DESIGN/` | Wireframes, specs de UI, fluxos visuais |
| `.context/DOCS/` | Features, Tasks, PRDs, Memory |
| `.context/WORKFLOW/` | PREVC.md, validation-flow.md |

## 🏗️ Architecture

| Arquivo | Descrição |
|---|---|
| `.context/ARCHITECTURE/architecture.md` | Diagrama de camadas |
| `.context/ARCHITECTURE/modules.md` | Mapa de módulos e dependências |
| `.context/ARCHITECTURE/user-flow.md` | Fluxos principais do usuário |
| `.context/ARCHITECTURE/modules.yaml` | Definição dos módulos/bounded contexts |
| `.context/ARCHITECTURE/dependencies.yaml` | Regras de dependência entre módulos |
| `.context/ARCHITECTURE/project-state.yaml` | Estado atual: stack, métricas |
| `.context/ARCHITECTURE/project-brain.yaml` | Identidade, decisões, regras de negócio |
| `.context/ARCHITECTURE/context-version.yaml` | Versionamento dos arquivos de contexto |

> Antes de qualquer decisão técnica: consultar `project-brain.yaml` e `architecture.md`.

## 🎨 Design

> **OBRIGATÓRIO para tasks de Frontend:** consultar `.context/DESIGN/` antes de implementar qualquer componente, página ou fluxo visual.

## 🤖 Agents

| Agent | Fase PREVC | Absorve |
|---|---|---|
| ORCHESTRATOR | Todas | Coordenação — nunca implementa |
| PLANNER | Pré-Planning + Planning + Review | BRANDING + PM + ARCHITECT + DESIGNER |
| BUILDER | Execution | BACKEND + GATEWAY + FRONTEND + DBA + DEBUG |
| REVIEWER | Review + Validation + Confirm | REVIEWER + DOC + GIT_COMMIT |

## 🔄 Workflow PREVC

```
/prevec-new-plan [ideia]
  → /prevec-decompose-plan [prd]
    → /prevec-decompose-task [feature]
      → /prevec-execute-task [feature] TASK-X.Y.Z
        → /prevec-review-execution [feature] TASK-X.Y.Z
          → /prevec-finalize-execution [feature] TASK-X.Y.Z
```

## 📋 T.A.C.E

Cada task: **T**arefa · **A**rquivo · **C**omportamento (antes→depois) · **E**vidência verificável

## 🧠 Memory

Decisões técnicas e aprendizados: `.context/DOCS/MEMORY/`
