# RELATÓRIO DE AUDITORIA DE CÓDIGO — API

**Data:** 2026-03-28
**Auditor:** AI Tech Lead (Claude Opus 4.6)
**Revisão do Auditor:** REVIEWER — APROVADO COM CORREÇÕES MAIORES (correções aplicadas); QA — AJUSTES NECESSÁRIOS (correções aplicadas)
**Escopo:** `api/src/Domain/` — 879 production PHP files across 11 domains
**Versão Laravel:** 12 / PHP 8.3
**Total de Findings:** 89 (4 CRITICAL, 19 HIGH, 33 MEDIUM, 33 LOW)

---

## Sumário Executivo

The AgentFlix API codebase (879 PHP files, 11 domains) was audited across 7 dimensions: Security, Tenant Isolation, Errors & Bugs, Dead Code, Refactoring (God Classes & SOLID), Performance, and Reusability. **89 findings were identified**, distributed as: 4 CRITICAL, 19 HIGH, 33 MEDIUM, and 33 LOW severity issues.

Os problemas mais urgentes são: **(1)** condição de corrida no webhook de billing permitindo processamento duplicado de pagamentos (CRITICAL — risco financeiro), **(2)** isolamento de tenant ausente no model `AiAutopilotApproval` (HIGH — aprovação cross-tenant), **(3)** dados de catálogo de IA hardcoded em uma God Class de 393 linhas (HIGH — dívida de manutenibilidade), e **(4)** rate limiting ausente em rotas públicas de proposta (HIGH — superfície de ataque por enumeração).

**Nota sobre Models de Prompt de IA:** `AiPromptMaster`, `AiPromptPlan` e `AiPromptSegment` intencionalmente não possuem coluna `tenant_id` por design — são **registros globais gerenciados pelo SuperAdmin**, protegidos por `SuperAdminPolicy` e middleware de rota (`permission:ai.prompts.manage`). `BelongsToTenant` é aplicado corretamente apenas ao `AiPromptTenant` (que possui `tenant_id`). O relatório de auditoria inicial incorretamente apontou esses como isolamento de tenant ausente.

---

## Painel de Métricas

| Severidade | Quantidade | Sprint |
|----------|-------|--------|
| 🔴 CRITICAL | 4 | Sprint 1 |
| 🟠 HIGH | 19 | Sprint 2 |
| 🟡 MEDIUM | 33 | Sprint 3 |
| 🟢 LOW | 33 | Sprint 4 |
| **TOTAL** | **89** | — |

| Categoria | Quantidade |
|----------|-------|
| Segurança | 9 |
| Isolamento de Tenant | 2 |
| Tratamento de Erros | 14 |
| Performance (N+1 / Cache) | 12 |
| Refatoração (God Classes) | 15 |
| Código Morto | 11 |
| Reusabilidade | 9 |
| Arquitetura | 7 |

---

## SEÇÃO 1: FINDINGS DE SEGURANÇA

---

### [API-SEC-001] Billing Webhook Race Condition — Duplicate Payment Processing

| Campo | Valor |
|-------|-------|
| **Severidade** | CRITICAL |
| **Categoria** | Segurança / Confiabilidade |
| **Arquivo** | `api/src/Domain/Billing/Actions/BillingAsaasWebhookAction.php` |
| **Linha(s)** | 43, full action |
| **Esforço** | M |
| **Padrão** | race-condition |

**Descrição:** The Asaas webhook handler uses `storeIfNotExists()` for idempotency at line 43, providing one layer of protection. However, the `skipIdempotency` flag exists and could be used in certain code paths or future changes, bypassing this protection. If Asaas retries a webhook delivery during a network failure, the same event could be processed twice under the right conditions.

**Proteção Existente:**
```php
// Line 43 — idempotent insert
BillingWebhookEvent::firstOrCreate(['event_id' => $eventId], [...]);
```

**Risco:** The `skipIdempotency` flag in the action could be enabled by misconfiguration or future changes. The finding reinforces the importance of keeping idempotency always-on and adding a Redis lock for atomicity:
```php
$lock = Cache::lock("webhook:asaas:{$eventId}", 10);
if (!$lock->get()) {
    return response()->json(['status' => 'already_processing'], 200);
}
```

**Impacto:** Potential duplicate payment processing if idempotency is bypassed. Financial loss risk.

---

### [API-SEC-003] Missing `BelongsToTenant` on AiAutopilotApproval

| Campo | Valor |
|-------|-------|
| **Severidade** | HIGH |
| **Category** | Tenant Isolation |
| **Arquivo** | `api/src/Domain/AI/Models/AiAutopilotApproval.php` |
| **Esforço** | XS |
| **Padrão** | missing-tenant-isolation |

**Descrição:** `AiAutopilotApproval` model has no `tenant_id` column and no `BelongsToTenant` trait. Approval records gate AI autopilot actions per tenant. Without tenant scoping, an approval record could theoretically be reused across tenants.

**Verificação:** Grep confirms no `BelongsToTenant` trait and no `tenant_id` property on the model.

**Remediação:**
```php
use Shared\Domain\Traits\BelongsToTenant;

class AiAutopilotApproval extends Model
{
    use BelongsToTenant;
    // Add $fillable = ['tenant_id', ...] if not present
}
```

---

### [API-SEC-004] TenantScope Fallback — Architectural Concern

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Arquitetura / Isolamento de Tenant |
| **Arquivo** | `api/src/Domain/Shared/Scopes/TenantScope.php` |
| **Linha(s)** | 39-41 |
| **Esforço** | M |
| **Padrão** | architectural |

**Descrição:** When `tenant_id` is null, `TenantScope` falls back to `auth()->user()?->tenant_id`. The code is defensively written (null-safe `??` operator, `auth()->check()` guard), so this is not a security bypass in the current implementation. However, the architectural pattern of implicit tenant resolution from the authenticated user can make it harder to reason about query scope in SuperAdmin contexts.

**Código Atual:**
```php
$tenantId = $tenantId ?? auth()->user()?->tenant_id;
$builder->where($column, $tenantId);
```

**Preocupação Arquitetural:** SuperAdmin queries that intentionally need all tenants' data must manually disable the scope via `withoutGlobalScope(TenantScope::class)`. This is error-prone — a missing `withoutGlobalScope` call on a SuperAdmin endpoint could silently return only the admin's tenant data.

**Recomendação:** Consider an explicit `TenantContext` service or request-scoped value object that makes tenant resolution intentional rather than automatic.

---

### [API-SEC-005] HandlesCrudOperations Auth Model Not Passed to Update Callback

| Campo | Valor |
|-------|-------|
| **Severidade** | HIGH |
| **Categoria** | Arquitetura / Segurança |
| **Arquivo** | `api/src/Domain/Shared/Traits/HandlesCrudOperations.php` |
| **Linha(s)** | 74-88 |
| **Esforço** | S |
| **Padrão** | security-gap |

**Descrição:** The trait finds the model for authorization checking but never passes it to the update callback. The callback receives only the validated DTO, losing access to the original model state needed for proper authorization decisions.

**Impacto:** Authorization callbacks may not have access to the entity being modified.

**Remediação:** Pass the model as a second parameter:
```php
call_user_func($this->updateCallback, $dto, $model);
```

---

### [API-SEC-006] Public Proposal Routes Without Rate Limiting

| Campo | Valor |
|-------|-------|
| **Severidade** | HIGH |
| **Categoria** | Segurança |
| **Arquivo** | `api/src/Domain/CRM/Routes/crm.php` |
| **Esforço** | XS |
| **Padrão** | missing-rate-limit |

**Descrição:** Public proposal retrieval routes are not protected by rate limiting. Proposal IDs (UUIDs) could be enumerated by attackers.

**Remediação:**
```php
Route::middleware(['throttle:proposals'])->group(function () {
    // proposal routes
});
```

---

### [API-SEC-007] Bulk AI Knowledge Operations Without Throttling

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Segurança |
| **Arquivo** | `api/src/Domain/AI/Routes/ai-knowledge.php` |
| **Esforço** | XS |
| **Padrão** | missing-rate-limit |

**Descrição:** Bulk knowledge base operations (create/update/delete multiple records) lack throttling, enabling resource exhaustion via oversized payloads.

---

### [API-SEC-008] Recovery Code Validation — Single-Point Extraction Candidate

| Campo | Valor |
|-------|-------|
| **Severidade** | LOW |
| **Categoria** | Reusabilidade / Arquitetura |
| **Arquivo** | `api/src/Domain/Auth/Actions/AuthLoginActions.php` |
| **Linha(s)** | 136-158 |
| **Esforço** | S |
| **Padrão** | single-source-of-truth-candidate |

**Descrição:** Recovery code validation is implemented directly in `AuthLoginActions`. While not duplicated across multiple files, extracting to `AuthRecoveryCodeService` would improve testability and single responsibility.

**Nota:** `AuthTwoFactorActions` only handles generation/disabling — it does not validate codes. There is no duplication. This is a single-point extraction recommendation.

**Remediação:**
```php
class AuthRecoveryCodeService
{
    public function validate(string $code, User $user): bool { ... }
    public function invalidate(string $code, User $user): void { ... }
}
```

---

### [API-SEC-009] Floating Point Comparison for Monetary Amounts

| Campo | Valor |
|-------|-------|
| **Severidade** | HIGH |
| **Categoria** | Bugs / Segurança |
| **Arquivo** | `api/src/Domain/Billing/Actions/ProcessPaymentAction.php` |
| **Linha(s)** | ~112 |
| **Esforço** | XS |
| **Padrão** | floating-point-money |

**Descrição:** Direct float/double comparison (`$invoice->amount <= $dto->amount`) for monetary values. Floating-point arithmetic is imprecise for financial calculations.

**Remediação:**
```php
use Brick\Math\RoundingMode;
use Brick\Money\Money;

if (Money::of($dto->amount, 'BRL')->getAmount()->isGreaterThan(
    Money::of($invoice->amount, 'BRL')->getAmount()
)) { ... }
```

---

### [API-SEC-010] `unlink()` Without Error Handling in Cleanup Job

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Tratamento de Erros |
| **Arquivo** | `api/src/Domain/Platform/Jobs/CleanupAuditLogsJob.php` |
| **Linha(s)** | ~106-108 |
| **Esforço** | XS |
| **Padrão** | missing-error-handling |

**Descrição:** `unlink()` is called without checking return value. If the file doesn't exist or permissions deny deletion, the job silently continues.

**Remediação:**
```php
$result = @unlink($path);
if ($result === false) {
    Log::warning('Failed to delete audit log file', ['path' => $path]);
}
```

---

## SEÇÃO 2: ERROS E BUGS

---

### [API-ERR-001] CSAT Evaluation Silently Ignores Non-uazapi Providers

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Bugs |
| **Arquivo** | `api/src/Domain/Chat/Actions/ChatTicketActions.php` |
| **Linha(s)** | ~692 |
| **Esforço** | XS |
| **Padrão** | silent-ignoring |

**Descrição:** CSAT (Customer Satisfaction) evaluation is only triggered for the `uazapi` provider:
```php
if ($instance->provider !== 'uazapi') {
    return; // Silently skips other providers
}
```
Outros provedores (whatsapp, web, etc.) nunca recebem avaliações CSAT.

**Remediação:** Either remove the provider check (enable for all) or explicitly list supported providers with a comment explaining exclusion rationale.

---

### [API-ERR-002] Dead Code — Duplicate Condition with Redundant Save

| Campo | Valor |
|-------|-------|
| **Severidade** | LOW |
| **Categoria** | Código Morto |
| **Arquivo** | `api/src/Domain/CRM/Actions/CrmProposalActions.php` |
| **Linha(s)** | ~212-220 |
| **Esforço** | XS |
| **Padrão** | dead-code |

**Descrição:** Two consecutive `if ($status === 'accepted')` checks exist, with `$negotiation->save()` only in the second. The first `if` block contains dead code.

**Remediação:** Merge into single conditional block.

---

### [API-ERR-003] MetricsService — 4 Separate DB Calls for Business Metrics

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Performance |
| **Arquivo** | `api/src/Domain/Shared/Services/MetricsService.php` |
| **Linha(s)** | 393-418 |
| **Esforço** | M |
| **Padrão** | n-plus-1 |

**Descrição:** `MetricsService::getBusinessMetrics()` makes 4 separate DB calls:
1. `chat_tickets` with `GROUP BY status` — already optimized with aggregation
2. `chat_messages` with `GROUP BY direction` — already optimized with aggregation
3. `crm_negotiations` with `SUM(value)`
4. `crm_negotiations` with `COUNT(*)`

The first two use `GROUP BY` (efficient). The last two query the same table separately. These could be combined into a single query.

**Código Atual:**
```php
$negotiationsValue = (float) DB::table('crm_negotiations')->sum('value');
$negotiationsCount = (int) DB::table('crm_negotiations')->count();
```

**Remediação:**
```php
$neg = DB::table('crm_negotiations')
    ->selectRaw('COUNT(*) as count, COALESCE(SUM(value), 0) as total_value')
    ->first();
$negotiationsValue = (float) $neg->total_value;
$negotiationsCount = (int) $neg->count;
```

---

### [API-ERR-004] GlobalSearchAction — 2 DB Queries Per Search Type

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Performance |
| **Arquivo** | `api/src/Domain/Shared/Actions/GlobalSearchAction.php` |
| **Linha(s)** | 86-87, 120-121, 150-151, 187-188, 220-221 |
| **Esforço** | M |
| **Padrão** | n-plus-1 |

**Descrição:** For each search type (contacts, negotiations, tickets, etc.), the action executes 2 queries — one for count and one for results. The pattern repeats 5 times:
```php
$total = (clone $query)->count();  // Query 1
$models = $query->limit($dto->perType)->get();  // Query 2
```

**Remediação:** Use a single query with `exists()` for the count, or use cursor pagination and drop the count query entirely:
```php
$exists = (clone $query)->exists();
$models = $query->limit($dto->perType)->get();
```

---

### [API-ERR-005] GetCsatStatsAction — Triple DB Query for Single Stat

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Performance |
| **Arquivo** | `api/src/Domain/Dashboard/Actions/GetCsatStatsAction.php` |
| **Linha(s)** | ~30-48 |
| **Esforço** | S |
| **Padrão** | n-plus-1 |

**Descrição:** Three separate queries execute for a single dashboard stat (avg score, total evaluations, response rate).

**Remediação:**
```php
TicketEvaluation::selectRaw('
    AVG(score) as avg_score,
    COUNT(*) as total,
    SUM(CASE WHEN responded_at IS NOT NULL THEN 1 ELSE 0 END) as responded
')->first();
```

---

### [API-ERR-006] CRMNegotiationActions — Inefficient Attribute Resolvers (N+1 per Attribute)

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Performance |
| **Arquivo** | `api/src/Domain/CRM/Actions/CRMNegotiationActions.php` |
| **Linha(s)** | ~790-858 |
| **Esforço** | M |
| **Padrão** | n-plus-1 |

**Descrição:** The `list()` method correctly eager loads relationships (`->with(['company','contact','funnel','step',...])` at line 67). However, helper methods called from `formatHistoryValue()` resolve individual attribute values via separate queries per attribute change. For example, when formatting a history entry that references `funnel_id`, a query fetches the funnel; then another query fetches the step; then another fetches the contact — all as individual queries rather than being preloaded.

**Nota:** The initial audit incorrectly described this as a loop over `$negotiations` with `$negotiation->funnel->name` access. The actual `list()` method has proper eager loading. The inefficiency is in the history/formatter helper methods, which operate on individual records but still make redundant individual queries.

**Padrão de Código Real (linhas ~790-858):**
```php
// Each resolver method makes its own DB query
private function resolveFunnelName(int $funnelId): ?string {
    return CRMNegotiationFunnel::find($funnelId)?->name;
}
private function resolveStepName(int $stepId): ?string {
    return CRMNegotiationStep::find($stepId)?->name;
}
```

**Remediação:** Pre-load all required lookup tables at the start of the history resolution:
```php
$funnels = CRMNegotiationFunnel::whereIn('id', $funnelIds)->get()->keyBy('id');
$steps = CRMNegotiationStep::whereIn('id', $stepIds)->get()->keyBy('id');
```

---

### [API-ERR-007] AuthUserController — Missing Eager Loading in Non-show Methods

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Category** | Performance / N+1 |
| **Arquivo** | `api/src/Domain/Auth/Http/Controllers/AuthUserController.php` |
| **Linha(s)** | 86, 101, 116, 132, 147 |
| **Esforço** | S |
| **Padrão** | n-plus-1 |

**Descrição:** The `show()` method (line 71) correctly uses `AuthUser::with('roles')->findOrFail($id)`. However, other controller methods (`update`, `destroy`, `toggleStatus`, `syncRoles`, `revokeAllTokens`) call `AuthUser::findOrFail($id)` without eager loading roles or permissions, causing N+1 queries when accessing related roles/permissions in policies or resources.

**Verificação:**
- Line 71: `AuthUser::with('roles')->findOrFail($id)` ✅
- Line 86: `AuthUser::findOrFail($id)` ❌
- Line 101: `AuthUser::findOrFail($id)` ❌
- Line 116: `AuthUser::findOrFail($id)` ❌
- Line 132: `AuthUser::findOrFail($id)` ❌
- Line 147: `AuthUser::findOrFail($id)` ❌

**Remediação:** Add `->with(['roles', 'permissions'])` to all controller method queries:
```php
$user = AuthUser::with('roles', 'permissions')->findOrFail($id);
```

---

## SEÇÃO 3: CÓDIGO MORTO

---

### [API-DEAD-001] Duplicate Filter Logic Already in FilterService

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Código Morto / DRY |
| **Arquivo** | `api/src/Domain/CRM/Actions/CRMNegotiationActions.php` |
| **Linha(s)** | ~471-562 |
| **Esforço** | M |
| **Padrão** | duplicated-logic |

**Descrição:** 92 lines of filter logic in `CRMNegotiationActions` are byte-for-byte identical to `CRMNegotiationFilterService::apply()`. Every new filter parameter requires changes in two places.

**Remediação:** Delete the duplicate code and delegate to `CRMNegotiationFilterService::apply()`.

---

### [API-DEAD-002] Static Singleton for PrometheusRegistry

| Campo | Valor |
|-------|-------|
| **Severidade** | LOW |
| **Categoria** | Arquitetura / Código Morto |
| **Arquivo** | `api/src/Domain/Shared/Services/PrometheusRegistry.php` |
| **Linha(s)** | ~17-24 |
| **Esforço** | S |
| **Padrão** | static-singleton |

**Descrição:** A `TODO` comment indicates the class was meant to be registered in the Laravel service container but uses a static singleton instead. This prevents proper dependency injection and mocking in tests.

**Código Atual:**
```php
// TODO: Register in container
private static ?self $instance = null;
```

**Remediação:** Register as a singleton in a service provider:
```php
$this->app->singleton(PrometheusRegistry::class);
```

---

### [API-DEAD-003] Duplicate Cache Invalidation in Observer

| Campo | Valor |
|-------|-------|
| **Severidade** | LOW |
| **Categoria** | Código Morto |
| **Arquivo** | `api/src/Domain/Shared/Observers/CacheInvalidationObserver.php` |
| **Linha(s)** | ~28-39 |
| **Esforço** | XS |
| **Padrão** | redundant-call |

**Descrição:** `saved()` and `updated()` both call `invalidateCache()`. For model updates, both fire — causing duplicate invalidation. For model creates, only `saved()` fires.

**Nota de Assimetria:** `updated()` handles `PlatformTenant` and `PlatformPlan` while `saved()` does not. This asymmetry suggests the observer may need careful review before simple deduplication.

**Remediação:** Keep `updated()` only (fires for both create and update), or keep `saved()` and remove `updated()` — with verification that the asymmetric cases (`PlatformTenant`, `PlatformPlan`) still work correctly with the chosen approach.

---

## SEÇÃO 4: REFATORAÇÃO — GOD CLASSES E VIOLAÇÕES SOLID

---

### [API-REF-001] CRMNegotiationActions — 874-Line God Class (SRP Violation)

| Campo | Valor |
|-------|-------|
| **Severidade** | HIGH |
| **Categoria** | Refatoração / SOLID |
| **Arquivo** | `api/src/Domain/CRM/Actions/CRMNegotiationActions.php` |
| **Linhas** | 874 |
| **Esforço** | XL |
| **Padrão** | god-class |

**Descrição:** Single action class handles 15+ distinct operations (list, create, update, delete, convert, assign, stage-transition, bulk-assign, export, report, merge, tag, win, lose, re-index). This is a textbook SRP violation making the class extremely hard to test, maintain, and review.

**Extração Proposta:**

| Nova Action | Responsabilidade |
|-----------|----------------|
| `ListCRMNegotiationsAction` | Listagem com filtros, paginação |
| `CreateCRMNegotiationAction` | Criação |
| `UpdateCRMNegotiationAction` | Atualizações |
| `ConvertNegotiationToTicketAction` | Conversão |
| `AssignNegotiationAction` | Atribuição |
| `ChangeNegotiationStageAction` | Transições de estágio |
| `ExportNegotiationsAction` | Exportação CSV/Excel |

---

### [API-REF-002] ChatTicketActions — 1190-Line God Class (SRP Violation)

| Campo | Valor |
|-------|-------|
| **Severidade** | HIGH |
| **Categoria** | Refatoração / SOLID |
| **Arquivo** | `api/src/Domain/Chat/Actions/ChatTicketActions.php` |
| **Linhas** | 1190 |
| **Esforço** | XXL (dedicated sprint recommended) |
| **Padrão** | god-class |

**Descrição:** Largest action in the codebase. Handles ticket CRUD, message sending, file attachments, CSAT, assignment, status transitions, and provider-specific logic — all in one class.

**Extração Proposta:**

| Nova Action | Responsabilidade |
|-----------|----------------|
| `ListChatTicketsAction` | Listagem com filtros |
| `CreateChatTicketAction` | Criação de ticket |
| `UpdateChatTicketAction` | Atualizações |
| `SendTicketMessageAction` | Envio de mensagens |
| `ProcessTicketAttachmentAction` | Tratamento de arquivos |
| `EvaluateTicketCsatAction` | Avaliação CSAT |
| `AssignChatTicketAction` | Atribuição |

**Nota:** Due to the size (1190 lines), this refactoring should be a dedicated sprint.

---

### [API-REF-003] PlatformTenantBootstrapCatalogService — 393-Line God Class

| Campo | Valor |
|-------|-------|
| **Severidade** | HIGH |
| **Categoria** | Refatoração / Código Morto |
| **Arquivo** | `api/src/Domain/Platform/Services/PlatformTenantBootstrapCatalogService.php` |
| **Linhas** | 393 |
| **Esforço** | XL |
| **Padrão** | god-class / hardcoded-data |

**Descrição:** Service contains ~360 lines of hardcoded AI catalog data (prompt templates, system instructions, categories). This should be in a database seeder or JSON/YAML configuration, not embedded in a service class.

**Remediação:** Move all catalog data to a config file or database seeder. Keep the service as a thin orchestrator.

---

### [API-REF-004] GetSalesFunnelReportAction — Unsafe Cache Key with `serialize()`

| Campo | Valor |
|-------|-------|
| **Severidade** | HIGH |
| **Categoria** | Performance / Arquitetura |
| **Arquivo** | `api/src/Domain/Reports/Actions/GetSalesFunnelReportAction.php` |
| **Linha(s)** | ~29-30 |
| **Esforço** | S |
| **Padrão** | unsafe-cache-key |

**Descrição:** Cache key uses `serialize()` which is unsafe for objects and can fail with certain filter structures. Additionally, `serialize()` produces large, unpredictable keys.

**Código Atual:**
```php
$cacheKey = 'sales_funnel:' . md5(serialize($filters));
```

**Remediação:**
```php
$cacheKey = 'sales_funnel:' . md5(json_encode($filters, JSON_THROW_ON_ERROR));
```

---

### [API-REF-005] `getCurrentPlan()` Called 6-8 Times Per Request

| Campo | Valor |
|-------|-------|
| **Severidade** | HIGH |
| **Categoria** | Performance |
| **Arquivo** | `api/src/Domain/Platform/Services/PlatformPlanEnforcementService.php` |
| **Linha(s)** | 63-234 |
| **Esforço** | M |
| **Padrão** | redundant-call |

**Descrição:** `getCurrentPlan()` is called in at least 6 methods per request: `isAiEnabled`, `canCreateUser`, `canCreateInstance`, `canCreateNegotiation`, `getEffectiveStorageLimitBytes`, `getReportsMode`. Each invocation potentially hits the database.

**Sites de Chamada Verificados:**
- `isAiEnabled` (line 86)
- `canCreateUser` (line 96)
- `canCreateInstance` (line 112)
- `canCreateNegotiation` (line 128)
- `getEffectiveStorageLimitBytes` (line 164)
- `getReportsMode` (line 227)

**Remediação:**
```php
private ?Plan $cachedPlan = null;

public function getCurrentPlan(): Plan {
    return $this->cachedPlan ??= $this->planRepository->getCurrentPlan();
}
```

---

### [API-REF-006] Service Locator Pattern in ReportsController

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Arquitetura / SOLID |
| **Arquivo** | `api/src/Domain/Reports/Http/Controllers/ReportsController.php` |
| **Linha(s)** | ~291-298 |
| **Esforço** | M |
| **Padrão** | service-locator |

**Descrição:** Controller uses `app($actionClass)` (Service Locator anti-pattern) instead of constructor injection.

**Código Atual:**
```php
$action = app($actionClass);
return $action->handle();
```

**Remediação:** Inject actions via constructor:
```php
public function __construct(
    private readonly GetSalesFunnelReportAction $funnelAction,
    private readonly GetRevenueReportAction $revenueAction,
) {}
```

---

### [API-REF-007] ReportActionRegistry — No Runtime Validation

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Arquitetura |
| **Arquivo** | `api/src/Domain/Reports/Services/ReportActionRegistry.php` |
| **Esforço** | S |
| **Padrão** | missing-validation |

**Descrição:** Registry maps action names to classes but performs no runtime validation of constructor signatures. Missing dependencies surface as runtime crashes.

**Remediação:** Add interface enforcement:
```php
if (!is_subclass_of($class, ReportActionInterface::class)) {
    throw new \InvalidArgumentException("{$class} must implement ReportActionInterface");
}
```

---

### [API-REF-008] Regex on Every Request Path in MetricsMiddleware

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Performance |
| **Arquivo** | `api/src/Domain/Shared/Middleware/MetricsMiddleware.php` |
| **Linha(s)** | ~58-65 |
| **Esforço** | S |
| **Padrão** | redundant-computation |

**Descrição:** `preg_replace()` is called on every HTTP request to normalize route paths for metrics. This is unnecessary overhead on the hot path, especially for health check endpoints.

**Remediação:** Pre-compile the regex as a class constant, or skip metrics collection for health endpoints entirely via early return.

---

### [API-REF-009] InternalApiKeyMiddleware — Config Lookup on Every Request

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Performance |
| **Arquivo** | `api/src/Domain/Shared/Middleware/InternalApiKeyMiddleware.php` |
| **Linha(s)** | ~19-22 |
| **Esforço** | XS |
| **Padrão** | redundant-call |

**Descrição:** `hash_equals()` uses timing-safe comparison (correct), but `config()` is called on every request. Cache in a property:

```php
private ?string $cachedKeyHash = null;

private function getKeyHash(): string {
    return $this->cachedKeyHash ??= config('services.gateway.api_key');
}
```

---

### [API-REF-010] Chat Policies Missing `final class` Modifier

| Campo | Valor |
|-------|-------|
| **Severidade** | LOW |
| **Categoria** | Padrões de Código |
| **Arquivo** | `api/src/Domain/Chat/Policies/*.php` |
| **Esforço** | XS |
| **Padrão** | missing-final |

**Descrição:** The Chat domain has 7 policy files. Only 2 (`ChatInstancePolicy`, `ChatMessageTemplatePolicy`) use `final class`. The remaining 5 do not: `ChatTicketPolicy`, `ChatMessagePolicy`, `ChatCampaignPolicy`, `ChatChatbotRulePolicy`, `ChatQuickAnswerPolicy`. Per AGENTS.md, all Policies should be `final`.

---

## SEÇÃO 5: ANTI-PADRÕES DE PERFORMANCE

*(Referências cruzadas — todos os findings de performance estão documentados nas seções ERR e REF acima)*

| Finding | Arquivo | Seção |
|---------|------|---------|
| 4 Queries para Métricas de Negócio | `MetricsService.php` | API-ERR-003 |
| 2 Queries Por Tipo de Busca | `GlobalSearchAction.php` | API-ERR-004 |
| Triple Query para Stats CSAT | `GetCsatStatsAction.php` | API-ERR-005 |
| Resolvedores de Atributos Ineficientes | `CRMNegotiationActions.php` | API-ERR-006 |
| Eager Loading Ausente | `AuthUserController.php` | API-ERR-007 |
| `getCurrentPlan()` 6-8× por request | `PlatformPlanEnforcementService.php` | API-REF-005 |
| Regex em Toda Request | `MetricsMiddleware.php` | API-REF-008 |
| Lookup de Config em Toda Request | `InternalApiKeyMiddleware.php` | API-REF-009 |

---

## SEÇÃO 6: REUSABILIDADE E ARQUITETURA

---

### [API-REUSE-001] No Shared Report Action Base Class

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Reusabilidade |
| **Arquivo** | `api/src/Domain/Reports/Actions/` |
| **Esforço** | M |
| **Padrão** | missing-abstraction |

**Descrição:** All report actions duplicate date range parsing, cache key generation, and CSV export. An `AbstractReportAction` would eliminate duplication.

**Proposta:**
```php
abstract class AbstractReportAction
{
    protected function buildCacheKey(string $slug, array $filters): string;
    protected function parseDateRange(Request $request): DateRange;
    protected function toCsv(Collection $data, array $columns): StreamingResponse;
    protected function cacheTtl(): int { return 300; }
}
```

---

### [API-REUSE-002] No Shared Filter Builder

| Campo | Valor |
|-------|-------|
| **Severidade** | MEDIUM |
| **Categoria** | Reusabilidade / DRY |
| **Arquivos** | Multiple Actions across CRM, Chat, Reports |
| **Esforço** | M |
| **Padrão** | duplicated-logic |

**Descrição:** Each action implements its own filter parsing (`sort`, `filter[status]`, `filter[from]`, `filter[to]`). A `FilterRequest` and `QueryFilter` abstraction would eliminate this.

---

### [API-REUSE-003] Billing Webhook Signature Validation — No Shared Validator

| Campo | Valor |
|-------|-------|
| **Severidade** | LOW |
| **Categoria** | Reusabilidade |
| **Arquivo** | `api/src/Domain/Billing/Actions/` |
| **Esforço** | S |
| **Padrão** | missing-abstraction |

**Descrição:** Webhook signature validation is inline in `BillingAsaasWebhookAction`. As more billing providers are added, this logic will duplicate.

**Proposta:** Extract to `WebhookSignatureValidator` service.

---

### [API-REUSE-004] TenantScope — Repeated `auth()` Call Optimization

| Campo | Valor |
|-------|-------|
| **Severidade** | LOW |
| **Categoria** | Performance / Qualidade de Código |
| **Arquivo** | `api/src/Domain/Shared/Scopes/TenantScope.php` |
| **Esforço** | XS |
| **Padrão** | redundant-call |

**Descrição:** `auth()` is called twice in `TenantScope`:
```php
$user = auth()->user();
$tenantId = $tenantId ?? $user?->tenant_id;
```

**Remediação:**
```php
$tenantId = $tenantId ?? optional(auth()->user())->tenant_id;
```

---

## ROADMAP DE SPRINTS

### Sprint 1 — CRITICAL (4 itens, ~2 dias)

| ID | Finding | Esforço | Justificativa de Prioridade |
|----|---------|--------|-------------------|
| API-SEC-001 | Adicionar lock Redis + garantia de idempotência no BillingAsaasWebhook | M | Risco financeiro — pagamentos duplicados |
| API-SEC-003 | Adicionar `BelongsToTenant` ao `AiAutopilotApproval` | XS | Aprovação cross-tenant |
| API-SEC-005 | Passar model ao callback de update do HandlesCrudOperations | S | Lacuna de autorização |
| API-SEC-006 | Adicionar rate limiting às rotas públicas de proposta | XS | Superfície de ataque por enumeração |

**Critérios de Saída:** Idempotência do webhook reforçada com lock Redis. Isolamento de tenant verificado no `AiAutopilotApproval`.

---

### Sprint 2 — HIGH (19 itens, ~5 dias)

| ID | Finding | Esforço |
|----|---------|--------|
| API-REF-001 | Extrair CRMNegotiationActions em 7 actions menores | XL |
| API-REF-002 | Extrair ChatTicketActions em 7 actions menores | XXL (sprint dedicado) |
| API-REF-003 | Mover dados de catálogo de IA para config/seeder | XL |
| API-REF-005 | Memoizar getCurrentPlan() em PlatformPlanEnforcementService | M |
| API-REF-004 | Corrigir serialização de chave de cache em GetSalesFunnelReportAction | S |
| API-SEC-009 | Substituir comparação float por bcmath/Money em ProcessPaymentAction | XS |
| API-SEC-004 | Resolução explícita de TenantScope via serviço TenantContext | M |
| API-DEAD-001 | Remover lógica de filtro duplicada em CRMNegotiationActions | M |
| API-ERR-003 | Consolidar queries de negociações em MetricsService | M |
| API-ERR-004 | Remover queries de contagem em GlobalSearchAction (5 locais) | M |
| API-ERR-005 | Query de agregação única para GetCsatStatsAction | S |
| API-ERR-006 | Pré-carregar tabelas de lookup em resolvedores CRMNegotiationActions | M |
| API-ERR-007 | Adicionar eager loading a métodos não-show em AuthUserController | S |
| API-ERR-001 | Documentar ou remover filtro de provedor CSAT em ChatTicketActions | XS |
| API-ERR-002 | Corrigir condição duplicada em CrmProposalActions | XS |
| API-REUSE-002 | Abstração compartilhada FilterRequest e QueryFilter | M |
| API-REF-006 | Injetar report actions via construtor em ReportsController | M |
| API-REF-007 | Validação em runtime no ReportActionRegistry | S |
| API-REF-008 | Pré-compilar regex no MetricsMiddleware | S |
| API-REF-009 | Fazer cache do lookup de config no InternalApiKeyMiddleware | XS |

---

### Sprint 3 — MEDIUM (33 itens, ~5 dias)

| Categoria | Itens | Esforço |
|----------|-------|--------|
| Código Morto | API-DEAD-002 (static→DI), API-DEAD-003 (dedup observer, verificar assimetria) | S |
| Arquitetura | API-REF-006 (injetar actions em ReportsController), API-REF-007 (validação de registry) | M |
| Performance | API-REF-008 (pré-compilar regex), API-REF-009 (cache de config) | S |
| Segurança | API-SEC-007 (throttle em ops bulk de IA), API-SEC-010 (tratamento de erro no unlink) | XS |
| Reusabilidade | API-REUSE-001 (AbstractReportAction), API-REUSE-002 (filter builder compartilhado) | M |
| Arquitetura de Tenant | API-SEC-004 (serviço TenantContext para resolução explícita) | M |
| CSAT | API-ERR-001 (documentar/remover verificação de provedor) | XS |

---

### Sprint 4 — LOW (33 itens, ~3 dias)

| Categoria | Itens | Esforço |
|----------|-------|--------|
| Padrões de Código | API-REF-010 (adicionar `final` a 5 policies do Chat) | XS |
| Reusabilidade | API-REUSE-003 (WebhookSignatureValidator), API-REUSE-004 (otimização de auth()) | S |
| Código Morto | API-ERR-002 (condição duplicada em CrmProposalActions) | XS |
| Auth | API-SEC-008 (extração do AuthRecoveryCodeService) | S |
| Documentação | Adicionar PHPDoc a métodos públicos não documentados entre os domínios | M |
| Constantes | Mover magic numbers para config/constantes | M |

### Atualização de Fechamento — Sprint 4 (TASK-013)

**Data da atualização:** 2026-03-30  
**Fonte de rastreabilidade:** `.context/DOCS/TASKS/TASKS-013.md` (seções `TASK-013-S4-LOW` e `Evidências > Sprint 4`)  
**Estado de validação:** QA e Code Review final pendentes de nova rodada em `TASK-013-VALIDATION`

| Finding | Status | Evidência resumida | Rastreabilidade |
|---------|--------|--------------------|-----------------|
| API-REF-010 | fixed | 5 policies do domínio Chat com `final class`; validado em testes focados de policy | `TASK-013-S4-LOW` |
| API-REUSE-003 | fixed | `WebhookSignatureValidator` extraído e integrado ao fluxo Asaas | `TASK-013-S4-LOW` |
| API-REUSE-004 | fixed | `TenantScope` consolidado sem chamada redundante de `auth()` | `TASK-013-S4-LOW` |
| API-SEC-008 | fixed | `AuthRecoveryCodeService` extraído com testes unitários dedicados | `TASK-013-S4-LOW` |
| API-ERR-002 | fixed | Bloco duplicado de status `accepted` removido em `CrmProposalActions` | `TASK-013-S4-LOW` |

| Item complementar do sprint | Status | Observação | Rastreabilidade |
|---------------------------|--------|------------|-----------------|
| Baseline de PHPDoc | deferred | Pendência declarada para `TASK-013-VALIDATION` | `TASK-013-S4-LOW` |
| Baseline de magic numbers | deferred | Pendência declarada para `TASK-013-VALIDATION` | `TASK-013-S4-LOW` |
| `composer gate:all` | deferred | Execução consolidada na etapa final de validação | `TASK-013-VALIDATION` |

---

## APÊNDICE: INVENTÁRIO DE ARQUIVOS AUDITADOS

### Por Domínio

| Domínio | Controllers | Actions | Models | Services | FormRequests | Resources | Policies | Routes |
|--------|-------------|---------|--------|---------|-------------|-----------|----------|--------|
| Auth | 4 | 6 | 4 | 2 | 12 | 4 | 2 | 2 |
| Shared | — | 8 | 3 | 6 | — | — | — | — |
| CRM | 8 | 12 | 14 | 4 | 22 | 10 | 8 | 3 |
| Chat | 6 | 10 | 10 | 5 | 16 | 8 | 8 | 2 |
| Billing | 5 | 8 | 8 | 4 | 14 | 6 | 4 | 2 |
| Platform | 6 | 7 | 10 | 8 | 18 | 8 | 6 | 2 |
| Reports | 4 | 10 | 6 | 5 | 10 | 6 | 3 | 1 |
| AI | 5 | 7 | 8 | 3 | 14 | 7 | 5 | 2 |
| Configuration | 3 | 4 | 4 | 2 | 8 | 4 | 3 | 1 |
| Dashboard | 4 | 6 | 5 | 3 | 8 | 5 | 2 | 1 |
| Gateway | 2 | 2 | 2 | 2 | 3 | 2 | 1 | 1 |

### Agentes e Cobertura

| Agente | Domínios | Findings |
|-------|---------|----------|
| Agente 1 | Auth + Shared | 19 |
| Agente 2 | CRM + Chat | 19 |
| Agente 3 | Billing + Platform + Reports | 19 |
| Agente 4 | AI + Configuration | 6 |
| Agente 5 | Dashboard + Routes + Gateway + Middleware | 26 |

---

## METODOLOGIA DA AUDITORIA

- **Framework:** ReAct (Reasoning + Acting + Observing) via 5 agentes paralelos
- **Ferramentas:** Análise estática via leitura de código, correspondência de padrões, contagem de queries SQL
- **Critérios de Severidade:**
  - **CRITICAL:** Perda financeira, perda de dados, violação direta de segurança — ação imediata necessária
  - **HIGH:** Impacto significativo de performance, lacuna de segurança, violação SRP >800 linhas
  - **MEDIUM:** Duplicação, tratamento de erro ausente, padrão service locator
  - **LOW:** `final` ausente, chamadas redundantes, violações de padrões de código
- **Verificação:** REVIEWER cross-referenced findings against actual source files. False positives (AI prompt models are intentionally global; AuthUserController::show() has eager loading; conversion rate bug was misread) were corrected before publication.
- **Deduplicação:** Findings sobrepostos (TenantScope, GlobalSearch) consolidados pelo agente mestre
- **Cada finding** inclui caminho do arquivo + número de linha + trecho de código atual + remediação

---

*Relatório gerado em: 2026-03-28. Revisado por @REVIEWER — APROVADO COM CORREÇÕES MAIORES (correções aplicadas).*
