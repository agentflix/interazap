# Feature: Migração de Token Limits para PlatformPlans + Cobrança de Overage

## Metadados

| Campo | Valor |
|-------|-------|
| ID | FEAT-003 |
| Bounded Context | Platform, Billing, Ai |
| Workspaces afetados | api, app |
| Complexidade | G |
| Status | Implementada (validação global pendente) |
| Autor (PM) | Rafael Silva |
| Aberta em | 2026-05-05 |
| Fechada em | |

---

## Resumo

Os campos `token_limit_monthly`, `allow_overage` e `overage_price_per_1k` existem em `ai_prompt_plans`, mas semanticamente pertencem ao plano de negócio (`platform_plans`). Um limite de tokens é uma política do plano contratado — não do prompt de IA. Esta feature migra esses campos para `platform_plans`, cria a lógica de cálculo de excedente e gera faturas mensais automáticas com overage somado à mensalidade.

---

## Objetivo de Negócio

Permitir que cada plano da plataforma defina uma cota mensal de tokens de IA. Quando o tenant ultrapassar essa cota (e `allow_overage = true`), o excedente é calculado em reais (R$ por 1k tokens) e somado automaticamente à fatura mensal. Isso viabiliza monetização granular do uso de IA sem bloquear o serviço.

---

## Bounded Context(s)

- `Platform` — `PlatformPlan` recebe os campos de limite de IA
- `Billing` — `BillingGenerateMonthlyInvoicesCommand` + `CalculateAiOverageAction` + `BillingInvoice`
- `Ai` — `AiPromptPlan` perde os campos (removidos); `AiUsageLog` é fonte de dados para overage

---

## Escopo

### Incluído

- [x] Migrar `token_limit_monthly`, `allow_overage`, `overage_price_per_1k` de `ai_prompt_plans` para `platform_plans`
- [x] Data migration: copiar valores existentes antes de dropar colunas
- [x] `CalculateAiOverageAction` — calcula excedente de tokens em BRL por período
- [x] `BillingGenerateMonthlyInvoicesCommand` — gera faturas mensais para todos os tenants ativos (subscription + overage)
- [x] Schedule mensal no `BillingServiceProvider` (1º dia do mês, 06:00)
- [x] Idempotência: não duplica fatura se `reference_month` já existir
- [x] Frontend: seção "Limite de IA" na tela de edição de planos (`plan-crud-form`)
- [x] Frontend: remover campos de token da tela de prompt plans

### Fora de Escopo

- Alertas em tempo real quando tenant se aproxima do limite (feature separada)
- Dashboard de consumo de tokens por tenant (feature separada)
- Conversão de moeda (overage sempre em BRL)
- Cobrança de overage por prompt específico (granularidade por feature)

---

## Critérios de Aceite

- [x] CA-1: `platform_plans` possui `token_limit_monthly`, `allow_overage`, `overage_price_per_1k` com dados migrados de `ai_prompt_plans`
- [x] CA-2: `ai_prompt_plans` não possui mais os 3 campos; API não os retorna nem aceita
- [x] CA-3: Tela de edição de planos exibe seção "Limite de IA" com os 3 campos; `overage_price_per_1k` visível apenas quando `allow_overage = true`
- [x] CA-4: Tela de prompt plans não exibe mais campos de token
- [x] CA-5: `php artisan billing:generate-monthly-invoices --dry-run` lista tenants sem criar faturas
- [x] CA-6: Fatura criada pelo comando tem `amount = price_monthly + overage_amount` e `metadata` com breakdown
- [x] CA-7: Segunda execução do comando no mesmo mês não duplica faturas (idempotência)
- [x] CA-8: Tenant sem excedente tem fatura com `amount = price_monthly` e `overage_applied: false` no metadata
- [ ] CA-9: `composer gate:all` e `pnpm --filter app build` passam sem erros
  - `app/`: `pnpm build` passou.
  - `api/`: testes focados e `composer format:test` passaram; `composer analyse` global segue falhando por erros preexistentes fora da FEAT-003.

---

## Dependências

- Nenhuma feature prévia de blocking
- `AiUsageLog` (tabela com índice `tenant_id, created_at`) — já existe
- `BillingInvoiceActions::create(BillingInvoiceDTO)` — reutilizado para criar faturas
- `BillingInvoice.metadata` (array nullable) — campo já existe para armazenar breakdown

---

## Riscos

| Risco | Impacto | Mitigação |
|-------|---------|-----------|
| Data loss ao dropar colunas de ai_prompt_plans | Alto | Migration de FASE 1 faz data migration antes; FASE 2 roda separada |
| Duplicação de faturas | Alto | Checar `reference_month` antes de criar (idempotência) |
| Query lenta em ai_usage_logs | Médio | Índice `(tenant_id, created_at)` já existe; usar `whereBetween` |
| Tenant sem plano associado | Baixo | Guard no comando: skip tenants com `plan_id = null` |
| overage_price_per_1k nulo com allow_overage=true | Baixo | Tratar como R$ 0,00 (sem cobrança) se campo for null |

---

## Tasks

> Decomposição feita pelo PLAN em 2026-05-05.
> Ver `.context/DOCS/TASKS/feat-003-token-limits-tasks.md`

---

## Histórico

- 2026-05-05: Identificada na análise de campos dead data em ai_prompt_plans
- 2026-05-05: Planejada por @PLAN (contexto em `CONTEXTO-MIGRACAO-TOKEN-LIMITS.md`)
- 2026-05-05: Feature doc criada e decomposta em tasks
- 2026-05-05: Implementada por Codex; validação global pendente por débitos PHPStan preexistentes
