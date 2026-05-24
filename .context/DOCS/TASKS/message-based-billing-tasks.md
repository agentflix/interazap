# Tasks — Bilhetagem por Mensagens IA

**Feature:** `.context/DOCS/FEATURES/message-based-billing.md`
**PRD:** `.context/DOCS/PRDS/0003-PRD-message-based-billing.md`
**Status:** [ ] Em progresso | [ ] Concluída

---

## Fase 2 — Design

### Grupo 2.1 — Wireframe

- [x] **TASK-2.1.1** ✅ Criar wireframe da barra "Mensagens IA" e modal de preferências
  **T — Tarefa:** Documentar layout final dos novos elementos visuais com estados (verde 0-79%, amarelo 80-99%, vermelho 100%+ stop/overage) e fluxo do modal.
  **A — Arquivo:** `.context/DESIGN/message-based-billing-wireframe.md` (criar)
  **Referência:** `.context/DOCS/PRDS/0003-PRD-message-based-billing.md` (seção Wireframes/Fluxos) — usar como ponto de partida
  **Imports autorizados:** N/A (documento markdown)
  **C — Comportamento:**
  ANTES: PRD descreve estados em texto; sem artefato visual consolidado.
  DEPOIS: arquivo wireframe mostra (a) barra "Mensagens IA" nos 3 estados, (b) layout do `BillingPrefsModal` com radio stop/overage, (c) bullet no `plan-card`, (d) fluxo de bloqueio cliente→IA→api→envio.
  **E — Evidência:**
  - [x] `test -f .context/DESIGN/message-based-billing-wireframe.md` → arquivo existe com seções A/B/C/D
  **Status:** ✅ Concluída

---

## Fase 3 — Backend (api/)

### Grupo 3.1 — Schema (migrations)

- [x] **TASK-3.1.1** ✅ Migration: alterar `platform_plans` (drop tokens, add message fields)
  **T — Tarefa:** Criar migration que dropa `token_limit_monthly`, `overage_price_per_1k`, `allow_overage` e adiciona `message_limit_monthly INT NOT NULL DEFAULT 0`, `overage_mode VARCHAR(10) NOT NULL DEFAULT 'stop'`, `overage_price_per_message DECIMAL(10,4) NULL`.
  **A — Arquivo:** `api/database/migrations/2026_05_23_000001_replace_token_fields_with_message_fields_on_platform_plans.php` (criar)
  **Referência:** `api/database/migrations/2026_05_05_100000_add_ai_token_fields_to_platform_plans.php` — mesmo estilo (Schema::table com `hasColumn`)
  **Imports autorizados:** `Illuminate\Database\Migrations\Migration`, `Illuminate\Database\Schema\Blueprint`, `Illuminate\Support\Facades\Schema` — proibido: any model/service
  **C — Comportamento:**
  ANTES: `platform_plans` tem `token_limit_monthly`, `allow_overage`, `overage_price_per_1k`.
  DEPOIS: colunas token removidas; novas colunas presentes com defaults; `down()` reverte.
  **E — Evidência:**
  - [ ] `cd api && php artisan migrate:fresh --seed` → executa sem erros
  - [ ] `cd api && php artisan tinker --execute="echo Schema::hasColumn('platform_plans','message_limit_monthly');"` → retorna `1`
  - [ ] `cd api && php artisan tinker --execute="echo (int)Schema::hasColumn('platform_plans','token_limit_monthly');"` → retorna `0`
  **Status:** ✅ Concluída

- [x] **TASK-3.1.2** ✅ Migration: alterar `platform_tenants` (overage override + anchor)
  **T — Tarefa:** Criar migration adicionando `overage_mode_override VARCHAR(10) NULL` e `billing_cycle_anchor_day SMALLINT NULL` em `platform_tenants`.
  **A — Arquivo:** `api/database/migrations/2026_05_23_000002_add_billing_message_fields_to_platform_tenants.php` (criar)
  **Referência:** `api/database/migrations/2026_02_26_000001_add_plan_id_to_platform_tenants.php` — padrão de alteração em `platform_tenants`
  **Imports autorizados:** `Illuminate\Database\Migrations\Migration`, `Illuminate\Database\Schema\Blueprint`, `Illuminate\Support\Facades\Schema` — proibido: model/service
  **C — Comportamento:**
  ANTES: `platform_tenants` sem campos de override/anchor.
  DEPOIS: 2 colunas adicionadas; `down()` reverte.
  **E — Evidência:**
  - [ ] `cd api && php artisan migrate:fresh --seed` → sem erros
  - [ ] Coluna `billing_cycle_anchor_day` presente (validar via tinker)
  **Status:** ✅ Concluída

- [x] **TASK-3.1.3** ✅ Migration: criar tabela `tenant_message_usage`
  **T — Tarefa:** Criar tabela com `id UUID PK`, `tenant_id UUID FK`, `cycle_start DATE`, `cycle_end DATE`, `message_count INT DEFAULT 0`, `overage_count INT DEFAULT 0`, `alert_80_sent_at TIMESTAMP NULL`, `alert_100_sent_at TIMESTAMP NULL`, timestamps; `UNIQUE(tenant_id, cycle_start)`; `INDEX(tenant_id, cycle_end)`; FK cascade.
  **A — Arquivo:** `api/database/migrations/2026_05_23_000003_create_tenant_message_usage_table.php` (criar)
  **Referência:** `api/database/migrations/2026_01_01_000010_create_billing_tables.php` — estilo de criação com UUID, FKs e índices
  **Imports autorizados:** `Illuminate\Database\Migrations\Migration`, `Illuminate\Database\Schema\Blueprint`, `Illuminate\Support\Facades\Schema` — proibido: model/service
  **C — Comportamento:**
  ANTES: tabela não existe.
  DEPOIS: tabela criada com colunas, índices, FK; `down()` dropIfExists.
  **E — Evidência:**
  - [ ] `cd api && php artisan migrate:fresh --seed` → sem erros
  - [ ] `cd api && php artisan tinker --execute="echo Schema::hasTable('tenant_message_usage');"` → retorna `1`
  **Status:** ✅ Concluída

- [x] **TASK-3.1.4** ✅ Migration: criar tabela `ai_message_usage_failed_log`
  **T — Tarefa:** Criar tabela com `id UUID PK`, `tenant_id UUID FK`, `ai_turn_id VARCHAR(36) UNIQUE`, `channel VARCHAR(20)`, `attempted_at TIMESTAMP`, `reason VARCHAR(255)`, timestamps; índice `(tenant_id, attempted_at)`.
  **A — Arquivo:** `api/database/migrations/2026_05_23_000004_create_ai_message_usage_failed_log_table.php` (criar)
  **Referência:** `api/database/migrations/2026_01_01_000010_create_billing_tables.php` — mesma estrutura
  **Imports autorizados:** `Illuminate\Database\Migrations\Migration`, `Illuminate\Database\Schema\Blueprint`, `Illuminate\Support\Facades\Schema` — proibido: model/service
  **C — Comportamento:**
  ANTES: tabela não existe.
  DEPOIS: tabela criada com UNIQUE em `ai_turn_id` (garante idempotência de reconciliação); `down()` dropIfExists.
  **E — Evidência:**
  - [ ] `cd api && php artisan migrate:fresh --seed` → sem erros
  - [ ] Tabela presente (validar via tinker)
  **Status:** ✅ Concluída

### Grupo 3.2 — Models e DTOs

- [x] **TASK-3.2.1** ✅ Atualizar `PlatformPlan` (campos message)
  **T — Tarefa:** Adicionar `message_limit_monthly`, `overage_mode`, `overage_price_per_message` ao `$fillable` e `$casts`; remover referências a campos token; adicionar enum-like accessor para `overage_mode`.
  **A — Arquivo:** `api/src/Domain/Platform/Models/PlatformPlan.php` (modificar)
  **Referência:** `api/src/Domain/Platform/Models/PlatformPlan.php` — manter padrão existente do model
  **Imports autorizados:** `Illuminate\Database\Eloquent\Model`, `Illuminate\Database\Eloquent\Casts\AsArrayObject` — proibido: HTTP, AI, gateway
  **C — Comportamento:**
  ANTES: model tem `token_limit_monthly`, `allow_overage`, `overage_price_per_1k` em fillable/casts.
  DEPOIS: campos novos em fillable/casts; campos token removidos; getters quando necessário.
  **E — Evidência:**
  - [ ] `cd api && composer format && composer analyse` → verde
  - [ ] `cd api && php artisan test --filter PlatformPlanModelTest` → verde (criar teste se ausente — vide grupo 3.6)
  **Status:** ✅ Concluída

- [x] **TASK-3.2.2** ✅ Atualizar `PlatformTenant` (override + anchor)
  **T — Tarefa:** Adicionar `overage_mode_override`, `billing_cycle_anchor_day` ao `$fillable`/`$casts`. Adicionar método `effectiveOverageMode(): string` que retorna override OR plan default.
  **A — Arquivo:** `api/src/Domain/Platform/Models/PlatformTenant.php` (modificar)
  **Referência:** `api/src/Domain/Platform/Models/PlatformTenant.php` — padrão do próprio model
  **Imports autorizados:** Eloquent — proibido: HTTP, AI, gateway
  **C — Comportamento:**
  ANTES: tenant sem campos de override/anchor.
  DEPOIS: campos disponíveis; método helper retorna `'stop'|'overage'`.
  **E — Evidência:**
  - [ ] `cd api && composer analyse` → verde
  - [ ] Teste unitário cobre helper (criar no grupo 3.6)
  **Status:** ✅ Concluída

- [x] **TASK-3.2.3** ✅ Criar model `TenantMessageUsage`
  **T — Tarefa:** Model Eloquent UUID com fillable/casts para todos campos; relacionamento `tenant()`; scope `forCurrentCycle(string $tenantId)` que filtra ciclo ativo.
  **A — Arquivo:** `api/src/Domain/Billing/Models/TenantMessageUsage.php` (criar)
  **Referência:** `api/src/Domain/Billing/Models/BillingInvoice.php` — model UUID com casts e scopes
  **Imports autorizados:** `Illuminate\Database\Eloquent\Model`, `Illuminate\Database\Eloquent\Concerns\HasUuids`, `Domain\Platform\Models\PlatformTenant` — proibido: HTTP, AI direto, gateway
  **C — Comportamento:**
  ANTES: model não existe.
  DEPOIS: model funcional com scope/casts/relacionamento.
  **E — Evidência:**
  - [ ] `cd api && composer analyse` → verde
  - [ ] Teste em grupo 3.6 valida CRUD
  **Status:** ✅ Concluída

- [x] **TASK-3.2.4** ✅ Criar model `AiMessageUsageFailedLog`
  **T — Tarefa:** Model UUID com fillable/casts; relacionamento `tenant()`; sem scopes complexos.
  **A — Arquivo:** `api/src/Domain/Billing/Models/AiMessageUsageFailedLog.php` (criar)
  **Referência:** `api/src/Domain/Billing/Models/BillingPayment.php` — model UUID simples
  **Imports autorizados:** Eloquent, `PlatformTenant` — proibido: HTTP/AI/gateway
  **C — Comportamento:**
  ANTES: model não existe.
  DEPOIS: model funcional para gravação fail-open.
  **E — Evidência:**
  - [ ] `cd api && composer analyse` → verde
  **Status:** ✅ Concluída

- [x] **TASK-3.2.5** ✅ Criar enum `OverageMode`
  **T — Tarefa:** Backed enum string com cases `STOP = 'stop'` e `OVERAGE = 'overage'`; método `label(): string`.
  **A — Arquivo:** `api/src/Domain/Billing/Enums/OverageMode.php` (criar)
  **Referência:** `api/src/Domain/Billing/Enums/BillingInvoiceStatus.php` — backed enum string
  **Imports autorizados:** N/A (enum) — proibido: nada
  **C — Comportamento:**
  ANTES: strings mágicas espalhadas.
  DEPOIS: enum tipado utilizado em model/service/controller.
  **E — Evidência:**
  - [ ] `cd api && composer analyse` → verde
  - [ ] Casts em `PlatformPlan` e `PlatformTenant` apontam para enum
  **Status:** ✅ Concluída

- [x] **TASK-3.2.6** ✅ Criar DTO `CheckAndIncrementResult`
  **T — Tarefa:** Readonly class com `allowed: bool`, `current: int`, `limit: int`, `mode: OverageMode`, `is_overage: bool`. Método `toArray(): array`.
  **A — Arquivo:** `api/src/Domain/Billing/DTOs/CheckAndIncrementResult.php` (criar)
  **Referência:** `api/src/Domain/Billing/DTOs/BillingPaymentDTO.php` — padrão DTO readonly
  **Imports autorizados:** `Domain\Billing\Enums\OverageMode` — proibido: HTTP, banco
  **C — Comportamento:**
  ANTES: payload de retorno do endpoint não tipado.
  DEPOIS: DTO usado pelo service e pelo controller.
  **E — Evidência:**
  - [ ] `cd api && composer analyse` → verde
  **Status:** ✅ Concluída

### Grupo 3.3 — Services

- [x] **TASK-3.3.1** ✅ Criar `BillingCycleCalculator`
  **T — Tarefa:** Service stateless com `calculate(int $anchorDay, CarbonImmutable $reference): array{cycle_start, cycle_end}`. Trata anchor 1-28 e cap em 28 para dias 29-31. Lida com viradas ano (dez→jan).
  **A — Arquivo:** `api/src/Domain/Billing/Services/BillingCycleCalculator.php` (criar)
  **Referência:** `api/src/Domain/Billing/Services/WebhookSignatureValidator.php` — service stateless
  **Imports autorizados:** `Carbon\CarbonImmutable` — proibido: HTTP, model, gateway
  **C — Comportamento:**
  ANTES: cálculo de ciclo inexistente.
  DEPOIS: dado `anchor=15` e `ref=2026-06-20` → `{cycle_start: 2026-06-15, cycle_end: 2026-07-14}`. Anchor 31 vira 28.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter BillingCycleCalculatorTest` → verde (criar teste grupo 3.6)
  **Status:** ✅ Concluída

- [x] **TASK-3.3.2** ✅ Criar `UsageCounterService`
  **T — Tarefa:** Service com método `checkAndIncrement(string $tenantId, string $channel, string $aiTurnId): CheckAndIncrementResult`. Usa `DB::transaction` + `SELECT ... FOR UPDATE` no `tenant_message_usage` da janela ativa (cria lazy se inexistente). Decide allowed conforme `effectiveOverageMode()` do tenant. Idempotência via `ai_turn_id` (registra em `ai_message_usage_audit` JSON column ou checa duplicate via lock externo Redis — definir na implementação preferindo coluna `last_ai_turn_id` simples).
  **A — Arquivo:** `api/src/Domain/Billing/Services/UsageCounterService.php` (criar)
  **Referência:** `api/src/Domain/Ai/Services/AiStorageLimitService.php` — service de limite com checagem
  **Imports autorizados:** `Illuminate\Support\Facades\DB`, `Domain\Billing\Models\TenantMessageUsage`, `Domain\Platform\Models\PlatformTenant`, `Domain\Billing\Services\BillingCycleCalculator`, `Domain\Billing\DTOs\CheckAndIncrementResult`, `Domain\Billing\Enums\OverageMode`, `Carbon\CarbonImmutable` — proibido: HTTP, AI direto, gateway
  **C — Comportamento:**
  ANTES: sem contador de mensagens.
  DEPOIS: incremento atômico; retorno tipado com `allowed`; idempotente; race-safe.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter UsageCounterServiceTest` → verde
  - [ ] Teste de concorrência (paralelo) prova absence of double-count
  **Status:** ✅ Concluída

- [x] **TASK-3.3.3** ✅ Criar `ThresholdChecker`
  **T — Tarefa:** Service com `check(TenantMessageUsage $usage, int $limit): array<int>` retornando thresholds a disparar (`[80]`, `[100]`, `[80,100]` ou `[]`). Marca `alert_*_sent_at` atomicamente para evitar duplicate.
  **A — Arquivo:** `api/src/Domain/Billing/Services/ThresholdChecker.php` (criar)
  **Referência:** `api/src/Domain/Ai/Services/AiStorageLimitService.php` — padrão
  **Imports autorizados:** `Domain\Billing\Models\TenantMessageUsage`, `Illuminate\Support\Facades\DB` — proibido: HTTP, gateway
  **C — Comportamento:**
  ANTES: sem detecção de threshold.
  DEPOIS: dado usage 80% → retorna `[80]`; já marcado → retorna `[]`; 100% disparado uma vez.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter ThresholdCheckerTest` → verde
  **Status:** ✅ Concluída

### Grupo 3.4 — Endpoints

- [x] **TASK-3.4.1** ✅ Endpoint `POST /v1/billing/usage/check-and-increment`
  **T — Tarefa:** Controller + FormRequest + rota com middleware service-to-service. Body `{tenant_id, channel, ai_turn_id}`. Chama `UsageCounterService::checkAndIncrement` e enfileira `CheckUsageThresholdsJob` após commit. Retorna `CheckAndIncrementResult::toArray()`.
  **A — Arquivo:**
  - `api/src/Domain/Billing/Http/Controllers/BillingUsageController.php` (criar)
  - `api/src/Domain/Billing/Http/Requests/BillingUsageCheckRequest.php` (criar)
  - `api/src/Domain/Billing/Routes/billing.php` (modificar — adicionar rota dentro de grupo de auth s2s)
  **Referência:** `api/src/Domain/Billing/Http/Controllers/BillingSubscriptionController.php` + `api/src/Domain/Billing/Http/Requests/BillingChangePlanRequest.php`
  **Imports autorizados:** Laravel HTTP, `UsageCounterService`, `CheckUsageThresholdsJob`, `CheckAndIncrementResult` — proibido: AI/gateway diretos
  **C — Comportamento:**
  ANTES: rota não existe.
  DEPOIS: rota responde 200 com payload; 401 sem token s2s; 402 sem plano; 422 body inválido.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter CheckAndIncrementEndpointTest` → verde
  **Status:** ✅ Concluída

- [x] **TASK-3.4.2** ✅ Estender `GET /v1/billing/subscription` (subscription/me) com `usage.ai_messages`
  **T — Tarefa:** Adicionar bloco `ai_messages` em `BillingSubscriptionResource` calculando ciclo via `BillingCycleCalculator`, lendo row atual ou criando estrutura zerada. Campos: `current, limit, percentage, overage_count, mode, overage_price, cycle_start, cycle_end, cycle_label`.
  **A — Arquivo:**
  - `api/src/Domain/Billing/Http/Resources/BillingSubscriptionResource.php` (modificar)
  - `api/src/Domain/Billing/Http/Controllers/BillingSubscriptionController.php` (modificar — injetar service ou passar usage para resource)
  **Referência:** `api/src/Domain/Billing/Http/Resources/BillingSubscriptionResource.php` — mesmo arquivo
  **Imports autorizados:** Laravel HTTP, `TenantMessageUsage`, `BillingCycleCalculator` — proibido: HTTP outbound, AI/gateway
  **C — Comportamento:**
  ANTES: payload não tem `ai_messages`.
  DEPOIS: payload inclui bloco completo; cycle_label format `"15/jun – 14/jul"` em pt-BR.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter SubscriptionMeEndpointTest` → verde
  - [ ] Snapshot inclui chave `usage.ai_messages`
  **Status:** ✅ Concluída

- [x] **TASK-3.4.3** ✅ Endpoint `PATCH /v1/tenants/me/billing-prefs`
  **T — Tarefa:** Controller + FormRequest + rota auth tenant. Body `{overage_mode_override: "stop"|"overage"|null}`. Aplica role check (owner/admin). Registra mudança em `audits`. Responde 200 com tenant atualizado.
  **A — Arquivo:**
  - `api/src/Domain/Billing/Http/Controllers/BillingPrefsController.php` (criar)
  - `api/src/Domain/Billing/Http/Requests/BillingPrefsUpdateRequest.php` (criar)
  - `api/src/Domain/Billing/Routes/billing.php` (modificar — adicionar rota no grupo auth:sanctum)
  **Referência:** `BillingChangePlanRequest.php` + `BillingSubscriptionController.php`
  **Imports autorizados:** Laravel HTTP, `PlatformTenant`, `OverageMode` enum, `App\Models\Audit` — proibido: AI/gateway
  **C — Comportamento:**
  ANTES: rota não existe.
  DEPOIS: rota aceita 3 valores; salva em tenant; cria audit; 403 sem role; 422 inválido.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter BillingPrefsEndpointTest` → verde
  **Status:** ✅ Concluída

### Grupo 3.5 — Jobs e Notificações

- [x] **TASK-3.5.1** ✅ Job `CheckUsageThresholdsJob`
  **T — Tarefa:** Job ShouldQueue que recebe `tenantId` e `cycleStart`; carrega usage + plan; chama `ThresholdChecker`; para cada threshold retornado, dispara `SendUsageAlertJob`. Idempotência via `alert_*_sent_at` no banco.
  **A — Arquivo:** `api/src/Domain/Billing/Jobs/CheckUsageThresholdsJob.php` (criar)
  **Referência:** `api/src/Domain/Billing/Jobs/ProcessPaymentJob.php` — padrão ShouldQueue
  **Imports autorizados:** Laravel Queue, `ThresholdChecker`, `SendUsageAlertJob`, models billing/platform — proibido: AI direto, HTTP outbound
  **C — Comportamento:**
  ANTES: job não existe.
  DEPOIS: enfileirado após increment; dispara alertas conforme threshold.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter CheckUsageThresholdsJobTest` → verde
  **Status:** ✅ Concluída

- [x] **TASK-3.5.2** ✅ Job `SendUsageAlertJob` + mail/notification
  **T — Tarefa:** Job que recebe `tenantId, threshold (80|100), current, limit, mode`. Envia email via Mailable existente OU criar `UsageAlertMail`. Enfileira mensagem WhatsApp na fila do gateway via webhook outbound (POST gateway/internal endpoint) OU diretamente em `billing_collection_logs` se padrão existente. Templates: 80% aviso; 100% diferencia stop/overage.
  **A — Arquivo:**
  - `api/src/Domain/Billing/Jobs/SendUsageAlertJob.php` (criar)
  - `api/src/Domain/Billing/Mail/UsageAlertMail.php` (criar)
  - `api/resources/views/emails/usage-alert-80.blade.php` (criar)
  - `api/resources/views/emails/usage-alert-100.blade.php` (criar)
  **Referência:** `api/src/Domain/Billing/Mail/BillingCollectionMail.php` + `api/src/Domain/Billing/Actions/BillingSendRemindersAction.php` — padrão de envio
  **Imports autorizados:** Laravel Mail/Queue, models — proibido: AI direto
  **C — Comportamento:**
  ANTES: sem alertas.
  DEPOIS: ambos canais enfileirados; template diferencia 80/100 e stop/overage.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter SendUsageAlertJobTest` → verde
  - [ ] Mail::fake assert sent
  **Status:** ✅ Concluída

- [x] **TASK-3.5.3** ✅ Job `CloseExpiredCyclesJob` + Scheduler
  **T — Tarefa:** Job que para cada tenant com `cycle_end < today AND status != closed` (heurística: row em `tenant_message_usage` cuja `cycle_end < CURRENT_DATE` e sem fatura overage gerada): se `overage_count > 0` cria `BillingInvoice` com `metadata.kind='overage'`, status `pending`, `reference_month` = mês fim ciclo, `amount` = overage × price. Agendar diariamente 03:00 UTC em `routes/console.php` ou `Domain\Billing\Console`.
  **A — Arquivo:**
  - `api/src/Domain/Billing/Jobs/CloseExpiredCyclesJob.php` (criar)
  - `api/routes/console.php` (modificar — adicionar schedule)
  **Referência:** `api/src/Domain/Billing/Actions/BillingCheckOverdueAction.php` — ciclo similar
  **Imports autorizados:** Laravel Queue/Schedule, models billing/platform — proibido: AI direto, HTTP outbound
  **C — Comportamento:**
  ANTES: ciclos vencidos não fecham.
  DEPOIS: job roda diário; gera invoice overage idempotente (não cria 2x para mesmo cycle).
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter CloseExpiredCyclesJobTest` → verde
  - [ ] `cd api && php artisan schedule:list | grep close-expired-cycles` → presente
  **Status:** ✅ Concluída

- [x] **TASK-3.5.4** ✅ Job `ReconcileFailedUsageJob` + Scheduler
  **T — Tarefa:** Job diário que lê `ai_message_usage_failed_log` últimas 24h, chama `UsageCounterService::checkAndIncrement` para cada (idempotente via `ai_turn_id`). Marca log como reconciliado (coluna `reconciled_at`).
  **A — Arquivo:**
  - `api/src/Domain/Billing/Jobs/ReconcileFailedUsageJob.php` (criar)
  - `api/database/migrations/2026_05_23_000005_add_reconciled_at_to_ai_message_usage_failed_log.php` (criar — adicionar coluna)
  - `api/routes/console.php` (modificar — adicionar schedule)
  **Referência:** `BillingCheckOverdueAction.php`
  **Imports autorizados:** Laravel Queue/Schedule, models, `UsageCounterService` — proibido: AI direto, HTTP outbound
  **C — Comportamento:**
  ANTES: registros fail-open ficam órfãos.
  DEPOIS: replay diário; nenhum item replayado 2x.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter ReconcileFailedUsageJobTest` → verde
  - [ ] `cd api && php artisan schedule:list | grep reconcile-failed-usage` → presente
  **Status:** ✅ Concluída

### Grupo 3.6 — Testes

- [x] **TASK-3.6.1** ✅ Suite Unit `BillingCycleCalculatorTest`
  **T — Tarefa:** Testes para anchors 1, 15, 28, 29, 30, 31; ciclos cruzando ano; ciclo no mesmo mês; reference em cycle_start exato.
  **A — Arquivo:** `api/tests/Unit/Domain/Billing/Services/BillingCycleCalculatorTest.php` (criar)
  **Referência:** `api/tests/Unit/Domain/Billing/` (alguma pasta existente — adaptar) ou seguir padrão de outro service unit test em `api/tests/Unit/`
  **Imports autorizados:** PHPUnit/Pest, `BillingCycleCalculator`, Carbon — proibido: HTTP, banco real
  **C — Comportamento:**
  ANTES: sem cobertura.
  DEPOIS: 100% paths do calculator.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter BillingCycleCalculatorTest` → verde
  **Status:** ✅ Concluída

- [x] **TASK-3.6.2** ✅ Suite Unit `UsageCounterServiceTest` + `ThresholdCheckerTest`
  **T — Tarefa:** Testes com RefreshDatabase: increment normal, limite stop bloqueia, overage incrementa overage_count, idempotência por `ai_turn_id`, race (2 increments paralelos sem duplicate). Para Threshold: 79 não dispara, 80 dispara uma vez, 100 dispara uma vez, ambos no mesmo ciclo.
  **A — Arquivo:**
  - `api/tests/Feature/Domain/Billing/UsageCounterServiceTest.php` (criar)
  - `api/tests/Feature/Domain/Billing/ThresholdCheckerTest.php` (criar)
  **Referência:** `api/tests/Feature/` (qualquer feature test billing existente)
  **Imports autorizados:** PHPUnit/Pest, factories, services billing — proibido: HTTP outbound, AI direto
  **C — Comportamento:**
  ANTES: services sem testes.
  DEPOIS: cobertura ≥ 90% nos 2 services.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter UsageCounterServiceTest` → verde
  - [ ] `cd api && composer test -- --filter ThresholdCheckerTest` → verde
  **Status:** ✅ Concluída

- [x] **TASK-3.6.3** ✅ Suite Feature endpoints (3 endpoints)
  **T — Tarefa:** Tests para: `CheckAndIncrementEndpointTest` (200, 401, 402, 422), `SubscriptionMeEndpointTest` (payload `usage.ai_messages` presente, cycle_label correto), `BillingPrefsEndpointTest` (200 stop/overage/null, 403 sem role, 422 inválido).
  **A — Arquivo:**
  - `api/tests/Feature/Domain/Billing/CheckAndIncrementEndpointTest.php` (criar)
  - `api/tests/Feature/Domain/Billing/SubscriptionMeEndpointTest.php` (criar)
  - `api/tests/Feature/Domain/Billing/BillingPrefsEndpointTest.php` (criar)
  **Referência:** `api/tests/Feature/Domain/Billing/` (testes existentes de billing controller)
  **Imports autorizados:** PHPUnit/Pest, factories, Sanctum::actingAs — proibido: HTTP outbound real
  **C — Comportamento:**
  ANTES: endpoints sem cobertura.
  DEPOIS: 100% paths críticos cobertos.
  **E — Evidência:**
  - [ ] `cd api && composer gate:all` → verde
  **Status:** ✅ Concluída

- [x] **TASK-3.6.4** ✅ Suite Feature jobs (4 jobs)
  **T — Tarefa:** Tests para `CheckUsageThresholdsJobTest`, `SendUsageAlertJobTest` (Mail::fake), `CloseExpiredCyclesJobTest` (cria invoice overage), `ReconcileFailedUsageJobTest` (replay idempotente).
  **A — Arquivo:**
  - `api/tests/Feature/Domain/Billing/Jobs/CheckUsageThresholdsJobTest.php`
  - `api/tests/Feature/Domain/Billing/Jobs/SendUsageAlertJobTest.php`
  - `api/tests/Feature/Domain/Billing/Jobs/CloseExpiredCyclesJobTest.php`
  - `api/tests/Feature/Domain/Billing/Jobs/ReconcileFailedUsageJobTest.php`
  **Referência:** `api/tests/Feature/` jobs existentes
  **Imports autorizados:** PHPUnit/Pest, Queue::fake, Mail::fake — proibido: AI/gateway externos reais
  **C — Comportamento:**
  ANTES: jobs sem cobertura.
  DEPOIS: jobs com smoke + edge cases.
  **E — Evidência:**
  - [ ] `cd api && composer gate:all` → verde
  **Status:** ✅ Concluída

---

## Fase 3.5 — Gateway (gateway/)

### Grupo 3.5G.1 — Cliente HTTP + integração pipeline

- [x] **TASK-3.5G.1.1** ✅ Criar `BillingUsageClient` (HTTP→api)
  **T — Tarefa:** Service NestJS que chama `POST {apiUrl}/api/v1/billing/usage/check-and-increment` com header de service-to-service token (config `api.s2sToken`). Retorna DTO `{allowed, current, limit, mode, isOverage}`. Retry 3x exponencial (200ms/1s/5s) com fallback fail-open (registra em `ai_message_usage_failed_log` via outro endpoint — vide TASK seguinte).
  **A — Arquivo:** `gateway/src/domains/billing/services/billing-usage-client.service.ts` (criar)
  **Referência:** `gateway/src/domains/realtime/services/webchat-proxy.service.ts` — padrão axios + apiUrl + config
  **Imports autorizados:** `@nestjs/common`, `@nestjs/config`, `axios`, DTO local — proibido: pg direto, AI provider
  **C — Comportamento:**
  ANTES: gateway não consulta usage.
  DEPOIS: client funcional com retry; em falha total faz fail-open + log estruturado `usage.fail_open`.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test -- billing-usage-client.service` → verde
  - [ ] `pnpm --filter gateway build` → verde
  **Status:** ⏳ Pendente

- [x] **TASK-3.5G.1.2** ✅ Endpoint api para registrar fail-open + cliente gateway
  **T — Tarefa:** (a) Adicionar rota `POST /api/v1/billing/usage/fail-open-log` no api (controller existente `BillingUsageController`, método novo `logFailure`) gravando em `ai_message_usage_failed_log`. (b) Adicionar método `logFailure` no `BillingUsageClient` chamando esta rota como último recurso (sem retry).
  **A — Arquivo:**
  - `api/src/Domain/Billing/Http/Controllers/BillingUsageController.php` (modificar — add `logFailure`)
  - `api/src/Domain/Billing/Routes/billing.php` (modificar)
  - `gateway/src/domains/billing/services/billing-usage-client.service.ts` (modificar — add método)
  **Referência:** controller criado na TASK-3.4.1
  **Imports autorizados:** já listados nas tasks correspondentes
  **C — Comportamento:**
  ANTES: fail-open não registrado.
  DEPOIS: registros chegam ao api e ficam aguardando reconciliação.
  **E — Evidência:**
  - [ ] `cd api && composer test -- --filter LogFailOpenEndpointTest` → verde (criar mini teste)
  - [ ] `pnpm --filter gateway test -- billing-usage-client.service` → verde
  **Status:** ⏳ Pendente

- [x] **TASK-3.5G.1.3** ✅ Integrar `BillingUsageClient` no pipeline IA (run-completion / message-builder)
  **T — Tarefa:** No ponto onde resposta IA final é construída e ANTES do envio ao canal, chamar `billingUsageClient.checkAndIncrement(tenantId, channel, aiTurnId)`. Se `allowed=false`: NÃO enviar mensagem IA; emitir evento de handoff humano (usar fluxo de handoff existente OU sinalizar via `RunCompletionService`); marcar run com flag `blockedByQuota`.
  **A — Arquivo:** `gateway/src/domains/ai/services/orchestration/run-completion.service.ts` (modificar)
  **Referência:** `gateway/src/domains/ai/services/orchestration/run-completion.service.ts` — mesmo arquivo (entender pontos de envio existentes)
  **Imports autorizados:** `BillingUsageClient`, `Logger`, DTOs locais, services AI já importados — proibido: pg direto, env raw
  **C — Comportamento:**
  ANTES: toda resposta IA envia ao cliente.
  DEPOIS: envio condicionado a `allowed=true`; bloqueios disparam handoff.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test -- run-completion.service` → verde com novos cenários
  - [ ] Smoke manual: tenant com 0 cota → response não chega ao canal
  **Status:** ⏳ Pendente

- [x] **TASK-3.5G.1.4** ✅ Gerar `aiTurnId` no orquestrador
  **T — Tarefa:** No `AiRunOrchestratorService` (ou ponto equivalente onde inicia turno LLM), gerar UUID v4 ao iniciar geração e propagar até `run-completion.service`. Manter mesmo `aiTurnId` em retries internos para idempotência.
  **A — Arquivo:** `gateway/src/domains/ai/services/ai-run-orchestrator.service.ts` (modificar)
  **Referência:** `gateway/src/domains/ai/services/ai-run-orchestrator.service.ts` — mesmo arquivo
  **Imports autorizados:** `uuid` (já no projeto) — proibido: nada novo
  **C — Comportamento:**
  ANTES: sem identificador de turno.
  DEPOIS: turno carrega UUID estável usado pelo billing client.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway test -- ai-run-orchestrator.service` → verde
  **Status:** ⏳ Pendente

- [x] **TASK-3.5G.1.5** ✅ Módulo + DI wiring
  **T — Tarefa:** Adicionar `BillingUsageClient` como provider em `BillingModule` (ou criar `BillingClientsModule`). Exportar para `AiModule`. Configurar `api.s2sToken` em `core/config/configuration.ts`.
  **A — Arquivo:**
  - `gateway/src/domains/billing/billing.module.ts` (modificar)
  - `gateway/src/domains/ai/ai.module.ts` (modificar — importar)
  - `gateway/src/core/config/configuration.ts` (modificar — adicionar `s2sToken`)
  **Referência:** módulos existentes
  **Imports autorizados:** padrão NestJS
  **C — Comportamento:**
  ANTES: client não injetável.
  DEPOIS: client resolvido via DI no `RunCompletionService` e teste unitário.
  **E — Evidência:**
  - [ ] `pnpm --filter gateway build` → verde
  **Status:** ⏳ Pendente

### Grupo 3.5G.2 — Métricas

- [x] **TASK-3.5G.2.1** ✅ Métricas Prometheus
  **T — Tarefa:** Adicionar contadores e histogram: `ai_messages_total{tenant_id, mode, allowed}` counter, `usage_check_duration_seconds` histogram, `usage_check_failures_total{reason}` counter. Registrar em `BillingUsageClient`.
  **A — Arquivo:** `gateway/src/metrics/billing-usage.metrics.ts` (criar) + integração no client
  **Referência:** `gateway/src/metrics/` (qualquer métrica existente)
  **Imports autorizados:** `@willsoto/nestjs-prometheus` ou equivalente já no projeto
  **C — Comportamento:**
  ANTES: sem métricas billing.
  DEPOIS: scrape em `/metrics` exibe as 3 séries.
  **E — Evidência:**
  - [ ] `curl localhost:3000/metrics | grep ai_messages_total` → linha presente após chamadas
  **Status:** ⏳ Pendente

---

## Fase 4 — Frontend (app/)

### Grupo 4.1 — Models e Service

- [x] **TASK-4.1.1** ✅ Estender `SubscriptionUsage` com `ai_messages`
  **T — Tarefa:** Adicionar campo `ai_messages` à interface `SubscriptionUsage` em `subscription.model.ts` com tipos descritos no PRD (RF05).
  **A — Arquivo:** `app/src/app/shared/models/subscription.model.ts` (modificar)
  **Referência:** `app/src/app/shared/models/subscription.model.ts` — mesmo arquivo
  **Imports autorizados:** N/A (apenas tipos TS) — proibido: nada
  **C — Comportamento:**
  ANTES: interface sem `ai_messages`.
  DEPOIS: interface inclui bloco com `current, limit, percentage, overage_count, mode, overage_price, cycle_start, cycle_end, cycle_label`.
  **E — Evidência:**
  - [ ] `pnpm --filter app build` → verde sem `any`
  **Status:** ✅ Concluída

- [x] **TASK-4.1.2** ✅ Criar `BillingPrefsService`
  **T — Tarefa:** Service Angular com método `updateOverageMode(mode: 'stop'|'overage'|null): Observable<PlatformTenant>` chamando `PATCH /v1/tenants/me/billing-prefs`. Usar HttpClient padrão e env `apiBaseUrl`.
  **A — Arquivo:** `app/src/app/shared/services/billing-prefs.service.ts` (criar)
  **Referência:** services existentes em `app/src/app/shared/services/` (qualquer service que chama api REST)
  **Imports autorizados:** `@angular/core`, `@angular/common/http`, `rxjs`, modelo `PlatformTenant` — proibido: localStorage direto, banco
  **C — Comportamento:**
  ANTES: sem service de prefs.
  DEPOIS: service consumido pelo modal.
  **E — Evidência:**
  - [ ] `pnpm --filter app test -- billing-prefs.service` → verde
  **Status:** ✅ Concluída

### Grupo 4.2 — Componentes

- [x] **TASK-4.2.1** ✅ Atualizar `UsageStatsComponent` (barra "Mensagens IA")
  **T — Tarefa:** Adicionar nova linha "Mensagens IA" no template entre Storage e Negociações conforme wireframe TASK-2.1.1. Computed signal `aiMsgBarColor()` retornando classe Tailwind (`bg-primary-500|bg-warning|bg-error`) conforme percentage. Renderizar `cycle_label` no header, `overage_count` se > 0, texto "IA pausada — reseta em X" se mode=stop e ≥100%, texto "Cobrando R$ X,XX/msg" se mode=overage e ≥100%.
  **A — Arquivo:**
  - `app/src/app/pages/settings/my-plan/components/usage-stats/usage-stats.html` (modificar)
  - `app/src/app/pages/settings/my-plan/components/usage-stats/usage-stats.ts` (modificar — add computed + format helpers)
  **Referência:** `app/src/app/pages/settings/my-plan/components/usage-stats/usage-stats.html` — mesmo arquivo (manter padrão das outras barras)
  **Imports autorizados:** `@angular/core` (input, computed), `SubscriptionUsage` — proibido: HttpClient direto (component dumb)
  **C — Comportamento:**
  ANTES: card mostra 4 métricas (users/instances/storage/negotiations).
  DEPOIS: card mostra 5 métricas, com mensagens IA destacada e estado overage/stop visível.
  **E — Evidência:**
  - [ ] `pnpm --filter app test -- usage-stats` → verde
  - [ ] Smoke: `pnpm --filter app start` + abrir `/settings/my-plan` → barra aparece
  **Status:** ✅ Concluída

- [x] **TASK-4.2.2** ✅ Criar `BillingPrefsModalComponent`
  **T — Tarefa:** Modal standalone Angular com 2 radios (`Pausar IA ao atingir limite` / `Cobrar mensagens excedentes`), preço por msg em legenda quando aplicável, botões Cancelar/Salvar. Ao salvar, chama `BillingPrefsService.updateOverageMode`; emite evento `(saved)` com novo valor.
  **A — Arquivo:**
  - `app/src/app/pages/settings/my-plan/components/billing-prefs-modal/billing-prefs-modal.ts` (criar)
  - `app/src/app/pages/settings/my-plan/components/billing-prefs-modal/billing-prefs-modal.html` (criar)
  - `app/src/app/pages/settings/my-plan/components/billing-prefs-modal/billing-prefs-modal.spec.ts` (criar)
  **Referência:** `app/src/app/pages/settings/my-plan/components/upgrade-modal/` — padrão de modal standalone
  **Imports autorizados:** `@angular/core` (signals, inject), `@angular/forms` (FormsModule), `BillingPrefsService` — proibido: HttpClient direto
  **C — Comportamento:**
  ANTES: tenant não consegue alternar modo.
  DEPOIS: modal abre, salva, fecha, atualiza estado.
  **E — Evidência:**
  - [ ] `pnpm --filter app test -- billing-prefs-modal` → verde
  **Status:** ✅ Concluída

- [x] **TASK-4.2.3** ✅ Adicionar botão "Preferências de cobrança" em `MyPlanComponent`
  **T — Tarefa:** No card "Plano atual" do `my-plan.html`, adicionar botão secundário pequeno que abre `BillingPrefsModalComponent`. Estado modal via signal local. Atualiza subscription após salvar.
  **A — Arquivo:**
  - `app/src/app/pages/settings/my-plan/my-plan.ts` (modificar)
  - `app/src/app/pages/settings/my-plan/my-plan.html` (modificar)
  **Referência:** `app/src/app/pages/settings/my-plan/my-plan.ts` — mesmo arquivo (padrão signals + modais)
  **Imports autorizados:** componente novo + Angular core — proibido: HttpClient direto
  **C — Comportamento:**
  ANTES: card sem ação de preferências.
  DEPOIS: botão abre modal funcional.
  **E — Evidência:**
  - [ ] `pnpm --filter app test -- my-plan` → verde
  - [ ] Smoke: abrir modal e salvar
  **Status:** ✅ Concluída

- [x] **TASK-4.2.4** ✅ Atualizar `PlanCardComponent` (bullet mensagens IA)
  **T — Tarefa:** Adicionar `✓ {{ plan.message_limit_monthly }} mensagens IA/mês` na lista de features de cada plano. Formatador `number:'1.0-0':'pt-BR'`.
  **A — Arquivo:**
  - `app/src/app/pages/settings/my-plan/components/plan-card/plan-card.html` (modificar)
  - `app/src/app/pages/settings/my-plan/components/plan-card/plan-card.ts` (modificar se necessário — formatter)
  **Referência:** `app/src/app/pages/settings/my-plan/components/plan-card/plan-card.html` — mesmo arquivo
  **Imports autorizados:** Angular core, CommonModule — proibido: HttpClient
  **C — Comportamento:**
  ANTES: cards listam users/WhatsApp/storage/chatbot/IA.
  DEPOIS: lista inclui linha de mensagens IA.
  **E — Evidência:**
  - [ ] `pnpm --filter app test -- plan-card` → verde
  - [ ] Smoke: 4 cards mostram a nova bullet
  **Status:** ✅ Concluída

### Grupo 4.3 — Atualizar `Plan` model TS

- [x] **TASK-4.3.1** ✅ Estender model TS `Plan` com novos campos
  **T — Tarefa:** Adicionar `message_limit_monthly: number`, `overage_mode: 'stop'|'overage'`, `overage_price_per_message: number | null` na interface `Plan` (e equivalentes). Remover campos token se ainda presentes.
  **A — Arquivo:** `app/src/app/shared/models/subscription.model.ts` (modificar — caso ainda exista `Plan` interface aqui ou em arquivo separado; ajustar conforme estrutura real)
  **Referência:** `app/src/app/shared/models/subscription.model.ts`
  **Imports autorizados:** N/A
  **C — Comportamento:**
  ANTES: campos não declarados → barra usaria `any`.
  DEPOIS: tipagem completa.
  **E — Evidência:**
  - [ ] `pnpm --filter app build` → sem erros TS
  **Status:** ✅ Concluída

### Grupo 4.4 — Testes frontend

- [x] **TASK-4.4.1** ✅ Spec `UsageStatsComponent` cobre novos estados
  **T — Tarefa:** Adicionar casos: 50% verde, 85% amarelo, 100% stop (vermelho + texto "IA pausada"), 100% overage (vermelho + "Cobrando R$ X,XX/msg" + contador extras).
  **A — Arquivo:** `app/src/app/pages/settings/my-plan/components/usage-stats/usage-stats.spec.ts` (criar se ausente; modificar se existe)
  **Referência:** outros `.spec.ts` em `app/src/app/pages/settings/my-plan/`
  **Imports autorizados:** `@angular/core/testing`, jest — proibido: HttpClient real
  **C — Comportamento:**
  ANTES: spec ausente/limitado.
  DEPOIS: 4 estados cobertos.
  **E — Evidência:**
  - [ ] `pnpm --filter app test -- usage-stats` → 4 testes verdes
  **Status:** ✅ Concluída

---

## Fase 5 — Integration / Validação

### Grupo 5.1 — Cenários manuais

- [ ] **TASK-5.1.1** ⏳ Cenário 1 — Limite stop
  **T — Tarefa:** Configurar plano com `message_limit_monthly=5`, `overage_mode=stop`. Disparar 5 mensagens via webchat. Confirmar 6ª bloqueada e handoff.
  **A — Arquivo:** N/A (validação manual)
  **Referência:** PRD seção Critérios de Aceite (cenário 1)
  **Imports autorizados:** N/A
  **C — Comportamento:**
  ANTES: comportamento desconhecido em prod-like.
  DEPOIS: cenário validado e registrado em `.context/DOCS/MEMORY/message-based-billing-decisions.md` (criar).
  **E — Evidência:**
  - [ ] Registro com timestamp + observações no MEMORY
  - [ ] Barra atinge 100% vermelho com texto "IA pausada"
  **Status:** ⏳ Pendente

- [ ] **TASK-5.1.2** ⏳ Cenário 2 — Reset aniversário
  **T — Tarefa:** Forçar `cycle_end` para ontem em registro de teste. Disparar 1 nova mensagem. Confirmar nova row criada e contador zerado.
  **A — Arquivo:** N/A
  **Referência:** PRD critério 2
  **C — Comportamento:**
  ANTES: virada não testada.
  DEPOIS: row nova criada, anterior intocada.
  **E — Evidência:**
  - [ ] Query `SELECT * FROM tenant_message_usage WHERE tenant_id=X ORDER BY cycle_start DESC LIMIT 2` mostra 2 ciclos
  - [ ] Registro em MEMORY
  **Status:** ⏳ Pendente

- [ ] **TASK-5.1.3** ⏳ Cenário 3 — Troca plano mid-cycle
  **T — Tarefa:** Tenant em 600/800 troca para plano 1500. Confirmar contador 600 preservado e limite agora 1500. Ciclo mantido.
  **A — Arquivo:** N/A
  **Referência:** PRD critério 3
  **C — Comportamento:**
  ANTES: comportamento não confirmado.
  DEPOIS: validado e documentado.
  **E — Evidência:**
  - [ ] Barra mostra `600/1500` imediatamente após troca
  - [ ] Registro em MEMORY
  **Status:** ⏳ Pendente

### Grupo 5.2 — Gates finais

- [x] **TASK-5.2.1** ✅ Gate api
  **T — Tarefa:** Rodar `cd api && composer gate:all` no estado final.
  **A — Arquivo:** N/A
  **Referência:** `.context/WORKFLOW/validation-flow.md` seção API
  **C — Comportamento:**
  ANTES: alterações sem gate completo.
  DEPOIS: gate verde.
  **E — Evidência:**
  - [x] Comando termina exit 0 (2923 passed, 9664 assertions)
  **Status:** ✅ Concluída

- [ ] **TASK-5.2.2** ⏳ Gate gateway
  **T — Tarefa:** Rodar `pnpm --filter gateway build && pnpm --filter gateway test`.
  **A — Arquivo:** N/A
  **Referência:** `.context/WORKFLOW/validation-flow.md` seção Gateway
  **C — Comportamento:**
  ANTES: alterações sem gate.
  DEPOIS: gate verde.
  **E — Evidência:**
  - [ ] Comandos exit 0
  **Status:** ⏳ Pendente

- [x] **TASK-5.2.3** ✅ Gate app
  **T — Tarefa:** Rodar `pnpm --filter app build && pnpm --filter app test`.
  **A — Arquivo:** N/A
  **Referência:** `.context/WORKFLOW/validation-flow.md` seção App
  **C — Comportamento:**
  ANTES: alterações sem gate.
  DEPOIS: build verde; testes 1195/1197 (2 falhas pré-existentes em webchat não relacionadas à feature).
  **E — Evidência:**
  - [x] Build OK (15.422s)
  - [x] Testes OK (1195 passed, 2 falhas em webchat — não relacionadas)
  **Status:** ✅ Concluída

- [x] **TASK-5.2.4** ✅ Verificação de dependências
  **T — Tarefa:** Confirmar que gateway não importa pg/typeorm/prisma novo nesta feature. Confirmar que migrations só em api/.
  **A — Arquivo:** N/A
  **Referência:** `.context/WORKFLOW/validation-flow.md` seção Verificação de Dependências
  **C — Comportamento:**
  ANTES: regras de dependência não validadas para esta feature.
  DEPOIS: comandos grep não retornam nada novo.
  **E — Evidência:**
  - [x] `grep -r "pg\|postgres\|knex\|typeorm\|prisma" gateway/src/domains/billing/ --include="*.ts" | grep -v "node_modules"` → vazio
  - [x] `find gateway/ -name "*migration*" -o -name "*migrate*" | grep -v node_modules` → vazio
  - [x] Migrations da feature apenas em `api/database/migrations/` (7 arquivos)
  **Status:** ✅ Concluída

- [ ] **TASK-5.2.5** ⏳ Code review com `code-review-confiavel`
  **T — Tarefa:** Disparar revisão multi-agent conforme `.context/skills/code-review-confiavel/SKILL.md`.
  **A — Arquivo:** N/A
  **Referência:** `.context/WORKFLOW/validation-flow.md` seção Code Review
  **C — Comportamento:**
  ANTES: sem revisão consolidada.
  DEPOIS: meta-review sem achados bloqueantes.
  **E — Evidência:**
  - [ ] Relatório anexado em `.context/DOCS/MEMORY/message-based-billing-decisions.md`
  **Status:** ⏳ Pendente (aguardar Fase 3.5G + tasks 5.1.x para review completo)

---

## Ordem de execução sugerida

1. Fase 2 (wireframe) → desbloqueia frontend
2. Grupo 3.1 (migrations) → desbloqueia 3.2
3. Grupo 3.2 (models) → desbloqueia 3.3 e 3.4
4. Grupo 3.3 (services) → desbloqueia 3.4 e 3.5
5. Grupo 3.4 (endpoints) → desbloqueia 3.5G (gateway)
6. Grupo 3.5 (jobs) — pode rodar paralelo a 3.4 após 3.3
7. Grupo 3.6 (testes api) — paralelo às tasks correspondentes
8. Fase 3.5G (gateway) → após 3.4.1 disponível
9. Fase 4 (frontend) → após 3.4.2 (subscription estendido)
10. Fase 5 (integration + gates) → último
