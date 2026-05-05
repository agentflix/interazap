# Contexto de Conversa — Migração de Token Limits para PlatformPlans

> Gerado em: 2026-05-05
> Projeto: InteraZap (monorepo: api/ + gateway/ + app/ + electron/)
> Stack: Laravel 12 (PHP 8.3) + NestJS 11 + Angular 19 + PostgreSQL 17 + Redis 7

---

## 1. Problema Original

O usuário perguntou se o campo **"Regras Obrigatórias (JSON)"** na tela de editar prompt do plano estava sendo usado.

### Diagnóstico

O campo `mandatory_rules` existia em toda a stack (frontend → API → banco) mas **nunca era consumido** na resolução do prompt de IA. Era dead data.

O usuário pediu para remover.

### O que foi feito (COMPLETADO ✅)

O campo `mandatory_rules` foi removido de:
- **Frontend**: `prompt-plans.html`, `prompt-plans.ts`, `ai.model.ts`
- **Backend**: `AiPromptPlan.php` (Model), `PlanPromptDTO.php`, `UpdatePlanPromptRequest.php`, `PlanPromptResource.php`
- **Banco**: Migration `2026_05_05_000001_remove_mandatory_rules_from_ai_prompt_plans.php`
- **Factories/Seeders**: `AiPromptPlanFactory.php`, `AiPromptPlanSeeder.php`
- **Commit**: `refactor(ai): remove unused mandatory_rules field from plan prompts`
- **Testes**: 7 testes do PlanPrompt passando, build do frontend OK

---

## 2. Próximo Problema Identificado

O usuário perguntou sobre o campo **"Limite de Tokens Mensal"** — se estava sendo usado.

### Diagnóstico

**NÃO está sendo usado.** Assim como `mandatory_rules`, os campos `token_limit_monthly`, `allow_overage` e `overage_price_per_1k` são dead data em `ai_prompt_plans`.

**Evidência chave:**
- O sistema real de budget (`TokenBudgetService`) trabalha com **DÓLARES** (custo), não tokens
- Os limites vêm de cache/config por tenant, NÃO do `AiPromptPlan`
- `AiPromptResolverService::resolvePlanPrompt()` retorna apenas `$promptPlan->content` — ignora todos esses campos

### O que o usuário pediu

> "O campo de limite de tokens do mês deve ser usado mas está na tela errada — deve ser migrado dessa tabela e tela para planos. O permitir excedente também. Agora campo overage_price_per_1k precisa criar uma lógica que a cada 1k de tokens seja adicionado à fatura do cliente o valor do excedente. Planeje isso."

---

## 3. Análise da Codebase

### 3.1 Estrutura Atual

**`ai_prompt_plans` (tabela atual — campos a remover):**
- `token_limit_monthly` (integer nullable)
- `allow_overage` (boolean)
- `overage_price_per_1k` (decimal nullable)

**`platform_plans` (tabela de destino — campos a adicionar):**
- Atualmente tem: `name`, `slug`, `limit_users`, `storage_mode`, `storage_limit_bytes`, `ai_enabled`, `chat_channels_limit`, `negotiations_mode`, `negotiations_limit`, `reports_mode`, `price_monthly`, `asaas_product_id`, `is_active`
- Precisa ganhar: `token_limit_monthly`, `allow_overage`, `overage_price_per_1k`

### 3.2 Como as faturas funcionam hoje

**Faturas SÃO criadas em:**
1. `BillingChangePlanAction.php` — quando tenant faz upgrade de plano (fatura pro-rata)
2. `BillingInvoiceActions::create()` — via API manual

**Faturas NÃO são criadas automaticamente:**
- ❌ Não existe comando `billing:generate-monthly-invoices`
- ❌ Não existe job recorrente que gera fatura mensal
- ❌ Não existe schedule para isso no `BillingServiceProvider`

**Comandos agendados existentes:**
| Comando | Frequência | Função |
|---------|-----------|--------|
| `billing:check-overdue` | Horária | Marca faturas vencidas |
| `billing:send-reminders` | Diária 09:00 | Envia lembretes |
| `billing:purge-delinquent` | Diária 02:00 | Purga inadimplentes |
| `streams:billing-consume` | Contínuo | Consome webhooks Asaas |

### 3.3 Como o uso de tokens é rastreado

**Tabela `ai_usage_logs`:**
- `tenant_id` (uuid)
- `input_tokens` (integer)
- `output_tokens` (integer)
- `input_cost` (decimal)
- `output_cost` (decimal)
- `feature` (string — autopilot/rag/chat)
- `created_at` (timestamp)
- `ai_model_pricing_id` (uuid)

**Query para total de tokens de um tenant no mês:**
```sql
SELECT SUM(input_tokens + output_tokens)
FROM ai_usage_logs
WHERE tenant_id = ?
  AND created_at BETWEEN '2026-05-01' AND '2026-05-31'
```

### 3.4 Telas de UI envolvidas

**Tela de Planos (onde os campos DEVEM ir):**
- `app/src/app/pages/platform/plans/plan-list/plan-list.ts`
- `app/src/app/pages/platform/plans/plan-list/components/plan-crud-form/plan-crud-form.ts`
- `app/src/app/pages/platform/plans/plan-list/components/plan-crud-form/plan-crud-form.html`
- Model: `app/src/app/pages/platform/models/platform-plan.model.ts`

**Tela de Prompt Plans (de onde os campos DEVEM sair):**
- `app/src/app/pages/platform/ai-governance/prompt-plans/prompt-plans.ts`
- `app/src/app/pages/platform/ai-governance/prompt-plans/prompt-plans.html`

---

## 4. Plano Completo de Implementação

### FASE 0 — Geração Automática de Faturas + Overage (NOVO)

```
TASK-0.1: Criar BillingGenerateMonthlyInvoicesCommand
  T: Comando agendado no 1º dia do mês que cria faturas para todos os tenants ativos
  A: api/src/Domain/Billing/Console/Commands/BillingGenerateMonthlyInvoicesCommand.php
  C: Para cada tenant ativo com plano, cria fatura com:
     - amount = price_monthly + overage (se aplicável)
     - reference_month = mês atual
     - due_date = 5º dia útil do mês
     - metadata = { overage_applied, overage_tokens, overage_amount }
  E: php artisan billing:generate-monthly-invoices --dry-run lista tenants que seriam faturados

TASK-0.2: Agendar comando no BillingServiceProvider
  T: Adicionar schedule no 1º dia de cada mês às 06:00
  A: api/src/Domain/Billing/Providers/BillingServiceProvider.php
  C: Comando executado automaticamente todo início de mês
  E: schedule:run executa o comando no dia correto

TASK-0.3: Criar CalculateAiOverageAction
  T: Action que calcula overage de tokens para um tenant em um mês
  A: api/src/Domain/Billing/Actions/CalculateAiOverageAction.php
  C: Query ai_usage_logs, compara com token_limit_monthly do plano, retorna valor em reais
  E: Teste unitário: 150k consumidos, limite 100k, preço R$0.005/k = R$250.00
```

### FASE 1 — Migrar campos para PlatformPlans

```
TASK-1.1: Adicionar colunas à tabela platform_plans
  T: Criar migration que adiciona token_limit_monthly, allow_overage, overage_price_per_1k
  A: api/database/migrations/YYYY_MM_DD_add_ai_token_fields_to_platform_plans.php
  C: Tabela platform_plans ganha 3 novas colunas
  E: php artisan migrate roda sem erro

TASK-1.2: Atualizar PlatformPlan Model
  T: Adicionar campos ao $fillable, $casts e phpDoc
  A: api/src/Domain/Platform/Models/PlatformPlan.php
  C: Model ganha os 3 campos
  E: PlatformPlan::first()->token_limit_monthly retorna valor

TASK-1.3: Atualizar PlatformPlan Factory
  T: Adicionar campos com valores default
  A: api/database/factories/PlatformPlanFactory.php
  E: PlatformPlan::factory()->make() contém os 3 campos

TASK-1.4: Atualizar PlatformPlanSeeder
  T: Adicionar valores para starter/professional/business
  A: api/database/seeders/PlatformPlanSeeder.php
  E: Seeder executa sem erro

TASK-1.5: Atualizar frontend PlatformPlan model/interface
  T: Adicionar campos às interfaces PlatformPlan e PlatformPlanPayload
  A: app/src/app/pages/platform/models/platform-plan.model.ts
  E: Build compila sem erro TypeScript

TASK-1.6: Atualizar form de edição de planos (TS)
  T: Adicionar campos ao FormBuilder group e buildPayload
  A: app/src/app/pages/platform/plans/plan-list/components/plan-crud-form/plan-crud-form.ts
  E: Form submission envia payload correto

TASK-1.7: Atualizar template HTML do form de planos
  T: Adicionar inputs para os 3 campos (seção "Limite de IA")
  A: app/src/app/pages/platform/plans/plan-list/components/plan-crud-form/plan-crud-form.html
  E: UI renderiza corretamente
```

### FASE 2 — Integrar overage na criação de faturas

```
TASK-2.1: Integrar overage em BillingInvoiceActions::create()
  T: Modificar create() para chamar CalculateAiOverageAction e somar ao amount
  A: api/src/Domain/Billing/Actions/BillingInvoiceActions.php
  C: Fatura criada com amount = price_monthly + overage (se aplicável)
  E: Teste: tenant que excedeu limite tem fatura > price_monthly

TASK-2.2: Integrar overage em BillingChangePlanAction (upgrade)
  T: Quando criar fatura de upgrade, também calcular overage
  A: api/src/Domain/Billing/Actions/BillingChangePlanAction.php
  E: Fatura de upgrade inclui overage se aplicável
```

### FASE 3 — Limpar AiPromptPlan (remover campos migrados)

```
TASK-3.1: Criar migration para remover campos de ai_prompt_plans
  T: Migration que remove token_limit_monthly, allow_overage, overage_price_per_1k
  A: api/database/migrations/YYYY_MM_DD_remove_token_fields_from_ai_prompt_plans.php
  E: php artisan migrate executa com sucesso

TASK-3.2: Atualizar AiPromptPlan Model
  T: Remover campos do $fillable, $casts e phpDoc
  A: api/src/Domain/Ai/Models/AiPromptPlan.php
  E: PHPStan passa

TASK-3.3: Atualizar AiPromptPlanSeeder
  T: Remover campos do updateOrCreate
  A: api/database/seeders/AiPromptPlanSeeder.php
  E: Seeder executa sem erro

TASK-3.4: Atualizar AiPromptPlanFactory
  T: Remover campos da definition
  A: api/database/factories/AiPromptPlanFactory.php
  E: Factory não contém mais os campos
```

### FASE 4 — Limpar UI de PromptPlans

```
TASK-4.1: Remover campos token/overage do form PromptPlans
  T: Remover campos do frontend prompt-plans
  A: app/src/app/pages/platform/ai-governance/prompt-plans/prompt-plans.ts
  A: app/src/app/pages/platform/ai-governance/prompt-plans/prompt-plans.html
  E: Build compila; campos não aparecem na UI

TASK-4.2: Atualizar PlanPromptResource, DTO e Request (API)
  T: Remover token_limit_monthly, allow_overage, overage_price_per_1k
  A: api/src/Domain/Ai/Http/Resources/PlanPromptResource.php
  A: api/src/Domain/Ai/DTOs/PlanPromptDTO.php
  A: api/src/Domain/Ai/Http/Requests/UpdatePlanPromptRequest.php
  E: API não retorna nem aceita mais esses campos
```

---

## 5. Decisões Arquiteturais

### 5.1 Lógica de cálculo do overage

```php
// CalculateAiOverageAction
$tokensConsumed = ai_usage_logs::sum(input_tokens + output_tokens)
$limit = $tenant->plan->token_limit_monthly
$allowed = $tenant->plan->allow_overage
$price = $tenant->plan->overage_price_per_1k

if ($allowed && $tokensConsumed > $limit) {
    $excess = $tokensConsumed - $limit;
    $overage = ($excess / 1000) * $price;
} else {
    $overage = 0;
}
```

### 5.2 Armazenamento na fatura

- O `overage_amount` é somado ao `amount` final da fatura
- Metadados gravados em `metadata`: `{ overage_applied: true, overage_tokens: 50000, overage_amount: 250.00 }`

### 5.3 Idempotência

- O comando de geração de faturas deve verificar se já existe fatura para o `reference_month` antes de criar
- Se existir, não duplicar — apenas atualizar se necessário

### 5.4 Multi-tenancy

- Toda query deve passar pelo tenant scope
- `TokenBudgetService` já existe mas trabalha em tempo real (por-run), não para fechamento mensal

---

## 6. Convenções do Projeto

### PHP / Laravel (api/)
- `declare(strict_types=1)` em todos os arquivos
- `final class` em Controllers, Actions, DTOs
- `$fillable` explícito (NUNCA `$guarded = []`)
- UUID primary keys
- Trait `BelongsToTenant` em Models multi-tenant
- Eager loading (sem N+1)

### TypeScript / Angular (app/)
- Standalone components
- Signals para estado simples
- Control flow novo (`@if`, `@for`, `@switch`)

### Git
- Conventional Commits (pt-BR): `feat(escopo): descrição`
- Escopos: `api`, `gateway`, `app`, `db`, `docs`

---

## 7. Ordem de Execução Recomendada

```
Fase 0 (billing) ──────────────────────────────────────────────┐
                                                              ├──→ Fase 2 (integrar overage)
Fase 1 (migrar campos) ───────────────────────────────────────┘
                                                              └──→ Fase 3 (limpar ai_prompt_plans)
                                                                       └──→ Fase 4 (limpar UI PromptPlans)
```

Fases 0 e 1 podem rodar em paralelo. Fase 2 depende de ambas. Fase 3 depende da Fase 1. Fase 4 depende da Fase 3.

---

## 8. Arquivos-Chave de Referência

| Propósito | Caminho |
|-----------|---------|
| Model PlatformPlan | `api/src/Domain/Platform/Models/PlatformPlan.php` |
| Model AiPromptPlan | `api/src/Domain/Ai/Models/AiPromptPlan.php` |
| Model BillingInvoice | `api/src/Domain/Billing/Models/BillingInvoice.php` |
| BillingInvoiceActions | `api/src/Domain/Billing/Actions/BillingInvoiceActions.php` |
| BillingChangePlanAction | `api/src/Domain/Billing/Actions/BillingChangePlanAction.php` |
| TokenBudgetService | `api/src/Domain/Ai/Services/TokenBudgetService.php` |
| BillingServiceProvider | `api/src/Domain/Billing/Providers/BillingServiceProvider.php` |
| PlatformPlanSeeder | `api/database/seeders/PlatformPlanSeeder.php` |
| AiPromptPlanSeeder | `api/database/seeders/AiPromptPlanSeeder.php` |
| Frontend: form planos | `app/src/app/pages/platform/plans/plan-list/components/plan-crud-form/` |
| Frontend: form prompt plans | `app/src/app/pages/platform/ai-governance/prompt-plans/` |
| Frontend: model PlatformPlan | `app/src/app/pages/platform/models/platform-plan.model.ts` |
| Frontend: model AI | `app/src/app/pages/ai/models/ai.model.ts` |
| Migration platform_plans | `api/database/migrations/2026_01_01_000000_create_platform_tables.php` |
| Migration ai_prompt_plans | `api/database/migrations/2026_01_01_000051_create_ai_prompt_tables.php` |
| Migration ai_usage_logs | `api/database/migrations/2026_01_01_000050_create_ai_core_tables.php` |
| Migration billing | `api/database/migrations/2026_01_01_000010_create_billing_tables.php` |
