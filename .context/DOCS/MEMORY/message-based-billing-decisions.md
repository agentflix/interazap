# Code Review — Feature: message-based-billing

**Data:** 2026-05-24
**Revisor:** REVIEWER (code-review-confiavel)
**Feature:** message-based-billing (FEAT-003)
**Escopo:** api/, gateway/, app/

---

## Achados

### Crítico

**1. Bypass de tenant isolation em `BillingUsageController::check()`**
- **Arquivo:** `api/src/Domain/Billing/Http/Controllers/BillingUsageController.php`
- **Linha:** 35-41
- **Evidência:** O método aceita `tenant_id` do request body e passa diretamente para `UsageCounterService::checkAndIncrement()`. Não há verificação se o `tenant_id` pertence ao usuário autenticado (`auth:sanctum`).
- **Impacto:** Qualquer usuário autenticado pode chamar `POST /billing/usage/check-and-increment` com qualquer `tenant_id`, potencialmente incrementando contadores de outros tenants ou bloqueando mensagens de terceiros.
- **Correção sugerida:** Usar `$this->tenantId($request)` (do `BaseController`) ou adicionar validação explícita: `$request->validated('tenant_id') === $this->tenantId($request)`.
- **Confiança:** 95%

### Alto

**2. Autorização aberta em `BillingUsageCheckRequest`**
- **Arquivo:** `api/src/Domain/Billing/Http/Requests/BillingUsageCheckRequest.php`
- **Linha:** 14-17
- **Evidência:** `authorize()` retorna `true` incondicionalmente.
- **Impacto:** Mesmo com autenticação, não há verificação de permissão. O endpoint `check-and-increment` é chamado pelo Gateway (serviço interno) e deveria usar `InternalApiKeyGuard` ou similar, não `auth:sanctum` de usuário final.
- **Correção sugerida:** Adicionar middleware de API key interna (`X-Service-Token`) ou restringir a super-admins/tenants autenticados com validação de ownership.
- **Confiança:** 90%

### Médio

**3. Falhas silenciosas em `ReconcileFailedUsageJob`**
- **Arquivo:** `api/src/Domain/Billing/Jobs/ReconcileFailedUsageJob.php`
- **Linha:** 37-39
- **Evidência:** `catch (\Throwable)` sem logar o erro.
- **Impacto:** Falhas de reconciliação passam despercebidas. Logs perdidos dificultam debugging e monitoramento.
- **Correção sugerida:** Adicionar `Log::warning('[ReconcileFailedUsageJob] Reconciliation failed', [...])` dentro do catch.
- **Confiança:** 85%

**4. Race condition em `CheckUsageThresholdsJob`**
- **Arquivo:** `api/src/Domain/Billing/Jobs/CheckUsageThresholdsJob.php`
- **Linha:** 35-38
- **Evidência:** O job lê `TenantMessageUsage` sem `lockForUpdate()`, enquanto `UsageCounterService::checkAndIncrement()` usa `lockForUpdate()` na mesma tabela.
- **Impacto:** Job pode ler valor desatualado do `message_count` e enviar alerta incorreto (ex: alerta de 80% quando já passou de 100%).
- **Correção sugerida:** Adicionar `->lockForUpdate()` na query do job.
- **Confiança:** 80%

**5. Anchor day hardcoded no dispatch do job**
- **Arquivo:** `api/src/Domain/Billing/Http/Controllers/BillingUsageController.php`
- **Linha:** 45
- **Evidência:** `$cycle = $this->cycleCalculator->calculate(1, $now);` — o anchorDay está hardcoded como 1, ignorando `billing_cycle_anchor_day` do tenant.
- **Impacto:** Se o tenant tiver anchor day diferente de 1, o job `CheckUsageThresholdsJob` recebe cycle_start incorreto e pode não encontrar o registro de uso.
- **Correção sugerida:** Carregar o tenant e usar `$tenant->billing_cycle_anchor_day ?? 1`.
- **Confiança:** 90%

**6. Rector violations em testes (gate:refactor falhou)**
- **Arquivo:** `api/tests/Feature/Domain/Billing/Scenarios/CycleResetScenarioTest.php:47` e `MidCyclePlanChangeScenarioTest.php:51`
- **Evidência:** Gate `composer gate:all` falhou no passo `refactor` com regra `EloquentMagicMethodToQueryBuilderRector`.
- **Impacto:** CI/CD bloqueado se gate:all for exigido. Não é bug funcional, mas viola regras do projeto.
- **Correção sugerida:** Substituir `TenantMessageUsage::create([...])` por `TenantMessageUsage::query()->create([...])` nos 2 arquivos.
- **Confiança:** 100%

### Baixo

**7. `CalculateAiOverageAction` retorna dados hardcoded obsoletos**
- **Arquivo:** `api/src/Domain/Billing/Actions/CalculateAiOverageAction.php`
- **Linha:** 33-43
- **Evidência:** O método retorna valores nulos/falsos hardcoded após remover a lógica de token-based overage, mas ainda é consumido por `BillingGenerateMonthlyInvoicesCommand`.
- **Impacto:** Comando legado que gera faturas pode reportar dados incorretos de overage de tokens (zero). O overage real agora é tratado por `CloseExpiredCyclesJob`.
- **Correção sugerida:** Adicionar comentário de deprecação ou refatorar o command para não depender mais deste Action.
- **Confiança:** 75%

**8. Models de billing sem `BelongsToTenant`**
- **Arquivo:** `api/src/Domain/Billing/Models/TenantMessageUsage.php` e `AiMessageUsageFailedLog.php`
- **Evidência:** Nenhum dos models usa o trait `BelongsToTenant`.
- **Impacto:** Baixo — essas tabelas são de controle de billing e já filtram por `tenant_id` nas queries. Mas inconsistente com padrão do projeto.
- **Correção sugerida:** Documentar decisão no código ou adicionar o trait se aplicável.
- **Confiança:** 70%

**9. Timeout de billing usando config de auth**
- **Arquivo:** `gateway/src/domains/billing/services/billing-usage-client.service.ts`
- **Linha:** 43
- **Evidência:** `this.timeoutMs = this.configService.get<number>('api.authTimeoutMs') ?? 1500;`
- **Impacto:** Baixo — timeout correto, mas nome da config é confuso. Deveria ser `api.billingTimeoutMs`.
- **Correção sugerida:** Adicionar config específica no `configuration.ts`.
- **Confiança:** 80%

---

## Gates

### API
```bash
cd api && /opt/homebrew/bin/composer gate:all
```
- **Resultado:** 2930 passed, 1 skipped
- **Problema:** Gate `refactor` falhou — Rector sugere mudanças em 2 arquivos de teste
- **Risco:** CI/CD bloqueado até correção do estilo

### Gateway
```bash
cd gateway && pnpm test
```
- **Resultado:** 1366 passed, 32 failed
- **Observação:** 32 falhas são e2e pré-existentes (falta `WEBCHAT_JWT_SECRET` env var)
- **Nossos testes:** 17/17 passaram (BillingUsageClient + BillingUsageMetrics)

### App
```bash
cd app && pnpm test
```
- **Resultado:** 1195 passed, 2 failed
- **Observação:** 2 falhas pré-existentes em `webchat-page.component.spec.ts`
- **Nossos testes:** 100% passaram (plan-card, usage-stats, my-plan, billing-prefs-modal)

---

## Second Pass

Áreas verificadas e consideradas limpas:

- **Migrations:** Todas usam `if (!Schema::hasColumn(...))` guards, rollback/down implementado, índices adequados, foreign keys com `cascadeOnDelete`.
- **Models:** `TenantMessageUsage` e `AiMessageUsageFailedLog` usam UUIDs com `Str::orderedUuid()`, `$fillable` explícito, casts corretos.
- **BillingCycleCalculator:** Lógica correta, capping em 28 para evitar problemas de fevereiro, testes unitários cobrem edge cases (year boundary, anchor day itself).
- **UsageCounterService:** Usa `DB::transaction` + `lockForUpdate()`, idempotência via `firstOrCreate`, idempotência para turnos já reconciliados.
- **Gateway fail-open:** Retry exponencial implementado (200ms → 1s → 5s), fallback `allowed=true` com log de falha, métricas Prometheus.
- **Frontend:** Componentes seguem padrão OnPush, signals, tratamento de erro, testes com mocks.
- **Multi-tenancy em queries:** `TenantMessageUsage::where('tenant_id', ...)` presente em todos os pontos de acesso.
- **Emails:** Templates `usage-alert-80.blade.php` e `usage-alert-100.blade.php` verificados — estrutura básica OK.
- **Console routes:** Jobs schedulados em `api/routes/console.php` (CloseExpiredCyclesJob diário, ReconcileFailedUsageJob a cada 6h).

---

## Perguntas

1. **O endpoint `check-and-increment` deveria ser acessível apenas pelo Gateway (serviço interno) ou também por usuários autenticados?** Se for interno, devemos trocar `auth:sanctum` para `InternalApiKeyGuard`.
2. **Qual o comportamento esperado quando `billing_cycle_anchor_day` é null?** O código assume `?? 1` — isso está documentado?
3. **O `CalculateAiOverageAction` ainda é necessário?** Se o overage agora é tratado por ciclo (mensagem), a Action legada pode ser removida ou marcada como `@deprecated`.

---

## Resumo

A feature está **funcionalmente sólida** com boa cobertura de testes (cenários, unidade, feature) e implementa corretamente o padrão de fail-open no gateway. Os principais riscos são:

1. **Segurança (Crítico):** Bypass de tenant isolation no endpoint `check-and-increment`
2. **Correção (Alto):** Autorização inadequada no mesmo endpoint
3. **Robustez (Médio):** Race condition no threshold job, falhas silenciosas na reconciliação, anchor day hardcoded

**Recomendação:** Não aprovar para merge até corrigir os achados Crítico e Alto. Os achados Médio podem ser tratados em follow-up se o time aceitar o risco.

**Próximo comando sugerido:**
```bash
# Corrigir o bypass de tenant isolation no BillingUsageController:
cd api && code src/Domain/Billing/Http/Controllers/BillingUsageController.php
```
