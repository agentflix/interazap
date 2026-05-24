# Context Snapshot
> Cache gerado pelo bootstrap. Regenerar quando context-version.yaml mudar.
> Fonte: project-brain.yaml + architecture.md + modules.yaml + dependencies.yaml

## Stack
Backend: PHP 8.3 + Laravel 12 (api/) | Gateway: Node.js + NestJS 11 (gateway/)
Frontend: TypeScript + Angular 20 (app/) | Database: PostgreSQL 17 + Redis 7
Testes: Pest --parallel (api) · Jest (gateway + app) | Queue: BullMQ
Arquitetura: Microservices | Camadas: Presentation → Gateway → Domain/Application → Infrastructure

## Regras Invioláveis
1. Gateway nunca acessa PostgreSQL diretamente — sempre via api REST
2. Migrations somente em api/ via Laravel Artisan
3. BullMQ queues definidas e processadas somente em gateway/
4. AI (Google Generative AI) integrada somente em gateway/
5. Secrets (AWS Secrets Manager) consumidos somente em gateway/
6. Frontend (app/) nunca acessa banco ou Redis diretamente
7. PSR-12 obrigatório para todo PHP em api/
8. Angular Style Guide obrigatório para todo TypeScript em app/
9. Conventional Commits: tipo(escopo): descrição em português
10. Tenant isolation: toda query deve filtrar por tenant_id
11. Sessão de chat expirada não pode ser reaberta
12. Webhook WhatsApp processado via BullMQ para garantir idempotência
13. Rate limiting no gateway por tenant para chamadas de AI

## Módulos e Dependências
| Módulo | Pode importar | Proibido |
|---|---|---|
| gateway | api (HTTP), Redis, Google AI, AWS, WhatsApp | PostgreSQL direto |
| api | PostgreSQL, Redis | Google AI, WhatsApp, gateway |
| app | gateway (WS+REST), api (REST) | PostgreSQL, Redis direto |
| landing | — | api, gateway, banco |
| landing-clinicas | — | api, gateway, banco |

Gates: api=`composer gate:all` · gateway=`pnpm --filter gateway build && test` · app=`pnpm --filter app build && test`
