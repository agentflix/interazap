# FEAT-005 — Trial + Self-Signup + Google OAuth + Checkout Asaas

**Tipo:** Decisão
**Data:** 2026-05-25
**Autor:** BUILDER / ORCHESTRATOR
**Tags:** trial, billing, oauth, asaas, pci, signup

## Situação

InteraZap precisava abrir o funil comercial com self-signup + trial automático + conversão inline via cartão. A feature é cross-cutting entre Auth, Billing, Platform e Frontend.

## Decisões Principais

### Trial como Plano Real (não estado paralelo)
Trial é `PlatformPlan` com `is_trial=true`, `cycle_days=7`, `price_monthly=0`. Reutiliza toda a maquinaria de FEAT-003 (message quota, overage_mode=stop, TenantMessageUsage).

### BillingCycleCalculator generalizado
`calculate(anchorDay, reference, ?cycleDays=null)`. Quando `cycleDays` fornecido: ciclo fixo a partir de `reference`. Quando null: comportamento mensal anchor-day preservado. Backward-compatible.

### bypassPassword em BillingChangePlanAction
`BillingChangePlanDTO.bypassPassword=true` pula validação de senha. Usado por `BillingUpgradeFromTrialAction` — user já autenticado via Sanctum token, senha redundante.

### PCI SAQ-A via Asaas.js
PAN/CVV tokenizados 100% no browser via Asaas.js SDK. Backend recebe apenas `card_token` (opaco). Gateway valida token via `/creditCards/tokenize` da API Asaas. PCI lint em `composer gate:pci`.

### Google OAuth Server-Side (não implicit)
Redirect server-side via Socialite. Callback no api emite Sanctum token + redireciona para `app/?token=...`. Sem JavaScript OAuth. Vinculação automática por email match.

### Trial expirado = estado derivado em runtime
`cycle_end < now()` detectado em cada request. `CloseExpiredTrialJob` (03:00 UTC) serve apenas para notificação por email — não altera estado no banco.

### Idempotência de jobs via campos existentes
`alert_100_sent_at` como flag para email de trial expirado; `alert_80_sent_at` como flag para email "ending soon". Campos já existiam em `TenantMessageUsage`. Colunas dedicadas ficam para v1.5.

### has_used_trial anti-reentry
`platform_tenants.has_used_trial=false` bloqueado na conversão. Impede re-trial via mesmo tenant. Anti-fraude por email novo aceito como custo de aquisição em v1.

### Token salvo para recorrência mensal
`payment_method_token` + `brand` + `last4` salvos em `platform_tenants` após upgrade bem-sucedido. Habilita renovação automática sem novo input de cartão.

### Race condition Google OAuth
`Cache::lock("google-oauth-lock:{email}", 10)->block(5, ...)` serializa callbacks concorrentes para o mesmo email. Evita criação de tenant duplicado.

## Alternativas Consideradas

| Alternativa | Por que descartada |
|---|---|
| Trial como estado em `platform_tenants.is_trial` | Duplicaria lógica de cycle/quota. Plano como entidade reutiliza FEAT-003 sem overhead. |
| OAuth implicit (JS) | Expõe token no frontend redirect. Server-side mais seguro e simples de auditar. |
| Boleto/PIX na conversão v1 | Adiciona complexidade de polling de status assíncrono. Cartão tokenizado = conversão imediata. |
| reCAPTCHA no signup | Fricção sem evidência de abuso no lançamento. Planejado para v1.5 se necessário. |

## Consequências

- **Positivas:** Funil de self-signup completo. Trial automático sem intervenção humana. PCI scope mínimo (SAQ-A). Reuso de toda infraestrutura FEAT-003.
- **Negativas / Trade-offs:** Multi-trial por email novo não bloqueado em v1. Jobs usam campos reciclados como flags de idempotência (tech debt menor).
- **Ação necessária:** Monitorar `authSignupTotal` + `billingTrialConversionsTotal` no Prometheus. Adicionar `trial_expired_email_sent_at` e `trial_ending_soon_sent_at` em v1.5 para clareza.

## Arquivos-Chave

- `api/src/Domain/Auth/Actions/AuthSignupAction.php`
- `api/src/Domain/Auth/Actions/AuthGoogleCallbackAction.php`
- `api/src/Domain/Billing/Actions/BillingUpgradeFromTrialAction.php`
- `api/src/Domain/Billing/Services/BillingCycleCalculator.php`
- `api/src/Domain/Billing/Jobs/CloseExpiredTrialJob.php`
- `api/src/Domain/Billing/Jobs/SendTrialEndingSoonJob.php`
- `gateway/src/domains/billing/providers/asaas/asaas.client.ts`
- `gateway/src/domains/billing/controllers/payment-method.controller.ts`
- `app/src/app/core/components/trial-banner/trial-banner.ts`
- `app/src/app/pages/billing/quick-upgrade-modal/quick-upgrade-modal.ts`
- `app/src/app/core/services/asaas-checkout.service.ts`
