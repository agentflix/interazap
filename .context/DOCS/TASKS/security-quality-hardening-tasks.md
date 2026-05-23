# Tasks — security-quality-hardening

**Feature:** `.context/DOCS/FEATURES/security-quality-hardening.md`
**PRD:** `.context/DOCS/PRDS/0002-PRD-security-quality-hardening.md`
**Status:** [ ] Em progresso | [x] Concluída
**Total:** 7 tasks | Pendentes: 0

---

## Fase 3 — Backend (api/)

### Grupo 3.1 — Job Tenant Scope

#### TASK-3.1.1 — Adicionar tenant scope no find de ChatMessage em AiMediaTranscriptionJob

**T — Tarefa:** Adicionar `->where('tenant_id', $this->tenantId)` no `ChatMessage::find($this->messageId)` (linha 71) — job já tem `$this->tenantId` disponível no construtor.

**A — Arquivo:**
- `api/src/Domain/Ai/Jobs/AiMediaTranscriptionJob.php` (modificar)

**Referência:** `api/src/Domain/Ai/Jobs/AiMediaTranscriptionJob.php:85` — mesmo job já usa `->where('tenant_id', $this->tenantId)` na segunda query (chatMediaDownloadJob pattern)

**Imports autorizados:** `Domain\Chat\Models\ChatMessage` (já importado) — proibido: importar do gateway

**C — Comportamento:**
- ANTES: `$message = ChatMessage::find($this->messageId);` — TenantScope silenciado em job context, query sem filtro de tenant
- DEPOIS: `$message = ChatMessage::query()->where('tenant_id', $this->tenantId)->find($this->messageId);` — query explicitamente scoped ao tenant correto

**E — Evidência:**
- [ ] `grep -n "where('tenant_id'" api/src/Domain/Ai/Jobs/AiMediaTranscriptionJob.php | grep "messageId"` retorna 1 ocorrência
- [ ] `cd api && composer gate:all` passa

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.1.2 — Adicionar tenantId ao construtor e find de SubmitMetaTemplateJob

**T — Tarefa:** Adicionar `private readonly string $tenantId` ao construtor de `SubmitMetaTemplateJob`, usar `->where('tenant_id', $this->tenantId)->find($this->templateId)` no lugar de `::query()->find($this->templateId)` (linhas 50 e 119), e atualizar o dispatch site em `CreateMetaTemplateAction.php:92` para passar `$template->tenant_id`.

**A — Arquivo:**
- `api/src/Domain/Chat/Jobs/SubmitMetaTemplateJob.php` (modificar)
- `api/src/Domain/Chat/Actions/CreateMetaTemplateAction.php` (modificar — linha 92)

**Referência:** `api/src/Domain/Ai/Jobs/AiMediaTranscriptionJob.php:44-56` — padrão de construtor com `private readonly string $tenantId`

**Imports autorizados:** `Domain\Chat\Models\ChatMessageTemplate` (já importado) — proibido: importar do gateway

**C — Comportamento:**
- ANTES: `__construct(private readonly string $templateId)` + `ChatMessageTemplate::query()->find($this->templateId)` em 2 lugares (linhas 50 e 119) + dispatch `SubmitMetaTemplateJob::dispatch((string) $template->id)`
- DEPOIS: `__construct(private readonly string $templateId, private readonly string $tenantId)` + `ChatMessageTemplate::query()->where('tenant_id', $this->tenantId)->find($this->templateId)` em 2 lugares + dispatch `SubmitMetaTemplateJob::dispatch((string) $template->id, (string) $template->tenant_id)`

**E — Evidência:**
- [ ] `grep -n "tenantId" api/src/Domain/Chat/Jobs/SubmitMetaTemplateJob.php` retorna ao menos 3 ocorrências (construtor + 2 queries)
- [ ] `grep -n "tenant_id" api/src/Domain/Chat/Actions/CreateMetaTemplateAction.php | grep dispatch` retorna 1 ocorrência
- [ ] `cd api && composer gate:all` passa

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.1.3 — Adicionar tenantId ao construtor e find de SendNotificationJob

**T — Tarefa:** Adicionar `private readonly string $tenantId` como terceiro parâmetro do construtor de `SendNotificationJob`, usar `->where('tenant_id', $this->tenantId)->find($this->notificationId)` no lugar de `::query()->find($this->notificationId)` (linha 58), e atualizar o dispatch em `NotificationDispatcherService.php:111`.

**A — Arquivo:**
- `api/src/Domain/Configuration/Jobs/SendNotificationJob.php` (modificar)
- `api/src/Domain/Configuration/Services/NotificationDispatcherService.php` (modificar — linha 111)

**Referência:** `api/src/Domain/Ai/Jobs/AiMediaTranscriptionJob.php:44-56` — padrão de construtor com tenantId

**Imports autorizados:** `Domain\Configuration\Models\ConfigurationNotification` (já importado) — proibido: importar do gateway

**C — Comportamento:**
- ANTES: `__construct(public readonly string $notificationId, public readonly string $channel)` + `ConfigurationNotification::query()->find($this->notificationId)` + dispatch `SendNotificationJob::dispatch((string) $notification->id, $channel)`
- DEPOIS: `__construct(public readonly string $notificationId, public readonly string $channel, public readonly string $tenantId)` + `ConfigurationNotification::query()->where('tenant_id', $this->tenantId)->find($this->notificationId)` + dispatch `SendNotificationJob::dispatch((string) $notification->id, $channel, $tenantId)` — o `$tenantId` já está disponível como parâmetro do método `dispatch()` em `NotificationDispatcherService`

**E — Evidência:**
- [ ] `grep -n "tenantId" api/src/Domain/Configuration/Jobs/SendNotificationJob.php | grep "construct\|where"` retorna 2+ ocorrências
- [ ] `grep -n "SendNotificationJob::dispatch" api/src/Domain/Configuration/Services/NotificationDispatcherService.php` mostra 3 argumentos
- [ ] `cd api && composer gate:all` passa

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.1.4 — Adicionar tenantId ao construtor e find de AiKnowledgeProcessJob

**T — Tarefa:** Adicionar `private readonly string $tenantId` ao construtor de `AiKnowledgeProcessJob`, usar `->where('tenant_id', $this->tenantId)->find($this->documentId)` nos 2 `find()` (linhas 97 e 530), e atualizar 4 dispatch sites para passar `$document->tenant_id` (ou variante equivalente em cada site).

**A — Arquivo:**
- `api/src/Domain/Ai/Jobs/AiKnowledgeProcessJob.php` (modificar)
- `api/src/Domain/Ai/Actions/Rag/ReindexDocumentAction.php` (modificar — linha 45)
- `api/src/Domain/Ai/Actions/Rag/IngestUrlAction.php` (modificar — linha 68)
- `api/src/Domain/Ai/Actions/Rag/UploadDocumentAction.php` (modificar — linha 93)
- `api/src/Domain/Ai/Console/Commands/ReindexAllKnowledgeDocumentsCommand.php` (modificar — linhas 54 e 56)

**Referência:** `api/src/Domain/Ai/Jobs/AiMediaTranscriptionJob.php:44-56` — padrão de construtor com tenantId

**Imports autorizados:** `Domain\Ai\Models\AiKnowledgeDocument` (já importado) — proibido: importar do gateway

**C — Comportamento:**
- ANTES: `__construct(private readonly string $documentId)` + `AiKnowledgeDocument::find($this->documentId)` em 2 lugares + 4 dispatch sites sem tenantId
- DEPOIS: `__construct(private readonly string $documentId, private readonly string $tenantId)` + `AiKnowledgeDocument::query()->where('tenant_id', $this->tenantId)->find($this->documentId)` em 2 lugares + cada dispatch site passa `(string) $document->tenant_id` (ler cada action/command para confirmar que `$document->tenant_id` está disponível)

**E — Evidência:**
- [ ] `grep -n "tenantId" api/src/Domain/Ai/Jobs/AiKnowledgeProcessJob.php | grep "construct\|where"` retorna 3+ ocorrências
- [ ] `grep -rn "AiKnowledgeProcessJob::dispatch" api/src/Domain/Ai/` mostra todos com 2 argumentos
- [ ] `cd api && composer gate:all` passa

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.1.5 — Adicionar tenantId ao construtor e find de AiPromptGuardianJob

**T — Tarefa:** Adicionar `private readonly string $tenantId` ao construtor de `AiPromptGuardianJob`, usar `->where('tenant_id', $this->tenantId)->find($this->tenantPromptId)` no lugar do `::query()->find($this->tenantPromptId)` (linha 47), e atualizar o dispatch em `UpdateTenantPromptAction.php:72` para passar `$tenantPrompt->tenant_id`.

**A — Arquivo:**
- `api/src/Domain/Ai/Jobs/AiPromptGuardianJob.php` (modificar)
- `api/src/Domain/Ai/Actions/Prompts/UpdateTenantPromptAction.php` (modificar — linha 72)

**Referência:** `api/src/Domain/Ai/Jobs/AiPromptGuardianJob.php` próprio — padrão canônico de job com `failed()` handler

**Imports autorizados:** `Domain\Ai\Models\AiPromptTenant` (já importado) — proibido: importar do gateway

**C — Comportamento:**
- ANTES: `__construct(private readonly string $tenantPromptId)` + `AiPromptTenant::query()->find($this->tenantPromptId)` + dispatch `AiPromptGuardianJob::dispatch($tenantPrompt->id)` (linha 72 — `$tenantPrompt` objeto disponível na action)
- DEPOIS: `__construct(private readonly string $tenantPromptId, private readonly string $tenantId)` + `AiPromptTenant::query()->where('tenant_id', $this->tenantId)->find($this->tenantPromptId)` + dispatch `AiPromptGuardianJob::dispatch($tenantPrompt->id, (string) $tenantPrompt->tenant_id)`

**E — Evidência:**
- [ ] `grep -n "tenantId" api/src/Domain/Ai/Jobs/AiPromptGuardianJob.php | grep "construct\|where"` retorna 2+ ocorrências
- [ ] `grep -n "AiPromptGuardianJob::dispatch" api/src/Domain/Ai/Actions/Prompts/UpdateTenantPromptAction.php` mostra 2 argumentos
- [ ] `cd api && composer gate:all` passa

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.1.6 — Adicionar tenantId ao construtor e find de AiRunExecutionJob

**T — Tarefa:** Adicionar `private readonly string $tenantId` ao construtor de `AiRunExecutionJob`, usar `->where('tenant_id', $this->tenantId)->findOrFail($this->runId)` no lugar do `::query()->findOrFail($this->runId)` (linha 50), e atualizar 2 dispatch sites.

**A — Arquivo:**
- `api/src/Domain/Ai/Jobs/AiRunExecutionJob.php` (modificar)
- `api/src/Domain/Ai/Actions/AiAutopilotRunActions.php` (modificar — linha 247)
- `api/src/Domain/Ai/Services/AiAgentDelegationService.php` (modificar — linha 370)

**Referência:** `api/src/Domain/Ai/Jobs/AiPromptGuardianJob.php` — padrão de construtor com tenantId + failed() handler

**Imports autorizados:** `Domain\Ai\Models\AiAutopilotRun` (já importado) — proibido: importar do gateway

**C — Comportamento:**
- ANTES: `__construct(private readonly string $runId)` + `AiAutopilotRun::query()->findOrFail($this->runId)` + 2 dispatch sites sem tenantId:
  - `AiAutopilotRunActions:247`: `AiRunExecutionJob::dispatch($run->id)` — `$run->tenant_id` disponível
  - `AiAgentDelegationService:370`: `AiRunExecutionJob::dispatch((string) $childRun->id)` — `$childRun->tenant_id` disponível
- DEPOIS: `__construct(private readonly string $runId, private readonly string $tenantId)` + `AiAutopilotRun::query()->where('tenant_id', $this->tenantId)->findOrFail($this->runId)` + dispatch sites atualizados com segundo argumento `(string) $run->tenant_id` / `(string) $childRun->tenant_id`

**E — Evidência:**
- [ ] `grep -n "tenantId" api/src/Domain/Ai/Jobs/AiRunExecutionJob.php | grep "construct\|where"` retorna 2+ ocorrências
- [ ] `grep -rn "AiRunExecutionJob::dispatch" api/src/Domain/Ai/` mostra todos com 2 argumentos
- [ ] `cd api && composer gate:all` passa

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.1.7 — Adicionar tenant scope no ChatMessage::find inline de DispatchAutopilotRunJob

**T — Tarefa:** Adicionar `->where('tenant_id', $this->tenantId)` no `ChatMessage::query()->find($messageId)` em `DispatchAutopilotRunJob.php` (~linha 223) — job já tem `$this->tenantId` disponível no construtor.

**A — Arquivo:**
- `api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` (modificar — linha ~223)

**Referência:** `api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` — linha ~219 já usa `ChatTicket::query()->where('tenant_id', $this->tenantId)->find($ticketId)` como padrão

**Imports autorizados:** `Domain\Chat\Models\ChatMessage` (já importado) — proibido: importar do gateway

**C — Comportamento:**
- ANTES: bloco try (~linha 218-235):
  ```php
  $currentMessage = $messageId !== ''
      ? ChatMessage::query()->find($messageId)
      : null;
  ```
  `ChatTicket` na linha acima já usa `where('tenant_id', ...)`, mas `ChatMessage` não.
- DEPOIS:
  ```php
  $currentMessage = $messageId !== ''
      ? ChatMessage::query()->where('tenant_id', $this->tenantId)->find($messageId)
      : null;
  ```

**E — Evidência:**
- [ ] `grep -n "ChatMessage::query" api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` retorna linha com `where('tenant_id'`
- [ ] `cd api && composer gate:all` passa

**Status:** ✅ Concluída
**Dependências:** Nenhuma
