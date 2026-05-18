# Memory: Setup da Estrutura AI-First com PREVC V5

## Metadados

| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão |
| **Data** | 2026-05-17 |
| **Autor** | Setup automático |
| **Contexto** | Configuração inicial do projeto para desenvolvimento com IA |
| **Tags** | setup, estrutura, prevc, ai-first, agents, workflow |

---

## Situação

Projeto InteraZap precisava de estrutura que permitisse desenvolvimento eficiente com IA,
com contexto adequado, workflow definido e qualidade garantida via gates.
AGENTS.md havia sido deletado e faltavam as camadas de documentação, workflow e comandos.

---

## Decisão

Adotar estrutura AI-First com PREVC V5:
- **AGENTS.md** como fonte da verdade (recreado após deleção)
- **CLAUDE.md** → symlink para AGENTS.md
- **PREVC** como workflow obrigatório (Planning → Review → Execution → Validation → Confirm)
- **T.A.C.E** como framework hierárquico de decomposição de tasks
- **Agents especializados** em `.context/AGENTS/` — preservados (16 agents: ARCHITECT, BACKEND, DBA, DEBUG, DESIGNER, DEV, DOC, FRONTEND, GATEWAY, GIT_COMMIT, ORCHESTRATOR, PLAN, PM, QA, REVIEWER, VIBE-CODER)
- **Skills especializadas** em `.context/SKILLS/` — preservadas (laravel-especialista, nestjs-especialista, angular-especialista, code-review-confiavel, workflow-prevc, skill-architect)
- **Hooks** com router.js para roteamento automático
- **Commands** PREVC: new-feature, review-feature, decompose, validate-tasks, implement-task, validate, confirm-task, feature-status, review-phase, validate-phase, confirm-phase
- **CHANGELOG** diário para registro factual de mudanças
- **MEMORY** para decisões e aprendizados persistentes
- **ARCHITECTURE** com 8 arquivos documentando DDD + módulos + dependências

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Agents em `.claude/agents/` | Já existem 16 agents especializados em `.context/AGENTS/` — duplicar seria redundante |
| Criar workflow-prevc do zero | Skill `workflow-prevc` já existia com prevc.md e validation-flow.md validados |
| Settings global apenas | Settings project-level permite configurações específicas do InteraZap |

---

## Consequências

### Positivas
- IA sempre tem contexto adequado via AGENTS.md + ARCHITECTURE
- Tasks nunca vagas (T.A.C.E garante especificidade)
- Qualidade garantida via gates inegociáveis por workspace
- Conhecimento não se perde (MEMORY)
- Histórico rastreável (CHANGELOG)
- Roteamento automático via hooks/router.js

### Trade-offs
- Disciplina necessária para manter CHANGELOG e MEMORY atualizados
- Overhead de processo para mudanças muito pequenas

---

## Referências

- Stack: Laravel 12 / NestJS 11 / Angular 20 / PostgreSQL
- Bounded contexts: Ai, Auth, Billing, Chat, Configuration, CRM, Dashboard, Gateway, Platform, Reports, Shared
- Workflow: `.context/WORKFLOW/PREVC.md`
- Gates: `.context/WORKFLOW/validation-flow.md`
