# Arquivos Canônicos de Referência

> Lido por **builder-explore** ao mapear padrões antes de escrever código.
> Copie a estrutura do canônico — não invente um padrão novo.
> Novo padrão canônico surgiu? Adicione aqui; não duplique em AGENTS.md.

## api — Laravel 12

| Padrão | Arquivo canônico |
|---|---|
| Action (business logic) | `api/src/Domain/Platform/Actions/PlatformTenantActions.php` |
| FormRequest (validação) | `api/src/Domain/Auth/Http/Requests/AuthUserStoreRequest.php` |
| Resource (transformer JSON) | `api/src/Domain/Platform/Http/Resources/PlatformTenantResource.php` |
| DTO | `api/src/Domain/Auth/DTOs/AuthUserDTO.php` |
| Controller REST | `api/src/Domain/Auth/Http/Controllers/AuthUserController.php` |
| Policy | `api/src/Domain/Chat/Policies/ChatTicketPolicy.php` |
| Job (Horizon) | `api/src/Domain/Ai/Jobs/AiRunExecutionJob.php` |
| Client do gateway (Redis Streams) | `api/src/Domain/Gateway/Services/RedisGatewayClient.php` |
| Ingestão de webhook | `api/src/Domain/Chat/Actions/ChatWebhookIngestor.php` |
| Teste Pest (unit) | `api/tests/Unit/Chat/VerifyContactWindowActionTest.php` |
| Teste Pest (feature) | `api/tests/Feature/AiAgentControllerTest.php` |

## gateway — NestJS 11

| Padrão | Arquivo canônico |
|---|---|
| Controller | `gateway/src/domains/chat/controllers/chat.controller.ts` |
| Controller de webhook | `gateway/src/domains/chat/controllers/meta-webhook.controller.ts` |
| Consumer de fila | `gateway/src/domains/ai/consumers/ai-completion.consumer.ts` |
| Factory de provedor LLM | `gateway/src/domains/ai/providers/ai-provider.factory.ts` |
| DTO | `gateway/src/domains/chat/dto/connect-instance.dto.ts` |
| Config de fila / resiliência | `gateway/src/shared/services/queue/queue-resilience.config.ts` |
| Configuração de ambiente | `gateway/src/core/config/configuration.ts` |
| Teste Jest | `gateway/src/domains/chat/controllers/chat.controller.spec.ts` |

## app — Angular 20

| Padrão | Arquivo canônico |
|---|---|
| Page com modal + lista | `app/src/app/pages/platform/users/platform-users.ts` |
| Form component (input/output) | `app/src/app/pages/platform/users/components/platform-user-form/platform-user-form.ts` |
| Store com signals | `app/src/app/pages/chat/chat.store.ts` |
| Service HTTP | `app/src/app/core/services/platform-user.service.ts` |
| Route guard | `app/src/app/core/guards/auth.guard.ts` |
| Model interface | `app/src/app/core/models/platform-user.model.ts` |
| Teste Vitest | `app/src/app/pages/chat/chat.store.spec.ts` |
