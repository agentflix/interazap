# Context Snapshot
> Cache gerado pelo bootstrap. Regenerar quando context-version.yaml mudar.
> Fonte: project-brain.yaml + architecture.md + modules.yaml + dependencies.yaml

## Stack
Backend: PHP 8.3 + Laravel 12 (Octane + Horizon + Reverb + Sanctum) | Gateway: TypeScript + NestJS 11 (BullMQ + WebSocket)
Frontend: TypeScript + Angular 17 + Capacitor (iOS/Android) | Database: PostgreSQL 17 + pgvector + Redis 7
Testes: Pest (api) · Jest (gateway) · Vitest (app) | Monorepo: pnpm workspaces
Arquitetura: DDD multi-tenant | Camadas API: Controller → DTO → Action → Resource (src/Domain/{Domain}/)

## Regras Invioláveis
1. `declare(strict_types=1)` em todo arquivo PHP
2. `final class` em Controllers, Actions e DTOs
3. UUID como PK — nunca auto-increment
4. `$fillable` explícito — NUNCA `$guarded = []`
5. Eager loading obrigatório — NUNCA N+1
6. Todo Model com dados de tenant usa `BelongsToTenant` trait
7. Todo Controller action chama `$this->authorize()`
8. `composer gate:all` deve passar antes de qualquer commit (API)
9. `pnpm lint && pnpm test` deve passar antes de qualquer commit (Gateway)
10. Webhook ACK < 150ms no Gateway — processamento via BullMQ
11. Idempotência via Redis SETNX em todo webhook handler
12. `ValidationPipe whitelist:true` em todos os controllers NestJS

## Módulos e Dependências (API)
| Módulo | Pode importar | Proibido |
|---|---|---|
| Shared | — | todos |
| Auth | Shared | Chat, Ai, Billing, CRM, Configuration, Dashboard, Gateway, Platform, Reports |
| Billing | Shared, Auth | Chat, Ai, CRM, Configuration, Dashboard, Gateway, Reports |
| Configuration | Shared, Auth | Chat, Ai, Billing, CRM, Dashboard, Gateway, Platform, Reports |
| Gateway | Shared, Auth, Configuration | Chat, Ai, Billing, CRM, Dashboard, Platform, Reports |
| Platform | Shared, Auth, Billing | Chat, Ai, CRM, Configuration, Dashboard, Gateway, Reports |
| CRM | Shared, Auth, Chat | Ai, Billing, Configuration, Dashboard, Gateway, Platform, Reports |
| Chat | Shared, Auth, Ai, CRM | Billing, Configuration, Dashboard, Gateway, Platform, Reports |
| Ai | Shared, Chat | Auth, Billing, CRM, Configuration, Dashboard, Gateway, Platform, Reports |
| Dashboard | Shared, Auth, Chat, Reports | Ai, Billing, CRM, Configuration, Gateway, Platform |
| Reports | Shared, Auth, Chat, CRM | Ai, Billing, Configuration, Dashboard, Gateway, Platform |

## Convenções
- PHP: PSR-12, strict_types=1, final classes, UUID PKs, BelongsToTenant
- NestJS: ValidationPipe whitelist:true, Logger por controller, circuit breaker em HTTP externas
- Angular: standalone components, signals, Tailwind CSS, mobile-first (Capacitor)
- Commits: Conventional Commits em português, escopo = módulo afetado
- Gates API: `composer gate:all` | Gates Gateway: `pnpm lint && pnpm test` | Gates App: `pnpm lint && pnpm build && pnpm test`
