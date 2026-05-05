# Changelog — YYYY-MM-DD

> Registro factual do que mudou. Para decisões e contexto, veja MEMORY.

## Formato

Cada entrada segue:

```text
- [HORA] [TIPO] [ESCOPO]: Descrição concisa
  - Detalhes relevantes
  - Arquivos principais afetados
  - Task/Feature relacionada
```

## Tipos

- `FEAT` — Nova funcionalidade
- `FIX` — Correção de bug
- `REFACTOR` — Refatoração sem mudança de comportamento
- `DOCS` — Documentação
- `TEST` — Testes
- `CHORE` — Configuração, tooling, infra
- `BREAKING` — Mudança que quebra compatibilidade

## Escopos comuns no InteraZap

- `api` — Laravel / DDD
- `gateway` — NestJS
- `app` — Angular 19
- `electron` — Desktop
- `landing` — Site marketing
- `infra` — Ansible, nginx, observability
- `db` — Migrations, schema, pgvector
- `ci` — GitHub Actions

---

## Entradas

<!-- Preencher durante o dia, a cada CONFIRM do PREVC -->

- [HH:MM] FEAT [escopo]: Descrição
  - Detalhes
  - Arquivos: `path/to/file.ext`
  - Ref: FEAT-NNN / TASK-NNN
