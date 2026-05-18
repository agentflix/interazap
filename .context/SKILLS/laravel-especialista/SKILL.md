---
name: laravel-especialista
description: Especialista Laravel do InteraZap. Use quando Codex precisar implementar, revisar ou planejar backend em api/ com Laravel 12, PHP 8.3, DDD por bounded context, multi-tenancy, controllers, DTOs, actions, resources, models, migrations, jobs assíncronos, policies, requests, rotas, Pest, gates de validação e padrões específicos deste repositório.
---

# Laravel Especialista

## Metadados

- Autor: Rafael Silva
- Versão: 1.0

## Fluxo

Use esta skill para qualquer trabalho em `api/`. Antes de implementar, leia a task ou documentação funcional correspondente quando existir, MEMORY relevante em `.context/DOCS/MEMORY/`, o workflow em `.context/SKILLS/workflow-prevc/references/prevc.md` e os gates em `.context/SKILLS/workflow-prevc/references/validation-flow.md`.

## Regras

- Mantenha `declare(strict_types=1)` em todo PHP novo.
- Siga `Controller -> DTO -> Action -> Resource`.
- Use `api/src/Domain/{Context}/...` e respeite o bounded context existente.
- Preserve isolamento tenant: modelos tenant-aware usam `BelongsToTenant`; queries recebem `tenant_id` explícito quando o padrão local exigir.
- Use UUID/`Str::orderedUuid()` para novas entidades e migrations.
- Escreva ou atualize testes Pest para todo comportamento novo.
- Rode `cd api && composer gate:all` quando a task tocar backend; se não rodar, registre o motivo.

## Regras Por Tema

Carregue apenas os arquivos necessários conforme a demanda em `references/rules/`:

- `references/rules/domain.md`: arquitetura DDD, bounded contexts e fluxo PREVC.
- `references/rules/controller.md`: controllers, requests, resources, rotas e policies.
- `references/rules/model.md`: models, multi-tenancy, UUIDs, casts, relacionamentos e factories.
- `references/rules/job.md`: jobs assíncronos, idempotência, retries, backoff, filas e testes.
- `references/rules/migration.md`: migrations PostgreSQL, UUIDs, índices, FKs e tenant_id.
- `references/rules/performance.md`: performance Laravel, Eloquent, cache, payloads e N+1.
- `references/rules/database-performance.md`: tuning PostgreSQL, índices, locks e queries grandes.
- `references/rules/queues.md`: filas Laravel/Horizon, Redis, prioridades e DLQ.
- `references/rules/availability.md`: alta disponibilidade, degradação graciosa, timeouts e resiliência.
- `references/rules/php-runtime.md`: PHP 8.3, OPcache, Composer autoload e PHP-FPM/CLI workers.
- `references/rules/octane.md`: Laravel Octane, workers long-lived, estado global e contexto tenant.
- `references/rules/laravel-production.md`: caches artisan, deploy, scheduler, Horizon e segurança operacional.
- `references/rules/observability.md`: logs estruturados, métricas, traces, health checks e alertas.
- `references/rules/security.md`: hardening, IDOR, webhooks, rate limit e dados sensíveis.
- `references/rules/api-contracts.md`: envelope HTTP, erros, paginação, idempotência e contratos API Gateway.
- `references/rules/testing.md`: Pest, factories, isolamento tenant, mocks e cobertura.
- `references/rules/validation.md`: gates obrigatórios e evidências de validação.
