# FEAT-047 Mobile (Capacitor) — Decisões Arquiteturais

**Data:** 2026-04-26
**Contexto:** Refino do plano FEAT-047 após rodada REVIEWER + QA

---

## Decisão 1: Tenant isolation via middleware, NÃO via abilities do PersonalAccessToken

**Contexto:** Plano inicial sugeria armazenar `tenant_id` em `abilities` do Sanctum PersonalAccessToken para uso pelo mobile.

**Decisão:** Tenant continua sendo resolvido **exclusivamente** por `Domain\Shared\Http\Middleware\TenantContextMiddleware`, lendo `$user->tenant_id`. Token não carrega tenant_id.

**Justificativa:**
- Cria caminho único de verificação (sem risco de divergência)
- Evita bypass cross-tenant se attacker re-emitir token com abilities adulteradas
- TenantContextMiddleware já está no grupo `api` e cobre 100% das rotas
- Bearer Token + cookies usam o mesmo guard `auth:sanctum` → mesmo `$user` → mesma resolução

**Alternativas rejeitadas:**
- ~~Tenant em abilities~~: cria 2º caminho de verificação (anti-pattern)
- ~~Header customizado `X-Tenant-Id`~~: cliente não deve declarar tenant; sempre derivado do user

**Referência:** `api/bootstrap/app.php` (linha do middleware group `api`), `api/config/sanctum.php` (guard `['web']` real)

---

## Decisão 2: CORS estende `CORS_ALLOWED_ORIGINS` existente, sem nova env var

**Contexto:** Plano inicial criava `MOBILE_ALLOWED_ORIGINS`.

**Decisão:** Usar a env `CORS_ALLOWED_ORIGINS` já existente em `api/config/cors.php`. Adicionar array hardcoded `$mobileOrigins = ['capacitor://localhost', 'http://localhost']` aplicado em todos os envs.

**Justificativa:**
- `cors.php` real já tem 4 fontes de origins (`productionOrigins`, `developmentOrigins`, `envOrigins`, env-aware via match). Adicionar 5ª env var aumenta superfície de erro.
- Origins Capacitor são fixos (não mudam por ambiente) → hardcoded é mais seguro que via env

**Referência:** `api/config/cors.php` (estrutura `productionOrigins`/`developmentOrigins`/`envOrigins` + match APP_ENV)

---

## Decisão 3: Estrutura DDD do Auth — Actions/Http/Models, NÃO Application/Presentation/Infrastructure

**Contexto:** Plano inicial usava paths inventados (`Domain/Entities`, `Application/Actions`, `Presentation/Http/Controllers`, `Infrastructure/Database/Migrations`).

**Decisão:** Seguir layout REAL do módulo Auth: `api/src/Domain/Auth/{Actions,DTOs,Http,Models,Policies,Repositories,Routes,Services}/`. Migrations ficam em `api/database/migrations/` (raiz, padrão Laravel).

**Justificativa:**
- Layout real validado via `ls api/src/Domain/Auth/`
- Modelos seguem prefixo `Auth*` (`AuthUser`, `AuthRole`, `AuthPermission`, `AuthPersonalAccessToken`) → novo modelo deve ser `AuthDeviceToken`
- Paths inventados quebram autoload PSR-4 (`composer dump-autoload --strict-psr` falharia)

**Aplicado a:** TASK-047.3 (Bearer auth), TASK-047.10 (push notifications + device_tokens)

---

## Decisão 4: Migration `auth_device_tokens` com soft revoke + unique index

**Contexto:** REVIEWER apontou que migration sem unique constraint geraria push duplicado (rejeição Apple 4.5.4).

**Decisão:** Schema final inclui:
- UNIQUE INDEX (tenant_id, platform, token) — previne duplicação
- INDEX (user_id, revoked_at) — query "tokens ativos"
- `revoked_at` (soft revoke, nunca hard delete) — preserva histórico para auditoria
- método `down()` implementado — rollback funcional
- prefixo `auth_` na tabela seguindo convenção do módulo

**Justificativa:**
- APNs/FCM enviam falha em token revogado → backend re-marca `revoked_at` automaticamente sem perder histórico
- Soft revoke permite estatísticas (X tokens revogados/mês)

---

## Decisão 5: 4 novas tasks (24-27) cobrindo gaps QA

**Contexto:** QA apontou lacunas críticas (LC-1, LC-3, LC-6, LC-8) sem cobertura no plano original.

**Decisão:** Adicionar:
- **TASK-047.24 (Sentry):** viabiliza CA-016 (crash-free sessions ≥ 99%)
- **TASK-047.25 (Deep Links):** sem isso, tap em push abre app na home (UX quebrada, CA-017/018)
- **TASK-047.26 (WS Bearer test):** sem teste explícito, risco de bypass cross-tenant em websocket (CA-019)
- **TASK-047.27 (Release build R8):** sem isso, app pode crashar em produção mesmo passando em debug (CA-020)

**Cronograma:** Tasks rodam em paralelo às fases existentes; total continua 9 semanas otimista / 12 com folga.

---

## Lições

1. **Sempre validar paths/configs contra repo real antes de planejar.** REVIEWER pegou 5 CRITs porque o plano inventou estrutura DDD genérica.
2. **Nunca colocar lógica de autorização em token claims/abilities** se já existe middleware fazendo isso. Caminho duplo = risco de bypass.
3. **Migrations precisam: unique constraints semânticos + down() + soft revoke** quando o registro tem implicações externas (APNs, FCM, billing).
4. **QA agrega mais valor revisando o PLANO** (lacunas) que revisando código pronto (re-trabalho).
