# CODEX.md — InteraZap

> Use `AGENTS.md` na raiz como kernel mínimo.

## Estrutura

- `AGENTS.md` — Kernel AI
- `api/AGENTS.md` — Regras Laravel
- `gateway/AGENTS.md` — Regras NestJS

## Contexto em Camadas

Consulte `.context/CONTEXT_INDEX.md`.

## Validação

- **API:** `composer gate:all`
- **Gateway:** `pnpm lint && pnpm test`

## Prompts

Prompts em `.codex/prompts/` são entradas operacionais para tarefas comuns.

## Skills

Skills em `.codex/skills/` fornecem instruções sob demanda.