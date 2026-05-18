# AGENTS.md — InteraZap

## Fonte da Verdade

Este arquivo é apenas o índice minimalista do projeto.

- Regras detalhadas por tecnologia ficam em `.context/SKILLS/`.
- Personas e responsabilidades ficam em `.context/AGENTS/`.
- Em caso de conflito, siga primeiro a skill ou agent mais específico.
- `.claude/`, `.codex/` e `.opencode/` apontam por symlink para `.context/AGENTS` e `.context/SKILLS`.

## Stack

- **API:** Laravel 12, PHP 8.3, PostgreSQL 17, Redis 7, Pest.
- **Gateway:** NestJS 11, TypeScript, BullMQ, Redis Streams, Socket.IO, Jest.
- **App:** Angular/Ionic/Capacitor, Vitest.
- **Electron:** Electron + Angular.
- **Infra:** Ansible, nginx, observabilidade em Prometheus/logs.

## Estrutura

- `api/`: backend Laravel, DDD por bounded context.
- `gateway/`: NestJS para integrações, Redis Streams, filas e realtime.
- `app/`: frontend web/mobile Angular/Ionic.
- `electron/`: app desktop.
- `landing/`: site público.
- `infra/`: infraestrutura.
- `observability/`: métricas e logs.

## Skills

- As skills abaixo são obrigatórias quando a tarefa estiver dentro do escopo delas.
- Use `laravel-especialista` para qualquer mudança em `api/`.
- Use `nestjs-especialista` para qualquer mudança em `gateway/`.
- Use `angular-especialista` para qualquer mudança em `app/`.
- Use `workflow-prevc` para planejar, executar, validar ou confirmar features/tasks pelo fluxo PREVC.
- Use `code-review-confiavel` para revisar diffs, PRs ou entregas antes de mergear.

## FLOW

- ** Mandatorio** sempre usar a skill `code-review-confiavel` para ao finazar uma tarefa de codigo.

## Agents

Use os arquivos em `.context/AGENTS/` conforme o tipo de tarefa:

- `BACKEND`: Laravel/API.
- `GATEWAY`: NestJS/Gateway.
- `FRONTEND`: Angular/Ionic/Electron.
- `DBA`: PostgreSQL, migrations, índices e Redis.
- `QA`: gates, testes e validação.
- `DOC`: changelog, memory e documentação.
- `ARCHITECT`: decisões técnicas e arquitetura.
- `PM`: planejamento e escopo.

## Comandos

```bash
# API
cd api && composer gate:all

# Gateway
pnpm --filter gateway test && pnpm --filter gateway build

# App
pnpm --filter app test && pnpm --filter app build

# Electron
pnpm --filter electron build
```

## Regras Rápidas

- Responder em português brasileiro.
- Não apagar, substituir ou mover arquivos/pastas existentes sem confirmação.
- Não commitar segredos.
- Todo código novo deve ter teste.
- Rode os gates do workspace alterado; se não rodar, informe o motivo.
- Frontends não acessam banco direto.
- API ↔ Gateway usa Redis Streams idempotentes.
