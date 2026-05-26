# 0005-PRD-trial-signup-checkout

**Versão:** 1.0
**Data:** 2026-05-25
**Autor:** Rafael Silva
**Status:** [ ] Rascunho | [x] Em revisão | [ ] Aprovado

---

## Visão Geral

Abrir o funil comercial do InteraZap implementando: (1) cadastro público self-service via email/senha e Google OAuth, (2) plano `trial` de 7 dias com 100 mensagens IA ativado automaticamente em todo novo tenant, (3) tarja global de upgrade nas telas autenticadas e (4) modal inline de conversão usando checkout transparente do Asaas (tokenização de cartão no browser) com habilitação de cobrança recorrente mensal.

## Problema

Hoje o InteraZap não tem fluxo público de aquisição:
- `POST /platform/tenants` é admin-only — prospects não conseguem se cadastrar sozinhos
- Não existe `laravel/socialite` nem qualquer scaffolding OAuth — login só por email/senha
- Não existe modelo de trial — todos os planos (`starter` R$ 97, `professional` R$ 297, `business` R$ 897) são pagos desde o dia 1
- Modal de upgrade existente (`upgrade-modal`) entrega `payment_url` hospedado do Asaas — força redirect, perde contexto e converte menos
- Sem cartão tokenizado salvo: cada renovação mensal exigiria reentrada de cartão, gerando churn passivo

Consequências: lead → cliente exige toque comercial manual; ciclo de venda mais longo; nenhum produto-led-growth; conversão de upgrade depende de fluxo de redirect.

## Solução

1. Plano `trial` em `platform_plans` com `cycle_days=7`, `message_limit_monthly=100`, `overage_mode='stop'`, `is_trial=true`, `price=0`. Reusa toda a maquinaria de bilhetagem entregue pela FEAT-003 (`tenant_message_usage`, `check-and-increment`, `BillingCycleCalculator`).
2. Endpoint público `POST /auth/signup` (rate-limited) cria tenant + user owner + plano trial + customer Asaas + dispara verificação email.
3. Endpoints `GET /auth/google/{redirect,callback}` via `laravel/socialite` cobrem login e signup via Google. Vincula por email quando user existe; cria tenant+user+trial quando email é novo. `email_verified_at` preenchido automaticamente.
4. Migration adiciona `auth_users.provider`, `auth_users.provider_id` com index único.
5. `TrialBannerComponent` sticky no topo do `MainLayoutComponent`, com 3 estados visuais: normal (azul), alerta (amarelo — `<48h restantes` ou `>=80 msgs`), expirado (vermelho).
6. `QuickUpgradeModalComponent` em 2 steps: (a) escolha de plano reaproveitando `plan-card`, (b) formulário de cartão tokenizado via SDK JS Asaas. PAN e CVV nunca chegam ao backend.
7. `POST /billing/upgrade-from-trial` recebe `card_token`, executa charge imediato via `BillingGatewayService.createPaymentWithToken()`, ativa novo plano por meio de `BillingChangePlanAction` (com flag `bypass_password=true`), salva token em `platform_tenants.payment_method_token` para recorrência.
8. `BillingCycleCalculator` deixa de hardcoded 30 dias — passa a usar `plan.cycle_days` (default 30 preserva comportamento dos planos pagos).
9. Schema sem migração de dados — banco já está limpo da FEAT-003. Migrations apenas adicionam colunas e seed do plano trial.

## Usuários

- **Primário:** Prospect / lead — auto-cadastra via signup ou Google, usa trial, decide contratar
- **Secundário:** Tenant owner — converte trial → pago, gerencia método de pagamento tokenizado
- **Terciário:** Super-admin InteraZap — não toca trial (auto-ativado), só ajusta cota nos demais planos
- **Quaternário:** Cliente final do tenant — recebe IA enquanto trial ativo; recebe handoff humano após bloqueio

## Requisitos Funcionais

1. **[RF01]** Migration adiciona `platform_plans.cycle_days INT NOT NULL DEFAULT 30` e `platform_plans.is_trial BOOLEAN NOT NULL DEFAULT FALSE`
2. **[RF02]** Migration adiciona `platform_tenants.has_used_trial BOOLEAN NOT NULL DEFAULT FALSE`, `payment_method_token VARCHAR(255) NULL`, `payment_method_brand VARCHAR(20) NULL`, `payment_method_last4 VARCHAR(4) NULL`
3. **[RF03]** Migration adiciona `auth_users.provider VARCHAR(20) NULL`, `auth_users.provider_id VARCHAR(255) NULL` + UNIQUE INDEX `(provider, provider_id)`
4. **[RF04]** Seeder `PlatformPlanTrialSeeder` cria plano `trial` com `price=0`, `cycle_days=7`, `message_limit_monthly=100`, `overage_mode='stop'`, `is_trial=true`, `is_active=true`
5. **[RF05]** Endpoint `POST /auth/signup` (público, `throttle:public`, máx 10/IP/min) aceita `{name, email, password, document?, phone?}`. Cria `PlatformTenant` via `PlatformTenantBootstrapAction` com `plan_id` do trial, cria `AuthUser` com role `owner`, cria customer no Asaas via `BillingGatewayService.ensureCustomer()`, envia email de verificação, retorna `{user, token (Sanctum), permissions, tenant_plan}` (mesmo payload do login)
6. **[RF06]** Endpoint `GET /auth/google/redirect` redireciona para Google OAuth com scopes `email`, `profile`. Via `laravel/socialite`
7. **[RF07]** Endpoint `GET /auth/google/callback` resolve user via Socialite. Se `provider_id` já existe → emite token Sanctum. Se email existe mas provider não → preenche `provider`/`provider_id`/`email_verified_at`. Se email novo → executa mesmo fluxo do `AuthSignupAction` (tenant + user + trial), preenche `email_verified_at` com `now()`
8. **[RF08]** Endpoint `POST /billing/upgrade-from-trial` (`auth:sanctum`, role owner/admin) aceita `{plan_id, card_token, hold_cvv?}`. Valida que tenant está em plano `is_trial=true`. Chama `BillingGatewayService.createPaymentWithToken(asaas_customer_id, card_token, amount)`. Se Asaas retorna `CONFIRMED` síncrono → invoca `BillingChangePlanAction` com `bypass_password=true`, salva token em `platform_tenants`, marca `has_used_trial=true`. Se retorno é `PENDING` → webhook `PAYMENT_CONFIRMED` finaliza
9. **[RF09]** Endpoint `POST /billing/payment-method` (`auth:sanctum`) aceita `{card_token}`, valida via Asaas, atualiza `payment_method_token`/`brand`/`last4` no tenant
10. **[RF10]** `BillingChangePlanAction` ganha parâmetro `bypass_password` (default `false`); quando `true`, pula validação de senha e idempotência por requisição interna do upgrade-from-trial
11. **[RF11]** `BillingCycleCalculator` passa a usar `plan.cycle_days` para calcular `cycle_end` (`cycle_start + cycle_days`). Default 30 preserva ciclo mensal aniversário dos planos pagos
12. **[RF12]** Conversão trial → pago cria novo `tenant_message_usage` com `cycle_start = now()`, `cycle_end = now() + plan.cycle_days`, `message_count = 0`. Ciclo trial anterior é fechado (não migrado)
13. **[RF13]** Job `CloseExpiredTrialJob` (cron 03:00 UTC diário): para cada tenant com plano `is_trial=true` e `cycle_end < now()`, enfileira `SendTrialExpiredEmailJob`. Estado expirado é derivado em runtime, não persistido
14. **[RF14]** Job `SendTrialEndingSoonJob` (cron 09:00 UTC diário): para cada trial com `cycle_end - now() <= 2 dias`, envia email lembrete via fila existente. Marca `trial_ending_soon_sent_at` no `tenant_message_usage` para não duplicar
15. **[RF15]** Endpoint interno `POST /internal/billing/payment-methods` no gateway (NestJS) recebe `{customer_id, token}`, valida com Asaas API, retorna `{brand, last4}`. Consumido pelo `BillingSetPaymentMethodAction`
16. **[RF16]** Componente Angular `TrialBannerComponent`: sticky no topo do `MainLayoutComponent`. Visível somente quando `subscription.plan.is_trial=true`. 3 estados — normal (texto + dias/msgs restantes + botão Contratar), alerta (cor amarela + barra inline) em `<48h` ou `>=80 msgs`, expirado (cor vermelha + botão pulsa "Contratar agora")
17. **[RF17]** Componente `QuickUpgradeModalComponent`: 2 steps. Step 1 lista 3 plan-cards (reusa `plan-card` existente) + texto "trial X/100 msgs usadas, conversas serão preservadas". Step 2 form de cartão (holder, número, MM/AA, CVV) — SDK Asaas tokeniza no browser, envia `card_token` ao backend
18. **[RF18]** Service Angular `AsaasCheckoutService`: wrapper do SDK JS oficial Asaas. Método `tokenizeCard(payload): Promise<{token, brand, last4}>`. Trata 3DS modal automaticamente quando exigido pelo emissor
19. **[RF19]** Componente `GoogleLoginButtonComponent`: botão padrão com logo oficial Google. Usado em `pages/auth/login/` e `pages/auth/signup/`. Aciona `window.location.href = '/auth/google/redirect'`
20. **[RF20]** Página `pages/auth/signup/`: form (name, email, password, accept_terms) + `GoogleLoginButton` no topo + link "Já tem conta?" para `/auth/login`
21. **[RF21]** Página `pages/auth/google-callback/`: lê `?code` da query, chama `auth.service.handleGoogleCallback()`, persiste token, redireciona para `/dashboard`
22. **[RF22]** `pages/auth/login/` recebe `GoogleLoginButton` acima do form email/senha
23. **[RF23]** `usage-stats` exibe seção destacada "🎁 Trial · termina em N dias (DD/MMM/AAAA)" quando plano `is_trial=true`, com botão "Contratar plano completo" abaixo da barra
24. **[RF24]** Em conversas (chat), quando `subscription.usage.ai_messages.allowed=false` por trial expirado, exibe card inline "⚠️ IA pausada — trial expirado" com botão "Contratar plano"

## Requisitos Não-Funcionais

1. **[RNF01]** `POST /auth/signup` p95 < 800ms (inclui email send assíncrono via fila)
2. **[RNF02]** `POST /billing/upgrade-from-trial` p95 < 2s (charge Asaas síncrono)
3. **[RNF03]** Rate-limit `throttle:public` em `POST /auth/signup` (máx 10/IP/min); rate-limit em `GET /auth/google/callback` (máx 30/IP/min) para anti-replay de `code`
4. **[RNF04]** PCI: nenhum log estruturado, request body persistido ou exception trace pode conter PAN, CVV ou full track data. Auditoria automática em CI via lint custom
5. **[RNF05]** PCI scope = SAQ-A. Tokenização ocorre exclusivamente no browser (SDK Asaas → tokenize endpoint Asaas). Backend recebe apenas `card_token`, `brand`, `last4`
6. **[RNF06]** Idempotência do upgrade-from-trial via webhook `PAYMENT_CONFIRMED` + flag `has_used_trial` (segundo POST do mesmo tenant retorna 409)
7. **[RNF07]** Tenant isolation respeitado em todas as queries de `tenant_message_usage`, `platform_tenants`, `auth_users` (verificar via gate `composer gate:tenant-isolation`)
8. **[RNF08]** Logs estruturados: `auth.signup.success` (info), `auth.signup.duplicate_email` (warn), `auth.google.linked` (info), `billing.upgrade_from_trial.success` (info), `billing.upgrade_from_trial.declined` (warn)
9. **[RNF09]** Métricas Prometheus: `auth_signup_total{source=email|google,status}`, `billing_trial_conversions_total{plan,outcome}`, `auth_google_callback_failures_total{reason}`
10. **[RNF10]** Cobertura mínima 80% em `Auth/Actions/AuthSignupAction`, `Auth/Actions/AuthGoogleCallbackAction`, `Billing/Actions/BillingUpgradeFromTrialAction`, `Billing/Jobs/CloseExpiredTrialJob`
11. **[RNF11]** Mudança de `has_used_trial` e de `payment_method_token` registrada em `audits` (tabela existente)
12. **[RNF12]** Webhook Asaas + idempotência mantida via `BillingWebhookEventRepository` existente (sem mudança na infraestrutura)

## Critérios de Aceite

- [ ] Migrations api criam novas colunas em `platform_plans`, `platform_tenants`, `auth_users` sem erros em `migrate:fresh --seed`
- [ ] Seeder cria plano `trial` ativo com cota 100/7d
- [ ] PHPUnit: `AuthSignupActionTest`, `AuthGoogleCallbackActionTest`, `BillingUpgradeFromTrialActionTest`, `BillingChangePlanActionTest` (estendido com bypass_password), `BillingCycleCalculatorTest` (cycle_days), `CloseExpiredTrialJobTest`, `SendTrialEndingSoonJobTest`, feature tests dos 5 endpoints novos
- [ ] Jest gateway: `payment-methods.controller.spec.ts` cobre tokenize OK + falha Asaas
- [ ] Jest app: `TrialBannerComponent` 3 estados, `QuickUpgradeModalComponent` fluxo 2 steps (step 1 → step 2 → success / step 2 → declined), `GoogleLoginButtonComponent`, `SignupPage`, `GoogleCallbackPage`
- [ ] Cenário manual 1 (signup email + trial + upgrade cartão): email novo em `/signup` → dashboard com tarja "Trial 7 dias · 0/100" → enviar 80 msgs → tarja amarela → Contratar → tokenizar cartão teste Asaas → plano alterado, contador zerado, tarja some
- [ ] Cenário manual 2 (trial expira por tempo): tenant com `cycle_start = now() - 8 dias` → `php artisan billing:close-expired-trials` → próxima mensagem IA bloqueada → tarja vermelha → chat exibe card "IA pausada"
- [ ] Cenário manual 3 (signup Google novo): `/signup` → "Continuar com Google" → autentica conta nova → callback cria tenant + plano trial + redireciona dashboard com `email_verified_at != null`
- [ ] Cenário manual 4 (login Google de user existente): user criado por email/senha autentica via Google com mesmo email → backend vincula provider → próximo login Google funciona sem novo cadastro
- [ ] Cenário manual 5 (charge declinado): cartão teste Asaas configurado p/ decline → modal exibe erro PT-BR, plano não troca, contador não zera, `has_used_trial` permanece false
- [ ] Tarja é exibida em todas as telas autenticadas (dashboard, conversas, configurações, contatos, campanhas) quando plano é trial e não expirado
- [ ] Tarja muda automaticamente para estado "alerta" em `<48h restantes` OU `>=80 msgs usadas`
- [ ] Token de cartão salvo habilita renovação mensal automática (testado simulando passagem de ciclo no plano contratado pós-trial)
- [ ] Webhook Asaas `PAYMENT_CONFIRMED` reconcilia caso o charge inicial seja assíncrono (testado disparando webhook manual)
- [ ] Rate-limit ativo em `POST /auth/signup` (teste integração: 11ª request em 60s do mesmo IP retorna 429)
- [ ] PCI: grep nos logs do api e gateway pós-cenários não revela PAN nem CVV; apenas `card_token`, `brand`, `last4`
- [ ] Documentação atualizada em `.context/DOCS/FEATURES/trial-signup-checkout.md`

## Wireframes / Fluxos

Mockups ASCII validados no brainstorm:

### Tarja topo global — estado normal
```
┌────────────────────────────────────────────────────────────────────────────┐
│ 🎁 Trial ativo · 5 dias restantes · 42/100 msgs IA      [ Contratar → ]   │
├────────────────────────────────────────────────────────────────────────────┤
│ InteraZap      Conversas   Contatos   Campanhas   Configurações    👤 RS  │
```

### Tarja — alerta (<48h ou ≥80 msgs, bg amarelo)
```
┌────────────────────────────────────────────────────────────────────────────┐
│ ⚠️  Trial acaba em 1 dia · 87/100 msgs IA               [ Contratar → ]   │
│    ████████████████████████████████████░░░░░  87%                          │
```

### Tarja — expirado (bg vermelho, botão pulsa)
```
┌────────────────────────────────────────────────────────────────────────────┐
│ 🔴 Trial expirado · IA pausada                  [ Contratar agora → ]     │
```

### Modal QuickUpgrade — step 1
```
┌──────────────── Escolha seu plano ─────────────────[ × ]─┐
│  Trial: 42/100 mensagens usadas · suas conversas e       │
│  contatos serão preservados ao contratar.                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐      │
│  │  Starter    │  │   Pro ⭐    │  │  Business   │      │
│  │  R$ 97/mês  │  │ R$ 297/mês  │  │ R$ 897/mês  │      │
│  │ ✓ 1.000 msg │  │ ✓ 5.000 msg │  │ ✓ 20.000 msg│      │
│  │ [ Escolher ]│  │ [ Escolher ]│  │ [ Escolher ]│      │
│  └─────────────┘  └─────────────┘  └─────────────┘      │
│  💳 Checkout seguro · cobrança recorrente mensal         │
└──────────────────────────────────────────────────────────┘
```

### Modal QuickUpgrade — step 2 (transparente)
```
┌──────────────── Contratar Pro ──────────────────────[ × ]─┐
│  ← Voltar         Plano Pro · R$ 297/mês · 5.000 msgs IA  │
│                                                           │
│  Cartão                                                   │
│  [ 1234 5678 9012 3456 ]                                  │
│  [ MM/AA ]    [ CVV ]                                     │
│  [ Nome no cartão ]                                       │
│  ☐ Aceito termos                                          │
│                                                           │
│              [ Confirmar e ativar — R$ 297 ]              │
│                                                           │
│  🔒 Pagamento processado por Asaas · seus dados não       │
│     passam pelos servidores InteraZap                     │
└───────────────────────────────────────────────────────────┘
```

### usage-stats em modo trial
```
┌─ Estatísticas de uso ─────────────────────────┐
│ 🎁 Trial · termina em 5 dias (30/mai/2026)    │
│ Mensagens IA                          42/100  │
│ ████████████░░░░░░░░░░░░░░░░░░░░░ 42%         │
│             [ Contratar plano completo → ]    │
```

### Chat — IA bloqueada
```
│ ⚠️  IA pausada — trial expirado                │
│ Esta conversa foi transferida para humano.    │
│           [ Contratar plano → ]               │
```

### Signup page
```
┌── Crie sua conta ──────────────────────────┐
│   [ G Continuar com Google ]               │
│   ──────── ou cadastre com email ────────  │
│   Nome    [ ___________________________ ]  │
│   Email   [ ___________________________ ]  │
│   Senha   [ ___________________________ ]  │
│   ☐ Aceito termos                          │
│                  [ Criar conta grátis ]    │
│   Já tem conta? [ Entrar ]                 │
└────────────────────────────────────────────┘
```

### Fluxo signup + trial + upgrade
```
Prospect → /signup
  ├─ form email/senha    → POST /auth/signup
  └─ Google              → GET /auth/google/redirect → callback
              │
              ▼
         tenant + user + plano trial + customer Asaas
              │
              ▼
         dashboard (TrialBanner ativo, 7d/0msgs)
              │
        ── uso ──
              ▼
    check-and-increment (FEAT-003): allowed=true (até 100 msgs OU 7d)
              │
        ── limite ──
              ▼
         allowed=false → IA pausada + tarja vermelha + card inline no chat
              │
              ▼
    botão Contratar → QuickUpgradeModal step1 (escolha plano)
              │
              ▼
         step2 (cartão) → SDK Asaas tokeniza no browser
              │
              ▼
    POST /billing/upgrade-from-trial {plan_id, card_token}
              │
              ▼
    BillingGatewayService.createPaymentWithToken → Asaas charge
              │
        ── CONFIRMED ──
              ▼
    BillingChangePlanAction(bypass_password=true)
    + save payment_method_token
    + new tenant_message_usage cycle
    + has_used_trial = true
              │
              ▼
    plano ativo, tarja some, IA volta a funcionar
```

## Dependências

- **Feature obrigatória:** FEAT-003 (message-based-billing) já entregue. Esta feature reusa `tenant_message_usage`, `check-and-increment`, `BillingCycleCalculator`, fluxo `overage_mode=stop`, `usage-stats`
- **Tabelas existentes:** `platform_plans`, `platform_tenants`, `auth_users`, `audits`, `billing_invoices`, `billing_webhook_events`, `tenant_message_usage`
- **Services/Actions existentes:**
  - `api/src/Domain/Billing/Services/BillingGatewayService.php` (estender com `createPaymentWithToken`)
  - `api/src/Domain/Billing/Actions/BillingAsaasWebhookAction.php` (handler upgrade-from-trial)
  - `api/src/Domain/Billing/Actions/BillingChangePlanAction.php` (adicionar `bypass_password`)
  - `api/src/Domain/Platform/Actions/PlatformTenantBootstrapAction.php` (reuso no signup)
  - `app/src/app/pages/settings/my-plan/components/plan-card/` (reuso no modal)
  - `app/src/app/core/services/auth.service.ts` (adicionar `signup`, `loginWithGoogle`)
- **Bibliotecas externas:**
  - `laravel/socialite ^5.x` — composer
  - SDK JS Asaas — npm (nome exato confirmar via Context7 antes da Fase 4)
- **Credenciais externas:**
  - Google OAuth Client ID/Secret (criar projeto no Google Cloud Console)
  - Asaas public key para SDK frontend
- **Variáveis ambiente:** `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `ASAAS_PUBLIC_KEY`

## Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| SDK JS Asaas mudar API ou exigir setup adicional para tokenização | Média | Alto | Validar SDK no início da Fase 3 via spike de 1h; fallback v1.5 = redirect `payment_url` se SDK travar |
| Self-signup virar vetor de spam / contas falsas | Alta | Médio | reCAPTCHA v3 + email verification obrigatória antes da 1ª mensagem IA + rate-limit IP agressivo |
| Multi-trial abuse (email novo após expirar) | Alta | Médio | Aceito em v1 (custo de aquisição). Em v1.5: fingerprint device + cross-check CPF/CNPJ + flag de risco em `audits` |
| Google OAuth callback falhar após user consentir (race condition criar tenant) | Baixa | Alto | Lock por email no `AuthGoogleCallbackAction`; `provider_id` único garante idempotência |
| Charge transparente falha mas user vê "Processando" eterno | Média | Alto | Webhook `PAYMENT_CONFIRMED` é fonte da verdade; UI exibe estado "Processando..." com timeout 30s + retry |
| 3DS obrigatório em alguns cartões trava fluxo inline | Média | Médio | SDK Asaas suporta 3DS modal automático; testar com cartões teste; fallback para `payment_url` se falhar |
| LGPD: salvar `last4` + `brand` + token Asaas sem consentimento explícito | Média | Médio | Termos de uso atualizados; modal exibe link de termos antes do submit; aceite registrado em `audits` |
| Vincular provider Google a user existente sem confirmação (account takeover via email Gmail comprometido) | Baixa | Crítico | v1 aceita auto-link por email match. v1.5: exigir confirmação por email do owner antes de vincular |
| Webhook Asaas atrasar e user pagar 2x | Baixa | Médio | `has_used_trial=true` + check de `subscription.plan.is_trial` no endpoint bloqueia segundo upgrade |

## Cronograma Estimado

- Planejamento (este PRD + feature doc + tasks): 1 dia ✅ (parcialmente concluído)
- Execução Backend api (migrations + seeder + socialite + actions signup/OAuth + upgrade-from-trial + jobs trial + testes): 3 dias
- Execução Gateway (endpoint `payment-methods` + integração Asaas + testes): 1 dia
- Execução Frontend (signup + Google callback + login button + trial banner + quick-upgrade-modal + asaas SDK service + testes): 3 dias
- Validação (5 cenários manuais + ajustes + code review): 2 dias

**Total estimado:** ~11 dias úteis

## Revisões

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-25 | Rafael Silva | Criação a partir de brainstorming (7 decisões) + exploração de codebase (Asaas + auth) + decisão de incluir signup público, OAuth Google e checkout transparente no mesmo PRD |
