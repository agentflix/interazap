# Feature: Trial + Self-Signup + Google OAuth + Checkout Transparente Asaas

## Metadados
- ID: FEAT-005
- PRD: `.context/DOCS/PRDS/0005-PRD-trial-signup-checkout.md`
- Bounded Context: Onboarding & Billing (cross-cutting: Auth + Billing + Platform)
- Complexidade: G
- Status: ✅ Concluída
- Data: 2026-05-25

## Resumo

Abre o funil comercial do InteraZap: prospect cadastra-se sozinho (email/senha ou Google) e recebe automaticamente um plano `trial` com 100 mensagens IA por 7 dias. Ao atingir qualquer limite, a IA é pausada e uma tarja global de upgrade aparece em todas as telas; o tenant converte via modal inline com checkout transparente Asaas (tokenização no browser), habilitando recorrência mensal. Reduz fricção end-to-end de lead → trial → cliente pago.

## Módulos Afetados

- [ ] api/ (Laravel 12) — migrations, socialite, actions de signup/OAuth/upgrade-from-trial, jobs trial
- [ ] gateway/ (NestJS 11) — endpoint interno `payment-methods` (tokenização Asaas + recorrência)
- [ ] app/ (Angular 20) — telas signup + Google callback, trial-banner global, quick-upgrade-modal, SDK Asaas
- [ ] Infraestrutura — env vars Google OAuth + Asaas public key

## Escopo

### Incluído

- [ ] Migration: `platform_plans` ganha `cycle_days INT DEFAULT 30` e `is_trial BOOLEAN DEFAULT FALSE`
- [ ] Migration: `platform_tenants` ganha `has_used_trial`, `payment_method_token`, `payment_method_brand`, `payment_method_last4`
- [ ] Migration: `auth_users` ganha `provider VARCHAR(20) NULL`, `provider_id VARCHAR(255) NULL` + index único `(provider, provider_id)`
- [ ] Seeder: plano `trial` com `price=0`, `cycle_days=7`, `message_limit_monthly=100`, `overage_mode='stop'`, `is_trial=true`
- [ ] Endpoint `POST /auth/signup` (público, `throttle:public`) — cria tenant + user owner + plano trial + customer Asaas + envia verificação email
- [ ] Endpoint `GET /auth/google/redirect` — redireciona para Google via Socialite
- [ ] Endpoint `GET /auth/google/callback` — login se existe, vincula se email match, signup completo se novo
- [ ] Endpoint `POST /billing/upgrade-from-trial` (`auth:sanctum`) — recebe `card_token`, executa charge Asaas, ativa plano, salva token recorrência
- [ ] Endpoint `POST /billing/payment-method` — atualizar cartão tokenizado pós-trial
- [ ] `BillingChangePlanAction` extendida com flag `bypass_password=true` para chamada interna
- [ ] `BillingCycleCalculator` passa a usar `plan.cycle_days` (não hardcoded 30)
- [ ] Job `CloseExpiredTrialJob` (cron diário 03:00) — marca ciclo trial expirado quando `cycle_end < now()`
- [ ] Job `SendTrialEndingSoonJob` (cron diário) — email aos tenants com trial terminando em ≤2 dias
- [ ] Endpoint interno `POST /internal/billing/payment-methods` no gateway (recebe token Asaas, valida com Asaas API)
- [ ] Frontend: `pages/auth/signup/` — form email/senha + botão Google
- [ ] Frontend: `pages/auth/google-callback/` — handler de callback OAuth
- [ ] Frontend: `core/components/google-login-button/` — botão padrão Google em login e signup
- [ ] Frontend: `core/components/trial-banner/` — sticky topo, 3 estados (normal, alerta, expirado), injetado no `MainLayoutComponent`
- [ ] Frontend: `pages/billing/quick-upgrade-modal/` — 2 steps (escolha plano + cartão tokenizado)
- [ ] Frontend: `core/services/asaas-checkout.service.ts` — wrapper do SDK JS Asaas
- [ ] Frontend: `usage-stats` ganha card "Trial · termina em N dias" quando plano `is_trial`
- [ ] Frontend: card "IA pausada" no chat quando trial expirado
- [ ] Login page recebe botão "Continuar com Google"
- [ ] Cobertura ≥ 80% em services de signup/OAuth/upgrade-from-trial

### Fora de Escopo

- Reset de senha via OAuth (continua só email/senha)
- Anti-reentry trial sofisticado (CPF/CNPJ/fingerprint) — v1 só `has_used_trial` por tenant
- Trial estendido, trial pago, trial customizado por segmento — v1 modelo único 7d/100msg
- Boleto/PIX no modal de upgrade — v1 só cartão tokenizado (PIX/boleto seguem disponíveis em faturas pós-conversão via `payment_url`)
- Dashboard super-admin de funil/conversão — só métricas Prometheus
- reCAPTCHA v3 no signup — fica para v1.5 se signup virar vetor de abuso
- Vincular contas Google a tenants existentes via UI (só vincula automaticamente por email match)

## Critérios de Aceite

- [ ] `cd api && composer gate:all` verde
- [ ] `pnpm --filter gateway build && pnpm --filter gateway test` verde
- [ ] `pnpm --filter app build && pnpm --filter app test` verde
- [ ] `migrate:fresh --seed` cria plano trial e schema novo sem erros
- [ ] Cenário 1 (signup + trial + upgrade cartão): novo email em `/signup` → dashboard com tarja "Trial 7 dias · 0/100" → enviar 80 msgs → tarja vira amarela → clicar Contratar → escolher Pro → tokenizar cartão teste Asaas → plano alterado, contador zerado, próxima fatura agendada, tarja some
- [ ] Cenário 2 (trial expira por tempo): tenant com `cycle_start = now() - 8 dias` → `php artisan billing:close-expired-trials` → mensagem IA bloqueada (`check-and-increment allowed=false`) → tarja vermelha → chat exibe card "IA pausada"
- [ ] Cenário 3 (signup Google novo): `/signup` → "Continuar com Google" → autentica conta nova → callback cria tenant + plano trial + redireciona dashboard com `email_verified_at != null`
- [ ] Cenário 4 (login Google de user existente): usuário criado por email/senha autentica via Google com mesmo email → backend vincula `provider='google'` + `provider_id` ao user existente → login OK
- [ ] Cenário 5 (charge declinado): cartão teste Asaas configurado para decline → modal exibe erro estruturado em PT-BR, plano não troca, contador não zera
- [ ] Tarja é exibida em todas as telas autenticadas quando plano é trial e não expirado
- [ ] Tarja muda para estado "alerta" automaticamente em `<48h restantes` ou `>=80 msgs usadas`
- [ ] Token de cartão salvo em `platform_tenants.payment_method_token` habilita renovação mensal sem nova entrada de cartão
- [ ] Webhook `PAYMENT_CONFIRMED` reconcilia caso o charge inicial seja assíncrono
- [ ] Rate-limit ativo em `POST /auth/signup` (`throttle:public`, validado por teste de integração)
- [ ] PCI: nenhum log/payload do api ou gateway contém PAN ou CVV — só `card_token`, `brand`, `last4`

## Design

Artefatos em `.context/DESIGN/trial-signup-checkout-*.md` (criar na Fase 2 antes do Frontend):
- `trial-signup-checkout-trial-banner.md` — 3 estados da tarja (normal/alerta/expirado), tokens de cor
- `trial-signup-checkout-signup-page.md` — layout signup + variante Google-only
- `trial-signup-checkout-quick-upgrade-modal.md` — 2 steps, transições, error states (3DS, declined)
- `trial-signup-checkout-chat-blocked.md` — card "IA pausada" inline na conversa

Mockups ASCII validados no brainstorm — referência no PRD 0005.

## Tasks

Ver `.context/DOCS/TASKS/trial-signup-checkout-tasks.md` *(a gerar pela fase Tasks do `/prevec-decompose-plan`)*

## Dependências

- **Features:** FEAT-003 (message-based-billing) — necessária. Usa `tenant_message_usage`, `check-and-increment`, `BillingCycleCalculator`, fluxo de `overage_mode=stop`
- **Módulos existentes:**
  - `api/src/Domain/Billing/Services/BillingGatewayService.php` (criar `createPaymentWithToken`)
  - `api/src/Domain/Billing/Actions/BillingAsaasWebhookAction.php` (handler upgrade-from-trial)
  - `api/src/Domain/Billing/Actions/BillingChangePlanAction.php` (adicionar `bypass_password`)
  - `api/src/Domain/Platform/Actions/PlatformTenantBootstrapAction.php` (reuso no signup)
  - `app/src/app/pages/settings/my-plan/components/plan-card/` (reuso no quick-upgrade-modal)
  - `app/src/app/core/services/auth.service.ts` (adicionar `signup()` + `loginWithGoogle()`)
- **Externas:**
  - `laravel/socialite ^5.x` (composer)
  - `@asaas/checkout` JS SDK (npm — confirmar nome exato via Context7 antes de implementar)
  - Google OAuth credentials (Client ID/Secret) — `.env`: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
  - Asaas public key para SDK frontend — `.env`: `ASAAS_PUBLIC_KEY`

## Fases Estimadas

- [x] **Fase 1 — Planning** ✅ (brainstorm + exploração + este feature doc)
- [ ] **Fase 2 — Design** (4 artefatos em `.context/DESIGN/`)
- [ ] **Fase 3 — Backend** (migrations + seeder + socialite + actions signup/OAuth + upgrade-from-trial + jobs trial)
- [ ] **Fase 3.5 — Gateway** (endpoint `payment-methods` + integração tokenização Asaas)
- [ ] **Fase 4 — Frontend** (signup + Google callback + login button + trial banner + quick-upgrade-modal + asaas SDK service)
- [ ] **Fase 5 — Integration** (5 cenários manuais + gates + cobertura)

Estimativa total: ~11 dias úteis.

## Notas

**Decisões técnicas tomadas no planning:**
- Trial é um plano (`platform_plans.is_trial=true`) com `cycle_days=7`, não estado paralelo no tenant — reusa toda a maquinaria de FEAT-003
- `BillingCycleCalculator` generalizado: ciclo = `subscription_started_at` até `+plan.cycle_days`. Default 30 mantém comportamento atual
- Conversão trial → pago zera ciclo: cria novo `tenant_message_usage` com `cycle_start = now()` do plano novo
- Trial expirado é estado derivado em runtime (`cycle_end < now()`), não flag persistida — `CloseExpiredTrialJob` só serve para enviar email de "trial expirou"
- Self-signup é endpoint público novo (`POST /auth/signup`) com `throttle:public` (10/IP/min) — admin-only continua existindo em `POST /platform/tenants`
- Google OAuth via `laravel/socialite` — fluxo redirect server-side (não JS implicit). Callback no api retorna Sanctum token + redireciona para `app/`
- OAuth vincula por email se já existe (preenche `provider`/`provider_id`); cria tenant novo se email é novo
- `email_verified_at` é preenchido automaticamente quando signup via Google (provedor já confirmou)
- Checkout transparente: SDK Asaas tokeniza 100% no browser. PAN/CVV nunca passam pelo backend. PCI scope = SAQ-A
- Token Asaas salvo em `platform_tenants.payment_method_token` habilita cobrança recorrente mensal sem novo prompt de cartão
- 3DS modal é tratado pelo SDK Asaas; fallback para `payment_url` se SDK falhar
- LGPD: salvar `last4` + `brand` + token requer aceite de termos no modal — registrado em `audits`

**Riscos principais:**
- Asaas SDK JS API pode mudar — validar no início da Fase 3
- Self-signup vira vetor spam — mitigação reCAPTCHA fica para v1.5
- Multi-trial via email novo — aceito como custo de aquisição em v1
- 3DS obrigatório pode travar fluxo inline — testar com cartões teste do Asaas
