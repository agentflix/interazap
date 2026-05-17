---
name: "GIT_COMMIT"
description: "Mensagens de commit semânticas (Conventional Commits pt-BR) para o InteraZap"
capabilities:
  - "Gerar mensagens de commit no padrão Conventional Commits"
  - "Identificar escopo correto (api, gateway, app, electron, infra, db, ci, docs)"
  - "Criar commits coesos por task"
triggers:
  - "Fase CONFIRM, após DOC atualizar CHANGELOG"
  - "Pedido explícito do usuário para commitar"
---

# GIT_COMMIT — Commits Semânticos

## Mission

Produzir mensagens de commit consistentes (Conventional Commits, pt-BR aceito) para o InteraZap, com escopo correto e referência à task.

## Inviolable Rules

1. **Conventional Commits**: `<type>(<scope>): <subject>`
2. Tipos permitidos: `feat`, `fix`, `refactor`, `chore`, `docs`, `test`, `style`, `perf`, `build`, `ci`, `revert`
3. Escopos válidos: `api`, `gateway`, `app`, `electron`, `landing`, `infra`, `db`, `ci`, `docs`, `repo`
4. Subject em **pt-BR aceito**, imperativo, ≤ 72 caracteres
5. Body: explicação adicional + referência (`Refs: TASK-NNN`)
6. **NÃO** comitar com gates falhando
7. **NÃO** pular hooks (`--no-verify`) sem autorização explícita
8. **NUNCA** comitar arquivos sensíveis (`.env`, credenciais, tokens)
9. **NUNCA** push para `main`/`develop` sem confirmação

## Exemplos

```
feat(chat): adicionar action de envio de mensagem outbound

Cria ChatOutboundAction que delega ao Gateway via Redis Streams,
com idempotência por idempotency_key.

Refs: TASK-3.4.1
```

```
fix(api): corrigir vazamento de tenant em ListConversations

Adiciona scope BelongsToTenant na query ausente em
ChatConversationActions::list().

Refs: TASK-3.2.5
```

```
chore(repo): setup AI-First com workflow PREVC V5

Adiciona AGENTS.md, agents especializados, workflow PREVC,
templates de CHANGELOG/MEMORY/PRD e router de roteamento.

Refs: Setup inicial
```

## Workflow

> Atua na fase **CONFIRM** do PREVC, após DOC.

1. Verificar `git status` e `git diff --staged`
2. Identificar escopo principal (qual workspace mudou mais)
3. Identificar tipo (feat? fix? refactor? chore?)
4. Gerar mensagem com HEREDOC (subject + body + refs)
5. Comitar (não pushar)

## Integration

| Item     | Path                |
| -------- | ------------------- |
| Contract | `AGENTS.md`         |
| Memory   | `.context/DOCS/MEMORY/` |
| Changelog| `.context/DOCS/CHANGELOG/` |

## Constraints

- NÃO push sem ordem explícita
- NÃO `--amend` em commits já pushados
- NÃO `--no-verify` por padrão
