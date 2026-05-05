# Tasks: Migração de Token Limits para PlatformPlans + Cobrança de Overage

> Decomposição T.A.C.E hierárquica (TASK-X.Y.Z) para FEAT-003.
> Feature doc: `../FEATURES/FEAT-003-token-limits-platform-plans.md`

---

## Estrutura Hierárquica

| Nível | Significado |
|-------|-------------|
| X | Fase: 1=Planning, 3=Backend, 5=Frontend, 6=Integration |
| Y | Grupo dentro da fase |
| Z | Etapa de codificação |

---

## Dependências de Execução

```
TASK-3.1.x (migration platform_plans ADD)
    └── TASK-3.2.x (migration ai_prompt_plans REMOVE)    [sequencial]
    └── TASK-3.3.x (CalculateAiOverageAction)            [sequencial — usa $plan->token_limit_monthly]

TASK-5.1.x (frontend platform plans)                    [paralelo com TASK-3.1.x]
TASK-5.2.x (frontend prompt plans — limpar)
    └── TASK-3.2.x (espera API não retornar mais os campos)
```

---

## FASE 1: PLANNING ✅

### 1.1 — Documentação

- [x] **TASK-1.1.1** ✅: Feature doc criada

  **T — Tarefa:** Criar feature doc FEAT-003
  **A — Arquivo:** `.context/DOCS/FEATURES/FEAT-003-token-limits-platform-plans.md`
  **C — Comportamento:**
  - ANTES: feature não documentada
  - DEPOIS: doc com escopo, CA, deps, riscos e tasks referenciados
  **E — Evidência:**
  - [x] Doc criado em 2026-05-05
  **Status:** Concluída

---

## FASE 3: BACKEND (api/)

### 3.1 — Database / Migrations (DBA + BACKEND)

- [x] **TASK-3.1.1** ✅: Migration — adicionar campos de token a platform_plans

  **T — Tarefa:** Criar migration que adiciona `token_limit_monthly` (int nullable), `allow_overage` (boolean default false), `overage_price_per_1k` (decimal 10,2 nullable) à tabela `platform_plans`. Incluir data migration que copia valores de `ai_prompt_plans` via UPDATE com JOIN por `plan_id`.
  **A — Arquivo:** `api/database/migrations/2026_05_05_100000_add_ai_token_fields_to_platform_plans.php`
  **C — Comportamento:**
  - ANTES: `platform_plans` sem campos de token; valores existem em `ai_prompt_plans`
  - DEPOIS: `platform_plans` com 3 novos campos preenchidos com dados migrados
  **E — Evidência:**
  - [x] `php artisan migrate` executa sem erro
  - [x] `down()` implementado para rollback seguro
  - [x] `SELECT token_limit_monthly FROM platform_plans` retorna valores copiados
  **Status:** Concluída

  > **Data migration obrigatória no `up()`:**
  > ```sql
  > UPDATE platform_plans pp
  > SET token_limit_monthly = ap.token_limit_monthly,
  >     allow_overage = ap.allow_overage,
  >     overage_price_per_1k = ap.overage_price_per_1k
  > FROM ai_prompt_plans ap
  > WHERE ap.plan_id = pp.id
  > ```

---

### 3.2 — Domain Layer — PlatformPlan (BACKEND)

- [x] **TASK-3.2.1** ✅: Atualizar PlatformPlan Model

  **T — Tarefa:** Adicionar `token_limit_monthly`, `allow_overage`, `overage_price_per_1k` ao `$fillable`, `$casts` e phpDoc da Model.
  **A — Arquivo:** `api/src/Domain/Platform/Models/PlatformPlan.php`
  **C — Comportamento:**
  - ANTES: Model sem os 3 campos
  - DEPOIS: `PlatformPlan::first()->token_limit_monthly` retorna int/null; `allow_overage` retorna bool; `overage_price_per_1k` retorna float/null
  **E — Evidência:**
  - [x] PHPStan L6 passa sem erros nos arquivos da FEAT-003
  **Status:** Concluída

- [x] **TASK-3.2.2** ✅: Atualizar PlatformPlanFactory

  **T — Tarefa:** Adicionar os 3 campos com valores fake realistas no `definition()` da factory.
  **A — Arquivo:** `api/database/factories/PlatformPlanFactory.php`
  **C — Comportamento:**
  - ANTES: Factory não gera os campos de token
  - DEPOIS: `PlatformPlan::factory()->make()` inclui os 3 campos
  **E — Evidência:**
  - [x] Factory não quebra testes existentes
  - [x] PHPStan passa nos arquivos da FEAT-003
  **Status:** Concluída

- [x] **TASK-3.2.3** ✅: Atualizar PlatformPlanSeeder

  **T — Tarefa:** Adicionar valores por plano ao seeder: Starter (sem limite ou 50k tokens, sem overage), Professional (100k tokens, overage R$ 2,00/1k), Business (500k tokens, overage R$ 1,50/1k).
  **A — Arquivo:** `api/database/seeders/PlatformPlanSeeder.php`
  **C — Comportamento:**
  - ANTES: Seeder não popula campos de token
  - DEPOIS: 3 planos com valores realistas de token limit e preço de overage
  **E — Evidência:**
  - [x] Seeder atualizado com valores por plano
  - [x] Planos têm valores distintos de token limit
  **Status:** Concluída

---

### 3.3 — Limpar AiPromptPlan (BACKEND)

> ⚠️ Executar somente APÓS TASK-3.1.1 estar aplicada (data migration já feita)

- [x] **TASK-3.3.1** ✅: Migration — remover campos de ai_prompt_plans

  **T — Tarefa:** Criar migration que remove `token_limit_monthly`, `allow_overage`, `overage_price_per_1k` da tabela `ai_prompt_plans`.
  **A — Arquivo:** `api/database/migrations/2026_05_05_110000_remove_token_fields_from_ai_prompt_plans.php`
  **C — Comportamento:**
  - ANTES: `ai_prompt_plans` tem os 3 campos
  - DEPOIS: `ai_prompt_plans` não tem mais os 3 campos
  **E — Evidência:**
  - [x] `php artisan migrate` executa sem erro
  - [x] `DESCRIBE ai_prompt_plans` não lista mais os 3 campos
  **Status:** Concluída

- [x] **TASK-3.3.2** ✅: Atualizar AiPromptPlan Model

  **T — Tarefa:** Remover `token_limit_monthly`, `allow_overage`, `overage_price_per_1k` de `$fillable`, `$casts` e phpDoc. Remover método `allowsOverage()` (agora é `$tenant->plan->allow_overage`).
  **A — Arquivo:** `api/src/Domain/Ai/Models/AiPromptPlan.php`
  **C — Comportamento:**
  - ANTES: Model com 3 campos e método `allowsOverage()`
  - DEPOIS: Model sem esses campos
  **E — Evidência:**
  - [x] PHPStan L6 passa nos arquivos da FEAT-003
  **Status:** Concluída

- [x] **TASK-3.3.3** ✅: Atualizar PlanPromptDTO

  **T — Tarefa:** Remover `tokenLimitMonthly`, `allowOverage`, `overagePricePer1k` do DTO e dos métodos `fromRequest()` e `toArray()`.
  **A — Arquivo:** `api/src/Domain/Ai/DTOs/PlanPromptDTO.php`
  **C — Comportamento:**
  - ANTES: DTO com 3 campos de token
  - DEPOIS: DTO sem esses campos
  **E — Evidência:**
  - [x] PHPStan passa nos arquivos da FEAT-003
  **Status:** Concluída

- [x] **TASK-3.3.4** ✅: Atualizar PlanPromptResource

  **T — Tarefa:** Remover `token_limit_monthly`, `allow_overage`, `overage_price_per_1k` da serialização do Resource.
  **A — Arquivo:** `api/src/Domain/Ai/Http/Resources/PlanPromptResource.php`
  **C — Comportamento:**
  - ANTES: API retorna os 3 campos no JSON de prompt plans
  - DEPOIS: `GET /api/platform/prompt-plans` não retorna mais esses campos
  **E — Evidência:**
  - [x] Response JSON não contém os campos
  - [x] PHPStan passa nos arquivos da FEAT-003
  **Status:** Concluída

- [x] **TASK-3.3.5** ✅: Atualizar UpdatePlanPromptRequest

  **T — Tarefa:** Remover validações dos 3 campos de token do FormRequest.
  **A — Arquivo:** `api/src/Domain/Ai/Http/Requests/UpdatePlanPromptRequest.php`
  **C — Comportamento:**
  - ANTES: Request valida `token_limit_monthly`, `allow_overage`, `overage_price_per_1k`
  - DEPOIS: Request não aceita mais esses campos
  **E — Evidência:**
  - [x] PHPStan passa nos arquivos da FEAT-003
  **Status:** Concluída

- [x] **TASK-3.3.6** ✅: Atualizar AiPromptPlanFactory e AiPromptPlanSeeder

  **T — Tarefa:** Remover os 3 campos de `definition()` (factory) e `updateOrCreate()` (seeder).
  **A — Arquivo:** `api/database/factories/AiPromptPlanFactory.php`
  **A — Arquivo:** `api/database/seeders/AiPromptPlanSeeder.php`
  **C — Comportamento:**
  - ANTES: Factory e seeder incluem campos de token
  - DEPOIS: Não incluem mais
  **E — Evidência:**
  - [x] Factory e seeder executam sem erro
  - [x] PHPStan passa nos arquivos da FEAT-003
  **Status:** Concluída

---

### 3.4 — Application Layer — Overage e Faturamento Mensal (BACKEND)

- [x] **TASK-3.4.1** ✅: Criar CalculateAiOverageAction

  **T — Tarefa:** Action `final class` que recebe `tenantId` (string) e `referenceMonth` (string YYYY-MM), consulta `ai_usage_logs` pelo período, compara consumo com `$tenant->plan->token_limit_monthly`, retorna array com `overage_tokens`, `overage_amount` (BRL) e `overage_applied`.
  **A — Arquivo:** `api/src/Domain/Billing/Actions/CalculateAiOverageAction.php`
  **C — Comportamento:**
  - ANTES: não existe cálculo de overage
  - DEPOIS: retorna `['overage_applied' => bool, 'overage_tokens' => int, 'overage_amount' => float]`

  > **Lógica central:**
  > ```php
  > $totalTokens = AiUsageLog::forTenant($tenantId)
  >     ->whereBetween('created_at', [$start, $end])
  >     ->sum(DB::raw('input_tokens + output_tokens'));
  >
  > $limit = $plan->token_limit_monthly ?? PHP_INT_MAX;
  > $excess = max(0, $totalTokens - $limit);
  > $overageAmount = $plan->allow_overage
  >     ? round(($excess / 1000) * ($plan->overage_price_per_1k ?? 0), 2)
  >     : 0.0;
  > ```

  **E — Evidência:**
  - [x] Teste Pest: 150k consumidos, limite 100k, R$2,00/1k → `overage_amount = 100.00`, `overage_tokens = 50000`
  - [x] Teste: tenant sem excedente → `overage_applied = false`, `overage_amount = 0.00`
  - [x] Teste: `allow_overage = false` com excedente → `overage_amount = 0.00`
  - [x] PHPStan L6 passa nos arquivos da FEAT-003
  **Status:** Concluída

- [x] **TASK-3.4.2** ✅: Criar BillingGenerateMonthlyInvoicesCommand

  **T — Tarefa:** Artisan command `billing:generate-monthly-invoices` que itera todos os tenants ativos com plano, calcula `price_monthly + overage` via `CalculateAiOverageAction`, e cria fatura via `BillingInvoiceActions::create()`. Suporta `--dry-run`. Idempotente por `reference_month`.
  **A — Arquivo:** `api/src/Domain/Billing/Console/Commands/BillingGenerateMonthlyInvoicesCommand.php`
  **C — Comportamento:**
  - ANTES: não existe geração automática de faturas
  - DEPOIS: comando cria faturas para todos tenants ativos; re-execução no mesmo mês não duplica

  > **Metadata da fatura:**
  > ```php
  > 'metadata' => [
  >     'base_price' => $plan->price_monthly,
  >     'overage_applied' => $overage['overage_applied'],
  >     'overage_tokens' => $overage['overage_tokens'],
  >     'overage_amount' => $overage['overage_amount'],
  > ]
  > ```

  **E — Evidência:**
  - [x] `php artisan billing:generate-monthly-invoices --dry-run` lista tenants sem criar faturas
  - [x] Fatura criada com `amount = price_monthly + overage_amount`
  - [x] Segunda execução no mesmo mês: zero faturas criadas (idempotência)
  - [x] Tenant com `plan_id = null` é ignorado (sem erro)
  **Status:** Concluída

- [x] **TASK-3.4.3** ✅: Registrar schedule no BillingServiceProvider

  **T — Tarefa:** Adicionar `billing:generate-monthly-invoices` ao schedule do `BillingServiceProvider`: primeiro dia do mês às 06:00, `withoutOverlapping()`, `onOneServer()`.
  **A — Arquivo:** `api/src/Domain/Billing/Providers/BillingServiceProvider.php`
  **C — Comportamento:**
  - ANTES: comando não está no schedule
  - DEPOIS: executado automaticamente no dia 1 de cada mês às 06:00
  **E — Evidência:**
  - [x] `php artisan schedule:list` exibe o comando com frequência mensal
  **Status:** Concluída

- [x] **TASK-3.4.4** ✅: Testes de Billing (Feature Tests Pest)

  **T — Tarefa:** Criar feature test cobrindo: tenant sem excedente, tenant com excedente, idempotência, tenant sem plano.
  **A — Arquivo:** `api/tests/Feature/Billing/BillingGenerateMonthlyInvoicesTest.php`
  **C — Comportamento:**
  - ANTES: nenhum teste de geração mensal
  - DEPOIS: cobertura dos cenários críticos do comando
  **E — Evidência:**
  - [x] `php artisan test --filter BillingGenerateMonthly` passa com ≥ 4 testes
  - [ ] `composer gate:all` passa
  **Status:** Concluída com pendência externa de gate global

### Revisão de Fase 3 (REVIEWER + QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Migrations com data migration e rollback | DBA | aguardando |
| `BelongsToTenant` mantido em todos os queries | REVIEWER | aguardando |
| `declare(strict_types=1)` em todos os arquivos | REVIEWER | aguardando |
| `final class` em Actions | REVIEWER | aguardando |
| `composer gate:all` passa | QA | aguardando |
| Testes de overage cobrindo edge cases | QA | aguardando |

---

## FASE 5: FRONTEND (app/)

### 5.1 — Tela de Planos (plan-crud-form)

- [x] **TASK-5.1.1** ✅: Atualizar interface TypeScript PlatformPlan

  **T — Tarefa:** Adicionar `token_limit_monthly: number | null`, `allow_overage: boolean`, `overage_price_per_1k: number | null` às interfaces `PlatformPlan` e `PlatformPlanPayload`.
  **A — Arquivo:** `app/src/app/pages/platform/models/platform-plan.model.ts`
  **C — Comportamento:**
  - ANTES: Interface sem campos de token
  - DEPOIS: Interfaces tipadas com os 3 campos
  **E — Evidência:**
  - [x] `pnpm build` em `app/` compila sem erros TypeScript
  **Status:** Concluída

- [x] **TASK-5.1.2** ✅: Atualizar plan-crud-form.ts

  **T — Tarefa:** Adicionar os 3 campos ao `FormBuilder` group e ao método de payload; `allow_overage` como `FormControl<boolean>`, `token_limit_monthly` e `overage_price_per_1k` como `FormControl<number | null>`. Usar signal ou `valueChanges` para controlar visibilidade de `overage_price_per_1k`.
  **A — Arquivo:** `app/src/app/pages/platform/plans/plan-list/components/plan-crud-form/plan-crud-form.ts`
  **C — Comportamento:**
  - ANTES: Form sem campos de token
  - DEPOIS: Submit envia `{ token_limit_monthly, allow_overage, overage_price_per_1k }` no payload
  **E — Evidência:**
  - [x] `pnpm build` em `app/` compila sem erros
  **Status:** Concluída

- [x] **TASK-5.1.3** ✅: Atualizar plan-crud-form.html

  **T — Tarefa:** Adicionar seção "Limite de IA" com: input number para `token_limit_monthly`, toggle/checkbox para `allow_overage`, input number para `overage_price_per_1k` visível apenas quando `allow_overage = true` (via `@if`).
  **A — Arquivo:** `app/src/app/pages/platform/plans/plan-list/components/plan-crud-form/plan-crud-form.html`
  **C — Comportamento:**
  - ANTES: Form sem seção de IA
  - DEPOIS: Seção "Limite de IA" com 3 campos; preço do excedente aparece/some conforme toggle
  **E — Evidência:**
  - [x] UI renderiza seção corretamente
  - [x] Campo `overage_price_per_1k` só aparece quando `allow_overage = true`
  **Status:** Concluída

---

### 5.2 — Tela de Prompt Plans (limpar)

- [x] **TASK-5.2.1** ✅: Remover campos de token de prompt-plans

  **T — Tarefa:** Remover `token_limit_monthly` e `allow_overage` do `FormBuilder` (e controles de form associados) e do template HTML da tela de prompt plans.
  **A — Arquivo:** `app/src/app/pages/platform/ai-governance/prompt-plans/prompt-plans.ts`
  **A — Arquivo:** `app/src/app/pages/platform/ai-governance/prompt-plans/prompt-plans.html`
  **C — Comportamento:**
  - ANTES: Form e template têm campos de token
  - DEPOIS: Tela de prompt plans sem campos de token; PATCH não envia esses campos
  **E — Evidência:**
  - [x] `pnpm build` em `app/` compila sem erros
  - [x] Tela de prompt plans não exibe mais campos de token
  **Status:** Concluída

### Revisão de Fase 5 (REVIEWER + QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Standalone components mantidos | REVIEWER | aguardando |
| Control flow novo (`@if`, `@for`) | REVIEWER | aguardando |
| `pnpm --filter app build` limpo | QA | aguardando |

---

## FASE 6: INTEGRATION

### 6.1 — Validação E2E

- [ ] **TASK-6.1.1** ⏳: Validação end-to-end dos critérios de aceite

  **T — Tarefa:** Executar todos os critérios de aceite da feature doc (CA-1 a CA-9) e confirmar que passam.
  **A — Arquivo:** `.context/DOCS/FEATURES/FEAT-003-token-limits-platform-plans.md` (marcar CAs)
  **C — Comportamento:**
  - ANTES: CAs não verificados
  - DEPOIS: Todos os 9 CAs marcados como atendidos
  **E — Evidência:**
  - [ ] CA-1 a CA-9 verificados manualmente ou via testes
  - [ ] CHANGELOG atualizado em `.context/DOCS/CHANGELOG/2026-05-05.md`
  - [ ] MEMORY atualizado (decisão de mover token limits para platform_plans)
  **Status:** Pendente

### Revisão de Fase 6 (PM)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Critérios de aceite CA-1 a CA-9 atendidos | PM | aguardando |
| CHANGELOG atualizado | DOC | aguardando |
| MEMORY criada (decisão arquitetural) | DOC | aguardando |
| project-state.yaml atualizado | DOC | aguardando |
