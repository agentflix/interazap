# Feature: security-quality-hardening

**Status:** [ ] Em planejamento | [ ] Em execução | [x] Concluída
**Data:** 2026-05-23
**PRD:** `.context/DOCS/PRDS/0002-PRD-security-quality-hardening.md`

## Metadados
- ID: FEAT-002
- PRD: .context/DOCS/PRDS/0002-PRD-security-quality-hardening.md
- Bounded Context: api/Ai + api/Chat + api/Configuration (Jobs)
- Complexidade: P
- Status: ✅ Concluída
- Commit: 9fc7ab1

## Visão Geral

Corrigir 8 jobs Laravel que fazem `find()` em modelos `BelongsToTenant` sem tenant scope explícito. `TenantScope` é silenciado em contexto de job (sem auth user, sem TenantContext), deixando queries sem filtro de tenant. Defense-in-depth: garantir que cada job scopa sua query ao tenant correto.

**Descoberta de contexto:** Análise inicial de 56 achados foi verificada — todos os outros itens (auth guards, interceptors, JWT secrets, void handlers, IDOR em controllers, etc.) já estavam corretamente implementados. Esta feature aborda os únicos achados reais.

## Módulos Afetados

- [x] api/ (Laravel 12) — 7 arquivos de job + dispatch sites
- [ ] gateway/ (NestJS 11)
- [ ] app/ (Angular 20)
- [ ] Infraestrutura

## Escopo

### Incluído
- [x] `AiMediaTranscriptionJob` — adicionar `where('tenant_id')` no `ChatMessage::find`
- [x] `SubmitMetaTemplateJob` — adicionar `$tenantId` ao construtor + find com scope
- [x] `SendNotificationJob` — adicionar `$tenantId` ao construtor + find com scope
- [x] `AiKnowledgeProcessJob` — adicionar `$tenantId` ao construtor + find com scope
- [x] `AiPromptGuardianJob` — adicionar `$tenantId` ao construtor + find com scope
- [x] `AiRunExecutionJob` — adicionar `$tenantId` ao construtor + find com scope
- [x] `DispatchAutopilotRunJob` — adicionar `where('tenant_id')` no `ChatMessage::find` inline
- [x] Atualizar todos os dispatch sites para passar `$tenantId`

### Fora de Escopo
- Refactor de TenantScope para throw em vez de silent return
- Testes E2E de cross-tenant job access
- Demais achados da análise original (todos já corretamente implementados)

## Dependências

- **Features:** Nenhuma
- **Módulos:** api/Shared (TenantScope, BelongsToTenant)
- **Externas:** Nenhuma

## Critérios de Aceite

- [ ] `grep -n "ChatMessage::find" api/src/Domain/Ai/Jobs/AiMediaTranscriptionJob.php` mostra `where('tenant_id'`
- [ ] `grep -n "ChatMessage::query" api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` (linha ~223) mostra `where('tenant_id'`
- [ ] `SubmitMetaTemplateJob::__construct` tem parâmetro `string $tenantId`
- [ ] `SendNotificationJob::__construct` tem parâmetro `string $tenantId`
- [ ] `AiKnowledgeProcessJob::__construct` tem parâmetro `string $tenantId`
- [ ] `AiPromptGuardianJob::__construct` tem parâmetro `string $tenantId`
- [ ] `AiRunExecutionJob::__construct` tem parâmetro `string $tenantId`
- [ ] `composer gate:all` passa sem regressões

## Fases Estimadas

- [x] **Fase 1 — Planning** ✅
- [ ] **Fase 2 — Design** N/A
- [ ] **Fase 3 — Backend** (8 jobs + dispatch sites)
- [ ] **Fase 4 — Frontend** N/A
- [ ] **Fase 5 — Integration** N/A

## Tasks

Ver `.context/DOCS/TASKS/security-quality-hardening-tasks.md`

## Notas

### Padrões de fix

**Padrão A** — Job já tem `$tenantId` no construtor:
```php
// Antes
$message = ChatMessage::find($this->messageId);
// Depois
$message = ChatMessage::query()->where('tenant_id', $this->tenantId)->find($this->messageId);
```

**Padrão B** — Job sem `$tenantId` no construtor:
1. Adicionar `private readonly string $tenantId` ao construtor
2. Atualizar dispatch sites para passar o tenantId
3. Usar no find: `->where('tenant_id', $this->tenantId)->find($id)`

### Dispatch sites mapeados
- `SubmitMetaTemplateJob`: `api/src/Domain/Chat/Actions/CreateMetaTemplateAction.php:92`
- `SendNotificationJob`: `api/src/Domain/Configuration/Services/NotificationDispatcherService.php:111`
- `AiKnowledgeProcessJob`: 4 sites — `ReindexDocumentAction:45`, `IngestUrlAction:68`, `UploadDocumentAction:93`, `ReindexAllKnowledgeDocumentsCommand:54,56`
- `AiPromptGuardianJob`: `api/src/Domain/Ai/Actions/Prompts/UpdateTenantPromptAction.php:72`
- `AiRunExecutionJob`: `AiAutopilotRunActions.php:247`, `AiAgentDelegationService.php:370`

### Canônico de referência
- Job pattern: `api/src/Domain/Ai/Jobs/AiPromptGuardianJob.php`
- TenantScope: `api/src/Domain/Shared/Scopes/TenantScope.php`
- BelongsToTenant: `api/src/Domain/Shared/Concerns/BelongsToTenant.php`
