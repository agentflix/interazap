# PLAN-009-ticket-notifications-integration — Integração de Notificações de Tickets no Frontend

> **VERSÃO 2** — Atualizada após QA + REVIEWER

## Objetivo

Integrar o componente `NotificationDropdownComponent` com a API de notificações do backend para exibir notificações de tickets (criados, atribuídos, encerrados) em tempo real.

## Módulo relacionado

Configuration | Frontend

## PRD relacionado

Não aplicável — correção de bug de integração.

## Escopo

### Incluído

1. **Renomear** `NotificationService` → `NativeNotificationService` (lida com SO native notifications)
2. **Criar** `NotificationApiService` para chamadas REST à API de notificações
3. **Criar** interface `Notification` em `notification.model.ts` com campos completos
4. **Criar** enum `NotificationTypeEnum` para mapeamento de tipos
5. **Atualizar** `NotificationDropdownComponent` para:
    - Buscar notificações reais ao abrir
    - Exibir lista de notificações
    - Mapear tipos → ícones Lucide
    - Estados: loading, empty, error
    - Marcar como lida ao clicar
    - Cleanup com `DestroyRef` + `takeUntilDestroyed`
6. **Criar** testes Vitest

### Excluído

- Notificações push/web (futura iteração)
- Preferências de notificação
- Histórico de notificações lidas
- WebSocket/real-time (polling simples)

## Etapas propostas

### Etapa 1 — NativeNotificationService

1. Renomear `notification.service.ts` → `native-notification.service.ts`
2. Atualizar imports em `electron.service.ts` e outros consumidores
3. Verificar que todos os métodos originais (`show()`, `showChatMessage()`, etc) continuam funcionando

### Etapa 2 — Criar NotificationApiService

1. Criar `notification-api.service.ts` em `app/src/app/core/services/`
2. Implementar:
    - `fetchUnread(limit?: number): Observable<NotificationListResponse>` → `GET /api/configuration/notifications?limit=N`
    - `markAsRead(id: string): Observable<void>` → `PATCH /api/configuration/notifications/{id}/read`
    - `markAllAsRead(): Observable<{count: number, message: string}>` → `POST /api/configuration/notifications/read-all`

### Etapa 3 — Criar Interface e Enum

1. Criar `notification.model.ts` em `app/src/app/shared/models/`:

```typescript
export interface Notification {
    id: string;
    tenant_id: string;
    user_id: string;
    type: string;
    title: string;
    body: string | null;
    data?: Record<string, unknown>;
    channel?: string;
    status?: string;
    sent_at?: string | null;
    read_at?: string | null;
    created_at: string;
}

export interface NotificationListResponse {
    data: Notification[];
    unread_count: number;
}
```

2. Criar enum `NotificationTypeEnum`:

```typescript
export enum NotificationTypeEnum {
    NewTicket = 'new_ticket',
    TicketAssigned = 'ticket_assigned',
    TicketClosed = 'ticket_closed',
    System = 'system',
    Billing = 'billing',
}
```

### Etapa 4 — Atualizar NotificationDropdownComponent

1. Inject `NotificationApiService` via `inject()`
2. Adicionar signals: `notifications = signal<Notification[]>([])`, `loading = signal(false)`, `error = signal<string | null>(null)`
3. Buscar notificações ao abrir dropdown
4. Implementar template com estados (loading/empty/error)
5. Adicionar `track notification.id` no `@for`
6. Adicionar `DestroyRef` + `takeUntilDestroyed` para cleanup
7. Mapear tipos para ícones:
    - `new_ticket` → `ticket`
    - `ticket_assigned` → `user-check`
    - `ticket_closed` → `check-circle`
    - `system` → `info`
    - `billing` → `credit-card`

### Etapa 5 — Criar Testes

1. Criar `notification-api.service.spec.ts`
2. Criar `notification-dropdown.spec.ts`

### Etapa 6 — Validar

1. `pnpm run gate:all` em app/
2. Verificar que auth token é enviado (HttpInterceptor)

## Tasks derivadas

| Task          | Descrição                       | Agente    | Status |
| ------------- | ------------------------------- | --------- | ------ |
| TASK-NOTIF-FE | Implementar integração frontend | @FRONTEND | todo   |

## Riscos e dependências

### Riscos

| Risco                                           | Probabilidade | Impacto | Mitigação                  |
| ----------------------------------------------- | ------------- | ------- | -------------------------- |
| Breaking change ao renomear NotificationService | Baixa         | Alto    | Atualizar todos os imports |
| Auth token não enviado                          | Baixa         | Alto    | Verificar HttpInterceptor  |

### Dependências

- Backend API `ConfigurationNotificationController` ✓
- `NativeNotificationService` existente ✓
- HttpClient interceptor para auth ✓

## Estimativa

| Item                          | Valor    |
| ----------------------------- | -------- |
| Complexidade                  | Baixa    |
| Camadas afetadas              | Frontend |
| Migrações necessárias         | Não      |
| Impacto em módulos existentes | Mínimo   |

## Arquivos a Modificar

### Frontend (Angular)

| Arquivo                            | Ação                                        | Caminho                                 |
| ---------------------------------- | ------------------------------------------- | --------------------------------------- |
| `notification.service.ts`          | Renomear → `native-notification.service.ts` | `app/src/app/core/services/`            |
| `native-notification.service.ts`   | Atualizar imports de consumidores           | `app/src/app/core/services/`            |
| `notification-api.service.ts`      | Criar                                       | `app/src/app/core/services/`            |
| `notification.model.ts`            | Criar                                       | `app/src/app/shared/models/`            |
| `notification-dropdown.ts`         | Modificar                                   | `app/src/app/layout/components/topbar/` |
| `notification-api.service.spec.ts` | Criar                                       | `app/src/app/core/services/`            |
| `notification-dropdown.spec.ts`    | Criar                                       | `app/src/app/layout/components/topbar/` |

## Evidências da Codebase

### Backend API

- `ConfigurationNotificationController::index()` → `GET /api/configuration/notifications`
- `ConfigurationNotificationController::markAsRead(string $id)` → `PATCH /api/configuration/notifications/{id}/read`
- `ConfigurationNotificationController::markAllAsRead()` → `POST /api/configuration/notifications/read-all`

### Frontend Atual

- `NotificationDropdownComponent` linha 71: `readonly unreadCount = signal(0);` hardcoded
- `NotificationService` métodos: `show()`, `showChatMessage()`, `showTicketUpdate()`, etc.

## Validação e Gates

- [ ] `pnpm run gate:all` em app/
- [ ] Verificar imports atualizados após renomear
- [ ] Verificar HttpClient interceptor envia auth token

## Checkbox de Validação QA (v1)

- [x] **CRITICAL #1**: Resolvido — criado `NotificationApiService` separado
- [x] **CRITICAL #2**: Resolvido — endpoint `PATCH /{id}/read`
- [x] **CRITICAL #3**: Resolvido — endpoint `POST /read-all`
- [x] **MAJOR #1**: Resolvido — testes Vitest incluídos
- [x] **MAJOR #2**: Resolvido — loading/empty/error states detalhados
- [x] **MAJOR #3**: Resolvido — campo `status` na interface
- [x] **MAJOR #4**: Resolvido — estratégia de polling simples definida
- [x] **MINOR #2**: Resolvido — `DestroyRef` + `takeUntilDestroyed`
- [x] **MINOR #3**: Resolvido — TASK detalhada com breakdown
