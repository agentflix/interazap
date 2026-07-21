# Context Snapshot
> Cache lean. Regenerar quando `context-version.yaml` mudar.
> Fonte: project-brain.yaml + architecture.md + modules.yaml + dependencies.yaml

## Stack
api: PHP 8.2+ · Laravel 12 · Sanctum · Horizon | gateway: NestJS 11 · Socket.io · BullMQ
app: Angular 20 · TypeScript | Dados: PostgreSQL 17 · Redis 7 (Streams + BullMQ)
IA: Gemini · OpenAI · MiniMax (factory no gateway) | Canais: uazapi · Meta WABA · Telegram · webchat
Testes: Pest (api) · Jest (gateway) · Vitest (app)
Arquitetura: Microservices | Presentation → Domain/Application → Execution/Realtime → Infrastructure
Integração: api→gateway por Redis Streams · gateway→api por HTTP /internal

## Regras Invioláveis
1. Gateway nunca acessa PostgreSQL — todo dado passa pela api
2. Migrations só em `api/database/migrations` via `php artisan make:migration`
3. BullMQ só em gateway/; jobs Horizon só em `api/src/Domain/*/Jobs`
4. Secrets AWS só em gateway/
5. Frontend nunca acessa banco, Redis ou LLM diretamente
6. Business logic em Action; Controller só orquestra
7. Validação de entrada em FormRequest; nunca em Action
8. Toda query multi-tenant filtra por tenant
9. PSR-12 (Pint) em PHP; Angular Style Guide em TS; zero `any`
10. Conventional Commits em português
11. Janela de 24h do WhatsApp define resposta livre vs. template
12. Humano assumiu o ticket → resposta da IA é bloqueada
13. Cobrança por mensagem de IA com cota mensal e modo stop|overage
14. Webhook de canal é idempotente
15. Frontend consulta `.context/DESIGN/` antes de implementar UI

## Módulos e Dependências
| Módulo | Pode consumir | Proibido |
|---|---|---|
| api | PostgreSQL, Redis, gateway (Streams) | envio outbound de canal; LLM (exceto transcrição e guardian) |
| gateway | api (/internal), Redis, LLMs, AWS, Meta | PostgreSQL |
| app | api (/api), gateway (/ws) | PostgreSQL, Redis, LLM |
| landing* | — | api, gateway, banco |

## Gates
api: `cd api && composer gate:all` (rápido: `gate:fast`) · gateway: `pnpm --filter gateway test && pnpm --filter gateway build` · app: `pnpm --filter app test:run && pnpm --filter app build`
