# Tasks: Chat Calendar Scheduling (FEAT-001)

Feature doc: `.context/DOCS/FEATURES/chat-calendar-scheduling.md`
Status geral: 🟡 Planning | 1/12 tasks concluídas

---

## FASE 1: PLANNING ✅

### 1.1 — Feature doc

- [x] **TASK-1.1.1** ✅: Criar feature doc

  **T — Tarefa:** Feature doc `chat-calendar-scheduling.md` criada.

  **A — Arquivo:** `.context/DOCS/FEATURES/chat-calendar-scheduling.md`

  **E — Evidência:**
  - [x] Feature doc com bounded context, complexidade G, escopo e critérios de aceite
  - [x] Flags MULTI-TENANT e WHATSAPP marcadas
  - [x] Mockups baseados em DESIGN.md incluídos

  **Status:** ✅ Concluída

---

## FASE 3: BACKEND (api/)

> Dependência: TASK-3.1.x deve preceder TASK-3.2.x. TASK-3.3.x pode rodar em paralelo com 3.2.x após 3.1.x.

---

### 3.1 — DBA: Migrations

- [x] **TASK-3.1.1** ✅: Migration `crm_event_client_confirmations`

  **T — Tarefa:** Criar migration para tabela de confirmações de agendamento do cliente.

  **A — Arquivo:** `api/database/migrations/2026_05_18_100000_create_crm_event_client_confirmations_table.php`

  **C — Comportamento:**
  ANTES:
  - Tabela `crm_event_client_confirmations` não existe

  DEPOIS:
  - Tabela criada com colunas:
    - `id` UUID PK
    - `tenant_id` UUID FK NOT NULL
    - `crm_event_id` UUID FK → `crm_events.id` ON DELETE CASCADE
    - `crm_contact_id` UUID FK nullable → `crm_contacts.id` ON DELETE SET NULL
    - `chat_ticket_id` UUID FK nullable → `chat_tickets.id` ON DELETE SET NULL
    - `status` ENUM(`pending`, `confirmed`, `declined`) DEFAULT `pending`
    - `minutes_before` UNSIGNED INT NOT NULL DEFAULT 1440 (24h)
    - `reminder_sent_at` TIMESTAMP nullable
    - `response_at` TIMESTAMP nullable
    - `no_response_notified_at` TIMESTAMP nullable
    - `timestamps()`
  - Index em `(tenant_id, status)`, `(crm_event_id)`, `(chat_ticket_id)`
  - `down()` com `dropIfExists`

  **E — Evidência:**
  - [x] `php artisan migrate --pretend` executa sem erro
  - [x] `php artisan migrate` cria tabela
  - [x] Syntax check: 0 erros
  - [x] Migration tem `down()` com `Schema::dropIfExists`

  **Status:** ✅ Concluída

---

- [x] **TASK-3.1.2** ✅: Migration `configuration_scheduling_settings`

  **T — Tarefa:** Criar migration para tabela de configurações de agendamento por tenant.

  **A — Arquivo:** `api/database/migrations/2026_05_18_100001_create_configuration_scheduling_settings_table.php`

  **C — Comportamento:**
  ANTES:
  - Tenant não tem configuração específica de antecedência de confirmação

  DEPOIS:
  - Tabela `configuration_scheduling_settings` com:
    - `id` UUID PK
    - `tenant_id` UUID FK NOT NULL UNIQUE → `platform_tenants.id`
    - `event_confirmation_advance_minutes` UNSIGNED INT NOT NULL DEFAULT 1440
    - `event_confirmation_enabled` BOOLEAN DEFAULT true
    - `event_confirmation_notify_ui` BOOLEAN DEFAULT true
    - `event_confirmation_notify_push` BOOLEAN DEFAULT true
    - `timestamps()`
  - Index UNIQUE em `tenant_id`
  - `down()` com `dropIfExists`

  **E — Evidência:**
  - [x] `php artisan migrate --pretend` sem erro
  - [x] `php artisan migrate` cria tabela
  - [x] Migration tem `down()` com `Schema::dropIfExists`
  - [x] Syntax check: 0 erros

  **Status:** ✅ Concluída

---

### 3.2 — Domain: Models

- [x] **TASK-3.2.1** ✅: Model `CRMEventClientConfirmation`

  **T — Tarefa:** Criar Eloquent model para `crm_event_client_confirmations`.

  **A — Arquivo:** `api/src/Domain/CRM/Models/CRMEventClientConfirmation.php`

  **C — Comportamento:**
  ANTES:
  - Model não existe

  DEPOIS:
  - `final class CRMEventClientConfirmation extends Model`
  - `use BelongsToTenant`
  - `$table = 'crm_event_client_confirmations'`
  - `$fillable` com todos os campos
  - `casts()`: `status` como enum string, `reminder_sent_at`, `response_at`, `no_response_notified_at` como Carbon
  - Constants: `STATUS_PENDING = 'pending'`, `STATUS_CONFIRMED = 'confirmed'`, `STATUS_DECLINED = 'declined'`
  - Relações: `crmEvent()`, `crmContact()`, `chatTicket()`
  - Scopes: `scopePending()`, `scopeReadyToSend()` (reminder_sent_at is null AND crm_event.starts_at - minutes_before <= now)

  **E — Evidência:**
  - [x] Model criado com `BelongsToTenant` e `HasUuids`
  - [x] Syntax check: 0 erros
  - [x] Scopes `pending()` e `readyToSend()` implementados

  **Status:** ✅ Concluída

---

- [x] **TASK-3.2.2** ✅: Model `ConfigurationSchedulingSetting`

  **T — Tarefa:** Criar Eloquent model para `configuration_scheduling_settings`.

  **A — Arquivo:** `api/src/Domain/Configuration/Models/ConfigurationSchedulingSetting.php`

  **C — Comportamento:**
  ANTES:
  - Não há configuração de agendamento por tenant

  DEPOIS:
  - `final class ConfigurationSchedulingSetting extends Model`
  - `use BelongsToTenant`
  - `$table = 'configuration_scheduling_settings'`
  - Campos: `tenant_id`, `event_confirmation_advance_minutes`, `event_confirmation_enabled`, `event_confirmation_notify_ui`, `event_confirmation_notify_push`
  - Método estático `ConfigurationSchedulingSetting::forTenant(string $tenantId)` — busca ou cria com defaults
  - `casts()`: campos boolean como `bool`, `event_confirmation_advance_minutes` como `int`

  **E — Evidência:**
  - [x] Model criado com `BelongsToTenant` e `HasUuids`
  - [x] Método `forTenant()` com `firstOrCreate` e defaults
  - [x] Syntax check: 0 erros

  **Status:** ✅ Concluída

---

### 3.3 — Domain: AI Tools

- [x] **TASK-3.3.1** ✅: Tool `GetAvailableSlotsTool`

  **T — Tarefa:** Criar nova AI tool que retorna horários livres no calendário CRM do operador.

  **A — Arquivos:**
  - `api/src/Domain/Ai/Tools/GetAvailableSlotsTool.php` ← novo
  - `api/src/Domain/Ai/Enums/AiToolEnum.php` ← adicionar `GET_AVAILABLE_SLOTS`
  - `api/src/Domain/Ai/Services/AiPermissionMatrixService.php` ← adicionar ao role `appointment` e `general`

  **C — Comportamento:**
  ANTES:
  - `CheckAvailabilityTool` só verifica se um intervalo específico tem conflito — não retorna slots livres
  - AI agent não consegue sugerir horários disponíveis proativamente

  DEPOIS:
  - Nova tool `get_available_slots` registrada em `AiToolEnum::GET_AVAILABLE_SLOTS = 'get_available_slots'`
  - Parâmetros: `date_from` (ISO), `date_to` (ISO), `duration_minutes` (int, default 30), `user_id` (UUID, opcional)
  - Lógica:
    1. Busca `CRMEvent` do tenant no período (filtro por `user_id` se fornecido, senão todos os eventos do tenant)
    2. Busca horário de funcionamento via `ConfigurationOpeningHour` do tenant
    3. Divide o período em slots da `duration_minutes` respeitando abertura/fechamento
    4. Remove slots com conflito com eventos existentes
    5. Retorna lista de até 8 slots livres: `[{starts_at, ends_at}]`
  - Adicionada ao `AiPermissionMatrixService::MATRIX` no role `appointment` (e `general`)
  - Resolve automaticamente via `ToolDispatcherService` por naming convention `GetAvailableSlotsTool`

  **E — Evidência:**
  - [x] Tool criada implementando `AiToolInterface`
  - [x] Adicionada ao `AiToolEnum` e `AiPermissionMatrixService`
  - [x] Syntax check: 0 erros

  **Status:** ✅ Concluída

---

- [x] **TASK-3.3.2** ✅: Tool `ConfirmEventBookingTool`

  **T — Tarefa:** Criar AI tool chamada pelo agente ao interpretar resposta do cliente (confirmar ou recusar).

  **A — Arquivos:**
  - `api/src/Domain/Ai/Tools/ConfirmEventBookingTool.php` ← novo
  - `api/src/Domain/Ai/Enums/AiToolEnum.php` ← adicionar `CONFIRM_EVENT_BOOKING`
  - `api/src/Domain/Ai/Services/AiPermissionMatrixService.php` ← adicionar ao role `appointment` e `general`

  **C — Comportamento:**
  ANTES:
  - Agente AI não tem como atualizar o status de uma confirmação nem notificar o tenant

  DEPOIS:
  - Nova tool `confirm_event_booking` registrada em `AiToolEnum::CONFIRM_EVENT_BOOKING = 'confirm_event_booking'`
  - Parâmetros: `confirmation_id` (UUID), `action` (`confirmed` | `declined`)
  - Lógica:
    1. Busca `CRMEventClientConfirmation` do tenant pelo `confirmation_id`
    2. Atualiza `status` e `response_at = now()`
    3. Se `confirmed`: atualiza `CRMEvent.status = 'confirmed'`, dispara `NotificationDispatcherService` ao tenant com tipo `event_confirmed`
    4. Se `declined`: atualiza `CRMEvent.status = 'cancelled'`, dispara `NotificationDispatcherService` ao tenant com tipo `event_cancelled`
    5. Retorna `ToolResultDTO::success` com dados do evento para o AI formular resposta ao cliente
  - Adicionada ao `AiPermissionMatrixService::MATRIX` no role `appointment` e `general`

  **E — Evidência:**
  - [x] Tool criada com injeção de `NotificationDispatcherService`
  - [x] Adicionada ao `AiToolEnum` e `AiPermissionMatrixService`
  - [x] Isolamento por tenant validado (query com `tenant_id`)
  - [x] Syntax check: 0 erros

  **Status:** ✅ Concluída

---

- [x] **TASK-3.3.3** ✅: Estender `ScheduleEventTool` para criar `CRMEventClientConfirmation`

  **T — Tarefa:** Após criar `CRMEvent`, criar `CRMEventClientConfirmation` e agendar job de lembrete.

  **A — Arquivo:** `api/src/Domain/Ai/Tools/ScheduleEventTool.php`

  **C — Comportamento:**
  ANTES:
  - `ScheduleEventTool::handle()` cria `CRMEvent` e opcionalmente `CRMEventParticipant`, retorna `event_id`
  - Nenhum lembrete de confirmação é gerado

  DEPOIS:
  - Após criar `CRMEvent`, busca `ConfigurationSchedulingSetting::forTenant($tenantId)`
  - Se `event_confirmation_enabled = true`:
    1. Cria `CRMEventClientConfirmation` com `status = pending`, `minutes_before` da config, `chat_ticket_id` do contexto (`$input->context['ticket_id']`)
    2. Calcula `scheduled_at = $event->starts_at->subMinutes($setting->event_confirmation_advance_minutes)`
    3. Despacha `ProcessEventConfirmationReminderJob::dispatch($confirmation->id)->delay($scheduledAt)`
  - `ToolResultDTO::success` passa `confirmation_id` nos dados retornados

  **E — Evidência:**
  - [x] Import de `ConfigurationSchedulingSetting` e `CRMEventClientConfirmation` adicionados
  - [x] Criação de confirmação condicional (enabled/disabled)
  - [x] Job agendado com `delay()` quando `scheduledAt` é futuro
  - [x] `confirmation_id` retornado no `ToolResultDTO`
  - [x] Syntax check: 0 erros

  **Status:** ✅ Concluída

---

### 3.4 — Application: Jobs

- [x] **TASK-3.4.1** ✅: Job `ProcessEventConfirmationReminderJob`

  **T — Tarefa:** Job assíncrono que dispara reengajamento do AI agent no ticket para pedir confirmação ao cliente.

  **A — Arquivo:** `api/src/Domain/Ai/Jobs/ProcessEventConfirmationReminderJob.php`

  **C — Comportamento:**
  ANTES:
  - Nenhum mecanismo envia lembrete de confirmação ao cliente

  DEPOIS:
  - `final class ProcessEventConfirmationReminderJob implements ShouldQueue`
  - `$timeout = 60`, `$tries = 3`, `$backoff = [30, 120, 300]`
  - Construtor: `__construct(public readonly string $confirmationId)`
  - `handle()`:
    1. Busca `CRMEventClientConfirmation` com `crm_event` eager loaded
    2. Valida: se `status != pending` ou `reminder_sent_at != null` → skip (idempotente)
    3. Atualiza `reminder_sent_at = now()`
    4. Prepara contexto do evento: título, data/hora, contato
    5. Dispara `DispatchAutopilotRunJob` no ticket de origem (`confirmation->chat_ticket_id`) com contexto adicional `['confirmation_context' => [...], 'confirmation_id' => $id]`
    6. Log: `[ProcessEventConfirmationReminderJob] Dispatched autopilot for confirmation {id}`

  **E — Evidência:**
  - [x] Job criado com `ShouldQueue`, `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels`
  - [x] Idempotência: skip se status != pending ou reminder_sent_at != null
  - [x] Graceful skip quando `chat_ticket_id` é null
  - [x] Syntax check: 0 erros

  **Status:** ✅ Concluída

---

### 3.5 — HTTP: Configurações de Agendamento

- [x] **TASK-3.5.1** ✅: Endpoint HTTP para CRUD de `ConfigurationSchedulingSetting`

  **T — Tarefa:** Expor GET e PUT para configurações de agendamento do tenant autenticado.

  **A — Arquivos:**
  - `api/src/Domain/Configuration/Http/Controllers/ConfigurationSchedulingSettingController.php`
  - `api/src/Domain/Configuration/Http/Requests/UpdateSchedulingSettingRequest.php`
  - `api/src/Domain/Configuration/Http/Resources/ConfigurationSchedulingSettingResource.php`
  - `api/src/Domain/Configuration/Actions/ConfigurationSchedulingSettingActions.php`
  - `api/src/Domain/Configuration/Routes/configuration.php` (adicionar rotas)
  - `api/src/Domain/Configuration/Policies/ConfigurationSchedulingSettingPolicy.php`

  **C — Comportamento:**
  ANTES:
  - Nenhum endpoint para configuração de agendamento

  DEPOIS:
  - `GET /api/configuration/scheduling` → retorna `ConfigurationSchedulingSettingResource` do tenant autenticado
  - `PUT /api/configuration/scheduling` → valida e salva `event_confirmation_advance_minutes` (min: 15, max: 4320), `event_confirmation_enabled` (bool), `event_confirmation_notify_ui` (bool), `event_confirmation_notify_push` (bool)
  - Controller usa `ConfigurationSchedulingSettingActions::get(string $tenantId)` e `ConfigurationSchedulingSettingActions::update(string $tenantId, array $data)`
  - Ambas as rotas com middleware `auth:sanctum`

  **E — Evidência:**
  - [x] Controller, Actions, Request, Resource e Policy criados
  - [x] Rotas adicionadas em `configuration.php`
  - [x] Policy registrada em `AuthServiceProvider`
  - [x] Validação: min 15, max 4320 minutos
  - [x] Syntax check: 0 erros

  **Status:** ✅ Concluída

---

### Revisão de Fase 3 (@REVIEWER + @QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Domain Layer sem imports de infra (Framework) | @REVIEWER | ✅ |
| Migrations com `up()` e `down()` implementados | @DBA | ✅ |
| `BelongsToTenant` em todos os models novos | @REVIEWER | ✅ |
| Policies registradas em `AuthServiceProvider` | @REVIEWER | ✅ |
| Isolamento multi-tenant via `BelongsToTenant` | @QA | ✅ |
| Job idempotente (re-run seguro) | @REVIEWER | ✅ |
| `ConfirmEventBookingTool` não acessa confirmações de outro tenant | @QA | ✅ |

**Gate de Qualidade Fase 3:** ✅ Syntax check: 0 erros | Migrations executadas com sucesso

---

## FASE 5: FRONTEND (app/)

### 5.1 — Componente: Configurações de Agendamento

- [x] **TASK-5.1.1** ✅: `SchedulingSettingsComponent` — UI para antecedência do lembrete

  **T — Tarefa:** Criar componente Angular standalone na tela de Configurações para gerenciar as configs de agendamento.

  **A — Arquivos:**
  - `app/src/app/pages/settings/scheduling/scheduling-settings.component.ts`
  - `app/src/app/pages/settings/scheduling/scheduling-settings.component.html`
  - `app/src/app/core/models/configuration/scheduling-setting.model.ts`
  - `app/src/app/core/services/configuration/scheduling-setting.service.ts`
  - `app/src/app/app.routes.ts` (adicionar rota `settings/scheduling`)

  **C — Comportamento:**
  ANTES:
  - Não há seção de configuração de agendamento no painel do tenant

  DEPOIS:
  - Componente standalone com `ReactiveFormsModule`
  - Formulário com:
    - `<select>` para `event_confirmation_advance_minutes` (opções: 15min, 30min, 1h, 2h, 6h, 12h, 24h, 48h, 72h)
    - `<toggle>` para `event_confirmation_enabled`
    - Checkboxes para `event_confirmation_notify_ui` e `event_confirmation_notify_push`
  - `GET /api/configuration/scheduling` ao init para carregar valores atuais
  - `PUT /api/configuration/scheduling` ao submit
  - Toast de sucesso/erro após save
  - Tokens de design: `card-feature`, `button-primary` (pill verde), `button-secondary`, `text-input` (44px, `rounded.md`)
  - Vitest spec: `scheduling-settings.component.spec.ts`

  **E — Evidência:**
  - [x] Componente standalone criado com `ChangeDetectionStrategy.OnPush`
  - [x] Service com `getSettings()` e `updateSettings()` via HTTP
  - [x] Rota `settings/scheduling` registrada em `app.routes.ts`
  - [x] Spec criado (padrão Vitest do projeto)
  - [x] `pnpm build` sucesso

  **Status:** ✅ Concluída

---

### Revisão de Fase 5 (@REVIEWER + @QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Componente standalone (sem NgModule) | @REVIEWER | ⏳ |
| Service usa `HttpClient` sem lógica de negócio inline | @REVIEWER | ⏳ |
| Vitest: 0 falhas, coverage ≥ 70% | @QA | ⏳ |
| ESLint: 0 warnings | @QA | ⏳ |
| Build limpo sem warnings de template | @QA | ⏳ |
| Design tokens aplicados conforme mockup | @REVIEWER | ⏳ |

**Gate de Qualidade Fase 5:** ⏳ Pendente — `cd app && npm run gate:all 2>&1`

---

## Progresso Geral

| Fase | Tasks | Concluídas | Gate |
|------|-------|-----------|------|
| 1 — Planning | 1 | 1 | ✅ |
| 3 — Backend (DBA) | 2 | 2 | ✅ |
| 3 — Backend (Domain) | 2 | 2 | ✅ |
| 3 — Backend (AI Tools) | 3 | 3 | ✅ |
| 3 — Backend (Jobs) | 1 | 1 | ✅ |
| 3 — Backend (HTTP) | 1 | 1 | ✅ |
| 5 — Frontend | 1 | 0 | ⏳ |
| **Total** | **11** | **10** | |

---

## Ordem de Execução Recomendada

```
TASK-3.1.1 → TASK-3.1.2           (migrations primeiro, em paralelo)
     ↓
TASK-3.2.1 + TASK-3.2.2           (models, em paralelo)
     ↓
TASK-3.3.1 + TASK-3.3.2           (tools novas, em paralelo)
     ↓
TASK-3.3.3                         (estender ScheduleEventTool — depende de 3.2.1)
     ↓
TASK-3.4.1                         (job — depende de 3.2.1 e 3.3.3)
     ↓
TASK-3.5.1                         (HTTP — depende de 3.2.2)
     ↓
[ Gate Fase 3: composer gate:all ]
     ↓
TASK-5.1.1                         (frontend — depende de 3.5.1 estar disponível)
     ↓
[ Gate Fase 5: cd app && npm run gate:all 2>&1 ]
```
