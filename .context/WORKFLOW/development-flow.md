# Development Flow — InteraZap

> Visão geral e índice do processo oficial de desenvolvimento do InteraZap.

## Introdução

Este documento é o ponto de entrada para o fluxo de desenvolvimento do InteraZap. Todo trabalho — seja feature nova, bugfix, refactoring ou documentação — **deve seguir o workflow PREVC** descrito aqui e detalhado nos documentos referenciados.

Nenhuma fase pode ser pulada. Nenhum gate pode ser ignorado.

---

## Documentos do Workflow

| Documento           | Descrição                         | Caminho                                |
| ------------------- | --------------------------------- | -------------------------------------- |
| **PREVC Workflow**  | Detalhamento completo das 5 fases | `.context/WORKFLOW/prevc.md`           |
| **Validation Flow** | Processo de validação e gates     | `.context/WORKFLOW/validation-flow.md` |
| **Task Template**   | Template para criação de tasks    | `.context/WORKFLOW/task-template.md`   |
| **Plan Template**   | Template para criação de planos   | `.context/WORKFLOW/plan-template.md`   |

---

## Fases PREVC — Resumo

| Fase  | Nome       | Objetivo                                          | Output                                                          |
| ----- | ---------- | ------------------------------------------------- | --------------------------------------------------------------- |
| **P** | Planning   | Entender spec, decompor tarefa, definir abordagem | `DOCS/PLANS/PLAN-{000}-{nome}.md` + `DOCS/TASKS/TASKS-{000}.md` |
| **R** | Review     | Validar abordagem antes da implementação          | Plano aprovado                                                  |
| **E** | Execution  | Codificar, testar, documentar                     | Código + testes + docs                                          |
| **V** | Validation | Executar gates, QA review                         | Gates verdes + review aprovado                                  |
| **C** | Confirm    | Evidências, commit semântico, fechamento          | Task `done` + changelog                                         |

---

## Gates — Referência Rápida

```bash
# Backend (Laravel / PHP)
cd api && composer gate:all

# Frontend (Angular / TypeScript)
cd app && pnpm run gate:all

# Gateway (NestJS / TypeScript)
cd gateway && pnpm lint && pnpm test
```

### Auto-fix

```bash
# Backend
cd api && composer format

# Frontend
cd app && pnpm run format
cd app && pnpm run lint:fix
```

---

## Regras Fundamentais

1. **Nenhuma fase pode ser pulada** — mesmo para changes "simples"
2. **Gates são inegociáveis** — se falharem, corrija e re-execute
3. **Toda mudança deve ser rastreável** — task → plan → PRD (se existir)
4. **Isolamento de tenant** — verificado em toda mudança de backend
5. **Testes acompanham código** — nunca entregar código sem cobertura
6. **Documentação é parte da entrega** — atualizar `.context/` quando relevante

---

## Agentes e Responsabilidades

| Agente        | Quando acionar                       |
| ------------- | ------------------------------------ |
| `@PM`         | Decomposição de features grandes     |
| `@ARCHITECT`  | Mudanças estruturais, ADRs           |
| `@DEV`        | Implementação full-stack             |
| `@BACKEND`    | Tasks exclusivas de Laravel          |
| `@FRONTEND`   | Tasks exclusivas de Angular          |
| `@DBA`        | Migrações e schema                   |
| `@QA`         | Após gates, auditoria de qualidade   |
| `@REVIEWER`   | Após QA, code review                 |
| `@GIT_COMMIT` | Commit semântico após review         |
| `@DOC`        | Documentação e artefatos de contexto |
| `@DEBUG`      | Investigação de bugs                 |

---

## Referências

- Contrato de desenvolvimento: `AGENTS.md`
- Arquitetura: `.context/ARCHITECTURE/`
- Memória do projeto: `.context/DOCS/MEMORY/`
- Changelog: `.context/DOCS/CHANGELOG/`
