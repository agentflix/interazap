---
name: init-prompt
description: 'Ponto de entrada para todas as tarefas. Avalia a tarefa recebida, verifica skills compatíveis, exibe catálogo de skills quando necessário, e invoca o agente ou skill apropriada.'
---

# Inicialização de Tarefa

## Meta

Roteador central que avalia TODA tarefa recebida, verifica skills compatíveis, exibe catálogo quando necessário, e invoca o recurso correto — tudo automaticamente, sem o usuário precisar informar.

## Fluxo de Execução

```
Tarefa Recebida
     │
     ▼
┌─────────────────────────┐
│ 1. ANALISAR TAREFA      │
│ - Identificar tipo      │
│ - Identificar módulo     │
│ - Identificar escopo    │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│ 2. VERIFICAR SKILLS     │
│ - Check .claude/skills/ │
│ - Match por descrição   │
│ - Match por keywords     │
└───────────┬─────────────┘
            │
      ┌─────┴─────┐
      │ Skill     │
      │ encontrada?│
      └─────┬─────┘
       SIM  │  NÃO
        │   │
        ▼   │
┌─────────┐ │
│INVOCAR  │ │
│ SKILL   │ │
└────┬────┘ │
     │      │
     │      ▼
     │ ┌─────────────────────────┐
     │ │ 3. EXIBIR CATÁLOGO      │
     │ │ Exibe todas as skills   │
     │ │ disponíveis abaixo      │
     │ └───────────┬─────────────┘
     │             │
     │             ▼
     │ ┌─────────────────────────┐
     └──│ USUÁRIO SELECIONA     │
        │ OU                    │
        │ TAREFA É COMPLEXA?    │
        └───────────┬───────────┘
                    │
          ┌─────────┴─────────┐
          │ Complexa (multi-  │
          │ agente/multi-layer)│
          └─────────┬─────────┘
             SIM    │  NÃO
              │     │
              ▼     ▼
     ┌───────────┐ ┌───────────┐
     │INVOCAR    │ │INVOCAR    │
     │@ORCHESTRA │ │@DEV para  │
     │TOR        │ │tarefas    │
     │           │ │simples    │
     └───────────┘ └───────────┘
```

## Catálogo de Skills Disponíveis

### 📋 Planejamento & Documentação

| Skill                          | Descrição                                                                                   | Uso                             |
| ------------------------------ | ------------------------------------------------------------------------------------------- | ------------------------------- |
| `brainstorming`                | Exploração de ideias antes de implementar. Obrigatório para features/funcionalidades novas. | `/brainstorming`                |
| `generate-prd`                 | Gera PRD (Product Requirements Document) para features de módulo.                           | `/generate-prd`                 |
| `create-plan`                  | Cria plano de desenvolvimento a partir de requisitos.                                       | `/create-plan`                  |
| `create-task`                  | Cria task a partir de um plano aprovado.                                                    | `/create-task`                  |
| `technical-design-doc-creator` | Cria Technical Design Documents (TDD) compreensivos.                                        | `/technical-design-doc-creator` |

### 🎨 Design & UI

| Skill                   | Descrição                                                            | Uso                      |
| ----------------------- | -------------------------------------------------------------------- | ------------------------ |
| `frontend-design`       | Cria interfaces frontend distintivas, production-grade.              | `/frontend-design`       |
| `generate-mockup`       | Gera mockups textuais ou wireframes ASCII para planejamento de UI.   | `/generate-mockup`       |
| `generate-diagram`      | Gera diagramas Mermaid para arquitetura, fluxos, ou relacionamentos. | `/generate-diagram`      |
| `web-design-reviewer`   | Revisão visual de websites — verifica design, acessibilidade, UX.    | `/web-design-reviewer`   |
| `web-design-guidelines` | Diretrizes de design web e compliance.                               | `/web-design-guidelines` |

### 🧪 Qualidade & Testes

| Skill            | Descrição                                                     | Uso               |
| ---------------- | ------------------------------------------------------------- | ----------------- |
| `tdd`            | Test-driven development com loop red-green-refactor.          | `/tdd`            |
| `e2e-testing`    | End-to-end testing com Playwright para workflows completos.   | `/e2e-testing`    |
| `playwright-cli` | Automação de browser para testing, form filling, screenshots. | `/playwright-cli` |
| `webapp-testing` | Testing de webapps locais com Playwright.                     | `/webapp-testing` |

### 💻 Especialistas Backend

| Skill                | Descrição                                                         | Uso                   |
| -------------------- | ----------------------------------------------------------------- | --------------------- |
| `laravel-specialist` | Laravel 10+ — Eloquent, Sanctum, Horizon, Livewire, APIs RESTful. | `/laravel-specialist` |
| `php-pro`            | PHP moderno 8.3+ — Laravel, Symfony, async patterns com Swoole.   | `/php-pro`            |

### 🖥️ Especialistas Frontend

| Skill               | Descrição                                                 | Uso                  |
| ------------------- | --------------------------------------------------------- | -------------------- |
| `angular-architect` | Angular 17+ — standalone components, signals, NgRx, RxJS. | `/angular-architect` |

### ⚙️ DevOps & Infraestrutura

| Skill                | Descrição                                                             | Uso                   |
| -------------------- | --------------------------------------------------------------------- | --------------------- |
| `ansible-automation` | Ansible playbooks, roles, inventory para deploy.                      | `/ansible-automation` |
| `electron`           | Automação de apps desktop (VS Code, Slack, etc.) via Chrome DevTools. | `/electron`           |

### 📝 Utilitários

| Skill                   | Descrição                                  | Uso                      |
| ----------------------- | ------------------------------------------ | ------------------------ |
| `git-commit`            | Commit semântico com Conventional Commits. | `/git-commit`            |
| `jsdoc-typescript-docs` | Documentação JSDoc para TypeScript.        | `/jsdoc-typescript-docs` |
| `task-management`       | Task management simples com TASKS.md.      | `/task-management`       |
| `prompt-builder`        | Criação de prompts GitHub Copilot.         | `/prompt-builder`        |
| `find-skills`           | Descobre e instala skills.                 | `/find-skills`           |

## Passo a Passo

### Etapa 1 — Analisar Tarefa

Analise a mensagem do usuário e classifique:

**Por tipo:**

- `brainstorming` — ideia nova, feature, funcionalidade
- `prd` — gerar Product Requirements Document
- `plan` — criar plano de implementação
- `task` — criar task específica
- `code` — implementação código
- `test` — testes
- `review` — revisão de código/design
- `refactor` — refatoração
- `doc` — documentação
- `infra` — infraestrutura/devops
- `commit` — git commit

**Por escopo:**

- `simples` — 1 arquivo, 1 camada, ação única → agente único
- `complexa` — multi-arquivo, multi-camada, multi-módulo → ORCHESTRATOR

**Por módulo:**

- `auth`, `crm`, `chat`, `billing`, `ai`, `platform`, `gateway`, `dashboard`, `configuration`, `reports`

### Etapa 2 — Match de Skills

Verifique se a tarefa bate com alguma skill:

| Keyword na mensagem                                         | Skill para invocar      |
| ----------------------------------------------------------- | ----------------------- |
| "nova feature", "criar feature", "implementar", "adicionar" | `brainstorming`         |
| "prd", "product requirements", "requisitos"                 | `generate-prd`          |
| "plano", "planejar", "implementação"                        | `create-plan`           |
| "task", "tarefa", "subtask"                                 | `create-task`           |
| "mockup", "wireframe", "layout"                             | `generate-mockup`       |
| "diagrama", "arquitetura", "fluxo"                          | `generate-diagram`      |
| "tdd", "test-first", "red-green", "testes"                  | `tdd`                   |
| "e2e", "end-to-end", "playwright"                           | `e2e-testing`           |
| "frontend", "angular", "componente", "ui"                   | `angular-architect`     |
| "backend", "laravel", "php", "api"                          | `laravel-specialist`    |
| "frontend design", "interface", "página"                    | `frontend-design`       |
| "revisar design", "review design", "ux"                     | `web-design-reviewer`   |
| "commit", "git"                                             | `git-commit`            |
| "documentar", "jsdoc", "docs"                               | `jsdoc-typescript-docs` |

### Etapa 3 — Decidir Ação

```
SE skill encontrada:
  → Invoke a skill via Skill tool

SE NÃO skill encontrada E tarefa simples:
  → Invoke @DEV com contexto apropriado

SE NÃO skill encontrada E tarefa complexa:
  → Invoke @ORCHESTRATOR com contexto completo
```

### Etapa 4 — Invocar ORCHESTRATOR (se aplicável)

Quando a tarefa requer multi-agente, leia `.claude/agents/ORCHESTRATOR.md` e delegue:

```
@ORCHESTRATOR — Task Coordinator

## Tarefa Recebida
[descrição da tarefa]

## Análise
- Tipo: [tipo identificado]
- Módulo: [módulo identificado]
- Escopo: [simples/complexo]
- Skills disponíveis: [lista de skills que não matcharam]

## Dependency Chain
[链条 de agentes necessária]

## Input para Orquestração
[contexto completo para o ORCHESTRATOR]
```

## Regras de Ouro

1. **SEMPRE avalie** — nunca pule a análise inicial
2. **Match completo** — verifique TODAS as skills antes de decidir
3. **Exiba o catálogo** — quando não houver match, mostre a tabela completa
4. **Delegue para ORCHESTRATOR** — tarefas multi-camada vão para ele
5. **Contexto é rei** — passe informação suficiente para a skill/agent
6. **Follow PREVC** — qualquer implementação deve seguir o workflow PREVC

## Exemplo de Invocação

**Mensagem do usuário:** "preciso adicionar um dashboard de métricas"

**Análise:**

- Tipo: `code` + `feature`
- Escopo: `complexo` (múltiplas camadas: API + Frontend)
- Módulo: `dashboard`
- Match: Nenhuma skill específica para dashboard

**Ação:**

```
👋dashboards require multi-layer implementation (API + Frontend). Invoking @ORCHESTRATOR for task coordination.

@ORCHESTRATOR — Dashboard de Métricas

## Tarefa
Adicionar dashboard de métricas ao InteraZap

## Análise
- Tipo: Feature nova
- Escopo: Complexo (Backend + Frontend)
- Módulo: Dashboard
- Skills disponíveis: brainstorming (não usado pois o escopo já está claro)

## Dependency Chain
DBA → BACKEND → FRONTEND → QA

Por favor, decomponha a tarefa e orquestre a implementação.
```

## Output

Após análise, responda com:

```
## Análise da Tarefa

**Tipo:** [identificado]
**Módulo:** [identificado]
**Escopo:** [simples|complexo]

**Skill invocada:** [nome da skill ou "Nenhuma - seguindo para agente"]

[Se complexa] Invocando @ORCHESTRATOR...
[Se simples e skill] → Invoke via Skill tool
[Se simples e sem skill] → @DEV
```
