# Tasks — App/API/Gateway Boundary + CRM N+1 Tests

## Metadados

| Campo | Valor |
|-------|-------|
| Data | 2026-05-11 |
| Contextos | CRM, Chat, Platform, Gateway, App |
| Status | Validado com ressalvas |

---

## TASK-1.1 — Limpar Ruído N+1

- **T:** garantir zero `dump()`/`dd()` em testes N+1 de CRM.
- **A:** `api/tests/Feature/Domain/CRM/CRMNegotiationN+1Test.php`, `api/tests/Feature/Domain/CRM/CRMContactN+1Test.php`.
- **C:** debug permanece apenas via `info()` condicionado por `DEBUG_N_PLUS1`.
- **E:** `rg "dump\\(|dd\\(" api/tests/Feature/Domain/CRM` sem resultados.
- **Status:** Concluída.

## TASK-1.2 — Melhorar Assert N+1

- **T:** trocar falha opaca por mensagem com endpoint, query count e threshold.
- **A:** mesmos testes CRM.
- **C:** assertions agora usam `assertCrmNPlusOneQueryBudget`.
- **E:** `cd api && ./vendor/bin/pest tests/Feature/Domain/CRM/CRMNegotiationN+1Test.php tests/Feature/Domain/CRM/CRMContactN+1Test.php` passou com 7 testes e 21 assertions.
- **Status:** Concluída.

## TASK-2.1 — Inventário App → Gateway

- **T:** buscar e classificar usos de `environment.gateway.url`.
- **A:** `app/src/app/**`.
- **C:** único uso permitido está em `RealtimeService` para Socket.io autenticado.
- **E:** `rg "environment\\.gateway\\.url|gateway\\.url" app/src/app` retorna apenas `app/src/app/core/services/realtime.service.ts`.
- **Status:** Concluída.

## TASK-2.2 — Templates Via API

- **T:** garantir selector e modal via Laravel API.
- **A:** `template-selector.ts`, `new-conversation-modal.ts`, specs.
- **C:** `GET /api/chat/message-templates` e `POST /api/chat/tickets/{ticketId}/messages/template`.
- **E:** specs validam `environment.apiUrl`; `rg "gateway" ...template-selector ...new-conversation-modal` sem HTTP direto.
- **Status:** Concluída.

## TASK-2.3 — Queue Admin Via API Proxy

- **T:** manter dashboard de filas via Laravel API.
- **A:** `queue.service.ts`, `queue.service.spec.ts`, `QueueAdminController.php`, `platform.php`.
- **C:** App usa `/api/admin/queues/*`; Laravel faz proxy para Gateway com `x-api-key`.
- **E:** `QueueAdminControllerTest` passou com 16 testes e 55 assertions; `QueueService` passou no suite do App.
- **Status:** Concluída.

## TASK-2.4 — Gate Anti-Regressão

- **T:** impedir HTTP direto App → Gateway.
- **A:** `scripts/validate-app-gateway-boundary.sh`, `.context/WORKFLOW/validation-flow.md`.
- **C:** gate falha se `environment.gateway.url` aparecer fora do realtime permitido.
- **E:** `scripts/validate-app-gateway-boundary.sh` passou.
- **Status:** Concluída.

---

## Validação

- `cd api && ./vendor/bin/pest tests/Feature/Domain/CRM/CRMNegotiationN+1Test.php tests/Feature/Domain/CRM/CRMContactN+1Test.php`: passou.
- `cd api && ./vendor/bin/pest tests/Feature/Platform/QueueAdminControllerTest.php`: passou.
- `cd app && pnpm build`: passou.
- `./scripts/validate-app-gateway-boundary.sh`: passou.
- `cd app && pnpm test --watch=false`: falhou em 4 suites preexistentes fora do escopo (`chat-contact-view`, `chat-message-media`, `chat-store.resync`, `lead-export-button`).
