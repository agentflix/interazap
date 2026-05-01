# AGENTS.md — Fonte da Verdade do Projeto

> Lido automaticamente pelo Claude Code via symlink CLAUDE.md.

---

## 🏗️ Identidade
- **Nome:** InteraZap
- **Descrição:** Plataforma de comunicação multicanal com IA integrada
- **Stack:** Laravel 12 (PHP 8.2+) + Angular 20 (TypeScript) + PostgreSQL
- **Arquitetura:** DDD (Domain-Driven Design)
- **Repositório:** Local

---

## 🚨 Regras Absolutas

1. Sempre responder em português brasileiro
2. Nunca apagar ou sobrescrever sem confirmação
3. Seguir estrutura de pastas existente
4. Todo código novo DEVE ter testes
5. Commits: Conventional Commits
6. Nunca expor segredos no código
7. Verificar feature doc em `.context/DOCS/FEATURES/` antes de implementar
8. Tasks seguem framework T.A.C.E
9. Workflow PREVC é obrigatório (`.context/WORKFLOW/PREVC.md`)
10. Gates de validação são inegociáveis (`.context/WORKFLOW/validation-flow.md`)
11. **Toda task concluída gera entrada em `.context/DOCS/CHANGELOG/`**
12. **Toda decisão relevante gera entrada em `.context/DOCS/MEMORY/`**
13. **`.context/ARCHITECTURE/project-state.yaml` é atualizado a cada feature concluída**
14. **Skills:** Cada agent carrega apenas as skills definidas na seção `🧠 Carregamento de Skills por Agente`.

---

## 📁 Mapa de Contexto

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

---

## 🔄 Workflow PREVC

```
PLANNING → REVIEW → EXECUTION → VALIDATION → CONFIRM
```

| Fase | Responsável | Output | Registros |
|------|------------|--------|-----------|
| Planning | PM / ARCHITECT | Feature doc | — |
| Review | REVIEWER / ARCHITECT | Aprovação → Tasks | — |
| Execution | DEV / BACKEND / FRONTEND / DBA | Código + Testes | — |
| Validation | QA / REVIEWER | Gates passam | — |
| **Confirm** | **PM / DOC** | **Task done** | **CHANGELOG + MEMORY** |

> Detalhes: `.context/WORKFLOW/PREVC.md`

---

## 📜 CHANGELOG — Registro de Mudanças

- **Onde:** `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`
- **Quando:** Fase CONFIRM de cada task
- **O quê:** Registro FACTUAL — o que mudou, arquivos afetados, refs
- **Template:** `.context/DOCS/CHANGELOG/_TEMPLATE.md`

## 🧠 MEMORY — Decisões e Aprendizados

- **Onde:** `.context/DOCS/MEMORY/YYYY-MM-DD-titulo.md`
- **Quando:** Sempre que uma decisão for tomada ou algo for aprendido
- **O quê:** Decisões com alternativas, aprendizados, armadilhas, insights
- **Template:** `.context/DOCS/MEMORY/_TEMPLATE.md`
- **REGRA:** Antes de tomar qualquer decisão técnica, consultar MEMORY primeiro

---

## 📐 Convenções

### PHP/Laravel
- Classes: `PascalCase`
- Métodos: `camelCase`
- Arquivos de classe: `PascalCase.php`
- Arquivos de config: `kebab-case.php`
- Migrations: `YYYY_MM_DD_HHMMSS_create_table_name.php`

### TypeScript/Angular
- Arquivos: `kebab-case.ts`
- Classes: `PascalCase`
- Métodos: `camelCase`
- Constantes: `UPPER_SNAKE_CASE`
- Standalone components: `.component.ts`

### Git
- Branch: `feature/FEAT-NNN-descricao | fix/FIX-NNN-descricao`
- Commit: `type(scope): description` (Conventional Commits)

---

## 🤖 Agents

| Agent | Fase PREVC | Quando Usar |
|-------|-----------|-------------|
| ORCHESTRATOR | Todas | Coordenação de features complexas |
| PM | Planning, Confirm | Feature docs, escopo, fechamento |
| ARCHITECT | Planning, Review | Decisões de arquitetura |
| REVIEWER | Review | Code review, doc review |
| BACKEND | Execution | Laravel/PHP |
| FRONTEND | Execution | Angular/TypeScript |
| DEV | Execution | Cross-camada |
| DBA | Execution | PostgreSQL, migrations |
| QA | Validation | Gates, testes |
| DEBUG | Execution | Bugs |
| DOC | Confirm | CHANGELOG, MEMORY, docs |
| GIT_COMMIT | Confirm | Commits semânticos |
| DESIGNER | Planning | UI/UX |

---

## 🧠 Carregamento de Skills por Agente

> Cada agent carrega **apenas** as skills listadas abaixo. Subagentes de execução não carregam skills de raciocínio — as tasks T.A.C.E já são prescritivas o suficiente.

| Agent | Skills obrigatórias | Skills opcionais | Não carrega |
|-------|--------------------|--------------------|-------------|
| ORCHESTRATOR | `tace-framework`, `decompose-feature` | `human-architect-mindset` | — |
| ARCHITECT | `tace-framework`, `human-architect-mindset` | `technical-design-doc-creator` | — |
| REVIEWER | `tace-framework`, `coding-guidelines` | — | — |
| PM | `tace-framework`, `write-feature` | `prd` | `coding-guidelines` |
| BACKEND | `coding-guidelines` | `laravel-specialist`, `php-pro` | `tace-framework`, `human-architect-mindset` |
| FRONTEND | `coding-guidelines` | `angular-architect`, `design` | `tace-framework`, `human-architect-mindset` |
| DEV | `coding-guidelines`, `tace-framework` | — | `human-architect-mindset` |
| DBA | `coding-guidelines` | — | `tace-framework`, `human-architect-mindset` |
| QA | `tace-framework` | `e2e-testing`, `tdd` | `coding-guidelines` |
| DEBUG | `coding-guidelines` | — | `tace-framework` |
| DOC | — | — | `coding-guidelines`, `tace-framework` |
| GIT_COMMIT | `git-commit` | — | `tace-framework`, `coding-guidelines` |
| DESIGNER | `design` | `frontend-design` | `tace-framework`, `coding-guidelines` |

**Regra:** ORCHESTRATOR é o único com visão completa do PLAN. Subagentes recebem apenas a task específica + contexto mínimo do seu domínio.

---

## 📦 Contexto Mínimo por Agente

> Subagentes leem APENAS o necessário para sua task. ORCHESTRATOR é o único com visão completa.

| Agent | Lê sempre | Lê se relevante | NUNCA lê |
|-------|-----------|-----------------|----------|
| BACKEND | `AGENTS.md` + task específica + módulo DDD do domínio | MEMORY do domínio | PLAN inteiro, tasks de outros agentes |
| FRONTEND | `AGENTS.md` + task específica + `.context/LAYOUT/` | MEMORY do componente | PLAN inteiro, tasks de backend |
| DBA | `AGENTS.md` + task específica + schema do módulo | MEMORY de migrations | Código de aplicação |
| QA | `AGENTS.md` + task específica + critérios de evidência | Testes existentes do módulo | Código de implementação |
| DOC | `AGENTS.md` + task específica + template de CHANGELOG | MEMORY recente | Código fonte |
| DEBUG | `AGENTS.md` + task específica + arquivo com bug | MEMORY do módulo | Tasks não relacionadas |
| GIT_COMMIT | `AGENTS.md` + diff atual | — | Feature docs, tasks, PLAN |
| ORCHESTRATOR | `AGENTS.md` + PLAN completo + todas as tasks | MEMORY global | — |
| ARCHITECT | `AGENTS.md` + feature doc + MEMORY do domínio | PLANs anteriores similares | Código de implementação |
| PM | `AGENTS.md` + PRD relacionado + MEMORY global | Features similares anteriores | Código, tasks T.A.C.E |
| REVIEWER | `AGENTS.md` + código a revisar + task de referência | MEMORY do módulo | PLANs, tasks de outros módulos |
| DESIGNER | `AGENTS.md` + `.context/LAYOUT/` + feature doc | MEMORY de UI | Código de implementação |

---

## 📝 Framework T.A.C.E

| Letra | Significado | Pergunta |
|-------|-------------|----------|
| **T** | Tarefa | O QUE fazer? |
| **A** | Arquivo | ONDE fazer? |
| **C** | Comportamento | COMO funciona (antes→depois)? |
| **E** | Evidência | COMO SABER que está pronto? |

> Skill: `.claude/skills/tace-framework/SKILL.md`

---

## 🏛️ Arquitetura DDD

### Layers

```
Domain Layer (Entities, Value Objects, Services, Events, Policies)
         ↓
Application Layer (Actions, DTOs)
         ↓
Infrastructure (Jobs, Mail, Notifications, External Services)
         ↓
Presentation (Controllers, Middleware, Requests)
```

### Módulos (Bounded Contexts)

| Módulo | Path | Dependências |
|--------|------|--------------|
| AI | `api/src/Domain/Ai` | — |
| Auth | `api/src/Domain/Auth` | — |
| Billing | `api/src/Domain/Billing` | Auth |
| Chat | `api/src/Domain/Chat` | Auth |
| Configuration | `api/src/Domain/Configuration` | Auth |
| CRM | `api/src/Domain/CRM` | Auth |
| Dashboard | `api/src/Domain/Dashboard` | Chat, Billing |
| Gateway | `api/src/Domain/Gateway` | Chat |
| Platform | `api/src/Domain/Platform` | — |
| Reports | `api/src/Domain/Reports` | Chat, Billing, CRM |
| Shared | `api/src/Domain/Shared` | — |

---

## 🧪 Testing Stack

### Backend (API)
- **Framework:** Pest
- **Command:** `./vendor/bin/pest`
- **Coverage:** `coverage.xml`

### Frontend (App)
- **Framework:** Vitest (via `@analogjs/vitest-angular` + `@analogjs/vite-plugin-angular`, jsdom)
- **Command:** `pnpm run gate:test` (executa `ng test --watch=false`)
- **Specs:** usar `vitest` API (`vi.spyOn`, `vi.fn`, `describe/it/expect`) — **não** Jasmine
- **Coverage:** `coverage/` (provider `@vitest/coverage-v8`)

---

## ⚙️ Stack Técnica

| Camada | Tecnologia | Version |
|--------|------------|---------|
| Backend | PHP | 8.2+ |
| Framework | Laravel | 12.x |
| Frontend | TypeScript | 5.x |
| Framework | Angular | 20.3.x |
| Database | PostgreSQL | 16 |
| Queue | Laravel Horizon | — |
| Auth | Laravel Sanctum | — |
| Permissions | Spatie Laravel Permission | 6.x |
