# Tasks: Roles com UUID Fixo e Nomes Padronizados

> Decomposição T.A.C.E hierárquica (TASK-X.Y.Z) para FEAT-001.
> Feature doc: `../FEATURES/FEAT-001-roles-uuid-fixos.md`

---

## Estrutura Hierárquica

| Nível | Significado |
|-------|-------------|
| X | Fase: 1=Planning, 3=Backend (api/), 6=Integration |
| Y | Feature dentro da fase |
| Z | Etapa de codificação |

---

## FASE 1: PLANNING

### Features desta Fase

#### 1.1 — Documentação

- [x] **TASK-1.1.1**: Feature doc criada e aprovada

  **T — Tarefa:** Criar feature doc com escopo, riscos, mapa de arquivos e hierarquia de roles
  **A — Arquivo:** `.context/DOCS/FEATURES/FEAT-001-roles-uuid-fixos.md`
  **C — Comportamento:**
  - ANTES: feature não documentada
  - DEPOIS: doc com 4 roles, UUIDs fixos, unificação admin+inquilino, 14 critérios de aceite
  **E — Evidência:**
  - [x] Doc criado
  **Status:** Concluída

### Revisão de Fase 1 (REVIEWER)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Feature doc completa | REVIEWER | aguardando |
| Bounded contexts identificados | ARCHITECT | concluído |

---

## FASE 3: BACKEND (api/)

### 3.1 — Modelo AuthRole + Constantes (BACKEND)

- [ ] **TASK-3.1.1**: Refatorar AuthRole com novas constantes de UUID e nome

  **T — Tarefa:** Substituir constantes antigas por novas constantes `*_ID` e `*_NAME` para 4 roles, mantendo as antigas como `@deprecated`. Remover `ADMIN_ID`/`ADMIN_NAME` (unificado em Inquilino).

  **A — Arquivo:** `api/src/Domain/Auth/Models/AuthRole.php`

  **C — Comportamento:**
  - ANTES:
    ```php
    public const SUPER_ADMIN = 'super-admin';
    public const MANAGER = 'Gerente';
    public const AGENT = 'Atendente';
    public const SUPER_ADMIN_ID = '00000000-0000-4000-8000-000000000001';
    ```
  - DEPOIS:
    ```php
    public const ADMINISTRADOR_ID = '00000000-0000-4000-8000-000000000001';
    public const ADMINISTRADOR_NAME = 'Administrador';
    public const INQUILINO_ID = '00000000-0000-4000-8000-000000000003';
    public const INQUILINO_NAME = 'Inquilino';
    public const GERENTE_ID = '00000000-0000-4000-8000-000000000004';
    public const GERENTE_NAME = 'Gerente';
    public const ATENDENTE_ID = '00000000-0000-4000-8000-000000000005';
    public const ATENDENTE_NAME = 'Atendente';

    /** @deprecated Use ADMINISTRADOR_ID */
    public const SUPER_ADMIN = self::ADMINISTRADOR_NAME;
    /** @deprecated Use ADMINISTRADOR_ID */
    public const SUPER_ADMIN_ID = self::ADMINISTRADOR_ID;
    /** @deprecated Use GERENTE_NAME */
    public const MANAGER = self::GERENTE_NAME;
    /** @deprecated Use ATENDENTE_NAME */
    public const AGENT = self::ATENDENTE_NAME;
    ```

  **E — Evidência:**
  - [ ] PHPStan L6 limpo
  - [ ] Nenhuma referência a `ADMIN_ID` no código

  **Status:** Pendente
  **Agente:** BACKEND

### 3.2 — Modelo AuthUser + Helpers (BACKEND)

- [ ] **TASK-3.2.1**: Adicionar métodos helpers de verificação por UUID no AuthUser

  **T — Tarefa:** Adicionar `hasRoleId()`, refatorar `isSuperAdmin()` para usar UUID, adicionar `isInquilino()`, `isManager()`, `isAgent()`, `isAnyTenantAdmin()`.

  **A — Arquivo:** `api/src/Domain/Auth/Models/AuthUser.php`

  **C — Comportamento:**
  - ANTES:
    ```php
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(AuthRole::SUPER_ADMIN, $this->guard_name);
    }
    ```
  - DEPOIS:
    ```php
    public function hasRoleId(string $roleId): bool
    {
        return $this->roles()->where('id', $roleId)->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRoleId(AuthRole::ADMINISTRADOR_ID);
    }

    public function isInquilino(): bool
    {
        return $this->hasRoleId(AuthRole::INQUILINO_ID);
    }

    public function isManager(): bool
    {
        return $this->hasRoleId(AuthRole::GERENTE_ID);
    }

    public function isAgent(): bool
    {
        return $this->hasRoleId(AuthRole::ATENDENTE_ID);
    }

    public function isAnyTenantAdmin(): bool
    {
        return $this->isInquilino();
    }
    ```

  **E — Evidência:**
  - [ ] Teste unitário: `tests/Unit/Auth/AuthUserHelpersTest.php` com asserts para cada helper
  - [ ] PHPStan L6 limpo

  **Status:** Pendente
  **Agente:** BACKEND

### 3.3 — Migration de Unificação (DBA)

- [ ] **TASK-3.3.1**: Criar migration para unificar roles, renomear e atribuir UUIDs fixos

  **T — Tarefa:** Criar migration que: (1) cria role `Inquilino` se não existir, (2) renomeia `super-admin` → `Administrador`, `gerente` → `Gerente`, `atendente` → `Atendente`, (3) reatribui usuários de `admin` e `inquilino` para `Inquilino`, (4) atribui UUIDs fixos, (5) deleta roles `admin` e `inquilino` antigas, (6) limpa cache do Spatie Permission.

  **A — Arquivo:** `api/database/migrations/2026_05_05_000000_unify_roles_to_uuid.php` (criar)

  **C — Comportamento:**
  - ANTES: 5 roles com nomes inconsistentes (`super-admin`, `admin`, `inquilino`, `gerente`, `atendente`), UUIDs aleatórios
  - DEPOIS: 4 roles com nomes padronizados (`Administrador`, `Inquilino`, `Gerente`, `Atendente`), UUIDs fixos, todos os usuários migrados

  **E — Evidência:**
  - [ ] `php artisan migrate` ok em banco limpo
  - [ ] `php artisan migrate` ok em banco com dados existentes (idempotente)
  - [ ] `php artisan migrate:rollback` ok (down implementado)
  - [ ] Após migrate: `SELECT name, id FROM auth_roles` retorna 4 rows com UUIDs fixos
  - [ ] Após migrate: zero usuários com role `admin` ou `inquilino` (antigos)
  - [ ] `php artisan permission:cache-reset` executado na migration

  **Status:** Pendente
  **Agente:** DBA / BACKEND

### 3.4 — Actions — Proteção e Verificação por UUID (BACKEND)

- [ ] **TASK-3.4.1**: AuthRoleActions — proteção por ID em `delete()` e `list()`

  **T — Tarefa:** Substituir comparação por nome por comparação por ID. Adicionar constante `SYSTEM_ROLE_IDS`.

  **A — Arquivo:** `api/src/Domain/Auth/Actions/AuthRoleActions.php`

  **C — Comportamento:**
  - ANTES linha 36: `->where('name', '!=', AuthRole::SUPER_ADMIN)`
  - DEPOIS linha 36: `->whereNotIn('id', self::SYSTEM_ROLE_IDS)`
  - ANTES linha 93: `in_array($resolvedRole->name, [AuthRole::SUPER_ADMIN, 'admin'], true)`
  - DEPOIS linha 93: `in_array($resolvedRole->id, self::SYSTEM_ROLE_IDS, true)`
  - Adicionar: `private const SYSTEM_ROLE_IDS = [AuthRole::ADMINISTRADOR_ID, AuthRole::INQUILINO_ID, AuthRole::GERENTE_ID, AuthRole::ATENDENTE_ID];`

  **E — Evidência:**
  - [ ] `tests/Unit/Auth/AuthRoleActionsTest.php` — teste de delete protegido passa
  - [ ] Tentar deletar `Inquilino` via API retorna 403

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.4.2**: AuthUserActions — `in_array` por UUID

  **T — Tarefa:** Substituir `AuthRole::SUPER_ADMIN` por `AuthRole::ADMINISTRADOR_ID` na verificação de atribuição de role.

  **A — Arquivo:** `api/src/Domain/Auth/Actions/AuthUserActions.php`

  **C — Comportamento:**
  - ANTES linha 166: `in_array(AuthRole::SUPER_ADMIN, $rolesToAssign, true)`
  - DEPOIS linha 166: `in_array(AuthRole::ADMINISTRADOR_ID, $rolesToAssign, true)`

  **E — Evidência:**
  - [ ] `tests/Unit/Auth/AuthUserActionsTest.php` passa
  - [ ] Tentar atribuir `Administrador` a usuário não-super-admin retorna 403

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.4.3**: PlatformTenantImpersonateAction — busca por UUID em vez de `LOWER()`

  **T — Tarefa:** Substituir `whereRaw('LOWER(name) = LOWER(?)', [AuthRole::MANAGER])` por `where('id', AuthRole::GERENTE_ID)`.

  **A — Arquivo:** `api/src/Domain/Platform/Actions/PlatformTenantImpersonateAction.php`

  **C — Comportamento:**
  - ANTES linha 116: `->whereRaw('LOWER(name) = LOWER(?)', [AuthRole::MANAGER])`
  - DEPOIS linha 116: `->where('id', AuthRole::GERENTE_ID)`

  **E — Evidência:**
  - [ ] `tests/Feature/Platform/PlatformTenantImpersonateTest.php` passa
  - [ ] Impersonação funciona sem `LOWER()`

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.4.4**: Billing Actions — proteção por UUID

  **T — Tarefa:** Substituir `where('name', AuthRole::SUPER_ADMIN)` por `where('id', AuthRole::ADMINISTRADOR_ID)` em BillingDowngradeEnforcementAction e BillingPlanChangePreviewAction.

  **A — Arquivo:** `api/src/Domain/Billing/Actions/BillingDowngradeEnforcementAction.php` (linha 56)
  **A — Arquivo:** `api/src/Domain/Billing/Actions/BillingPlanChangePreviewAction.php` (linha 123)

  **C — Comportamento:**
  - ANTES: `->where('name', AuthRole::SUPER_ADMIN)`
  - DEPOIS: `->where('id', AuthRole::ADMINISTRADOR_ID)`

  **E — Evidência:**
  - [ ] `tests/Feature/BillingOwnerProtectionTest.php` passa
  - [ ] `tests/Feature/BillingChangePlanTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

### 3.5 — Policies — Verificação por UUID (BACKEND)

- [ ] **TASK-3.5.1**: AuthRolePolicy — comparação por ID

  **T — Tarefa:** Substituir comparação por nome por comparação por ID.

  **A — Arquivo:** `api/src/Domain/Auth/Policies/AuthRolePolicy.php`

  **C — Comportamento:**
  - ANTES linha 39: `$role->name === AuthRole::SUPER_ADMIN`
  - DEPOIS linha 39: `$role->id === AuthRole::ADMINISTRADOR_ID`
  - ANTES linha 48: `$user->hasRole(AuthRole::SUPER_ADMIN, self::GUARD)`
  - DEPOIS linha 48: `$user->hasRoleId(AuthRole::ADMINISTRADOR_ID)`

  **E — Evidência:**
  - [ ] `tests/Feature/AuthSuperAdminProtectionTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.5.2**: AuthUserPolicy — `hasRole('admin')` → `isInquilino()`

  **T — Tarefa:** Substituir `hasRole('admin', 'sanctum')` por `$user->isInquilino()`.

  **A — Arquivo:** `api/src/Domain/Auth/Policies/AuthUserPolicy.php`

  **C — Comportamento:**
  - ANTES linha 108: `$user->hasRole('admin', 'sanctum')`
  - DEPOIS linha 108: `$user->isInquilino()`

  **E — Evidência:**
  - [ ] `tests/Feature/AuthUserControllerTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.5.3**: PlatformBillingInvoicePolicy — `hasRole('admin')` → `isInquilino()`

  **T — Tarefa:** Substituir 3 ocorrências de `hasRole('admin', self::GUARD)` por `isInquilino()`.

  **A — Arquivo:** `api/src/Domain/Platform/Policies/PlatformBillingInvoicePolicy.php`

  **C — Comportamento:**
  - ANTES linhas 21, 29, 37: `$user->hasRole('admin', self::GUARD)`
  - DEPOIS: `$user->isInquilino()`

  **E — Evidência:**
  - [ ] `tests/Feature/Platform/PlatformBillingInvoiceControllerTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.5.4**: PlatformPlanPolicy — `hasRole('admin')` → `isInquilino()`

  **T — Tarefa:** Substituir `hasRole('admin', self::GUARD)` por `isInquilino()`.

  **A — Arquivo:** `api/src/Domain/Platform/Policies/PlatformPlanPolicy.php`

  **C — Comportamento:**
  - ANTES linha 47: `$user->hasRole('admin', self::GUARD)`
  - DEPOIS linha 47: `$user->isInquilino()`

  **E — Evidência:**
  - [ ] `tests/Unit/Platform/PlatformPlanPolicyTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.5.5**: BillingSubscriptionPolicy — `hasRole('admin')` → `isInquilino()`

  **T — Tarefa:** Substituir `hasRole('admin', 'sanctum')` por `isInquilino()`.

  **A — Arquivo:** `api/src/Domain/Billing/Policies/BillingSubscriptionPolicy.php`

  **C — Comportamento:**
  - ANTES linha 20: `$user->hasRole('admin', 'sanctum')`
  - DEPOIS linha 20: `$user->isInquilino()`

  **E — Evidência:**
  - [ ] `tests/Feature/BillingSubscriptionTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

### 3.6 — Services (BACKEND)

- [ ] **TASK-3.6.1**: PlatformPlanEnforcementService — `isAdmin()` → `isInquilino()`

  **T — Tarefa:** Substituir `hasRole('admin', $user->guard_name)` por `isInquilino()`.

  **A — Arquivo:** `api/src/Domain/Platform/Services/PlatformPlanEnforcementService.php`

  **C — Comportamento:**
  - ANTES linha 269: `return $user->hasRole('admin', $user->guard_name);`
  - DEPOIS linha 269: `return $user->isInquilino();`

  **E — Evidência:**
  - [ ] `tests/Unit/Platform/PlatformPlanEnforcementServiceTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

### 3.7 — Controllers (BACKEND)

- [ ] **TASK-3.7.1**: BillingSubscriptionController — `hasRole('admin')` → `isInquilino()`

  **T — Tarefa:** Substituir `hasRole('admin', 'sanctum')` por `isInquilino()`.

  **A — Arquivo:** `api/src/Domain/Billing/Http/Controllers/BillingSubscriptionController.php`

  **C — Comportamento:**
  - ANTES linha 229: `$user->hasRole('admin', 'sanctum')`
  - DEPOIS linha 229: `$user->isInquilino()`

  **E — Evidência:**
  - [ ] `tests/Feature/BillingSubscriptionTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

### 3.8 — Form Requests (BACKEND)

- [ ] **TASK-3.8.1**: Billing Requests — `hasRole('admin')` → `isInquilino()`

  **T — Tarefa:** Substituir em 4 arquivos de request.

  **A — Arquivo:** `api/src/Domain/Billing/Http/Requests/BillingPlanChangePreviewRequest.php` (linha 33)
  **A — Arquivo:** `api/src/Domain/Billing/Http/Requests/BillingChangePlanRequest.php` (linha 33)
  **A — Arquivo:** `api/src/Domain/Platform/Http/Requests/PlatformBillingInvoiceIndexRequest.php` (linha 27)
  **A — Arquivo:** `api/src/Domain/Platform/Http/Requests/PlatformBillingInvoiceStoreRequest.php` (linha 25)

  **C — Comportamento:**
  - ANTES: `$user->hasRole('admin', 'sanctum')`
  - DEPOIS: `$user->isInquilino()`

  **E — Evidência:**
  - [ ] `tests/Feature/BillingChangePlanTest.php` passa
  - [ ] `tests/Feature/Platform/PlatformBillingInvoiceControllerTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.8.2**: Auth User Requests — `in_array` por UUID

  **T — Tarefa:** Substituir `AuthRole::SUPER_ADMIN` por `AuthRole::ADMINISTRADOR_ID` em verificações de segurança.

  **A — Arquivo:** `api/src/Domain/Auth/Http/Requests/AuthUserStoreRequest.php` (linha 59)
  **A — Arquivo:** `api/src/Domain/Auth/Http/Requests/AuthUserUpdateRequest.php` (linha 82)

  **C — Comportamento:**
  - ANTES: `in_array(AuthRole::SUPER_ADMIN, $rolesToAssign, true)`
  - DEPOIS: `in_array(AuthRole::ADMINISTRADOR_ID, $rolesToAssign, true)`

  **E — Evidência:**
  - [ ] `tests/Feature/AuthUserControllerTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

### 3.9 — Jobs e Listeners (BACKEND)

- [ ] **TASK-3.9.1**: AiPromptGuardianJob — `whereIn` por UUID

  **T — Tarefa:** Substituir `whereIn('name', [AuthRole::SUPER_ADMIN, 'admin'])` por `whereIn('id', [AuthRole::ADMINISTRADOR_ID])`.

  **A — Arquivo:** `api/src/Domain/Ai/Jobs/AiPromptGuardianJob.php`

  **C — Comportamento:**
  - ANTES linha 111: `->whereIn('name', [AuthRole::SUPER_ADMIN, 'admin'])`
  - DEPOIS linha 111: `->whereIn('id', [AuthRole::ADMINISTRADOR_ID])`

  **E — Evidência:**
  - [ ] `tests/Feature/Domain/Ai/Http/Controllers/AiPromptQuarantineControllerTest.php` passa

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.9.2**: AiBudgetThresholdNotificationListener — corrigir bug `owner` + UUID

  **T — Tarefa:** Corrigir bug silencioso (`owner` nunca existiu como role) e usar UUIDs.

  **A — Arquivo:** `api/src/Domain/Ai/Listeners/AiBudgetThresholdNotificationListener.php`

  **C — Comportamento:**
  - ANTES linha 31: `->whereIn('name', ['admin', 'owner'])`
  - DEPOIS linha 31: `->whereIn('id', [AuthRole::INQUILINO_ID])`
  - ANTES linha 36: `->where('name', AuthRole::SUPER_ADMIN)`
  - DEPOIS linha 36: `->where('id', AuthRole::ADMINISTRADOR_ID)`

  **E — Evidência:**
  - [ ] Notificação de budget é enviada para usuários com role `Inquilino`
  - [ ] Notificação de budget é enviada para usuários com role `Administrador`

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.9.3**: EvaluationNotificationListener — corrigir bug `manager` + UUID

  **T — Tarefa:** Corrigir bug de case (`'manager'` minúsculo vs `'Gerente'`) e usar UUID.

  **A — Arquivo:** `api/src/Domain/Configuration/Listeners/EvaluationNotificationListener.php`

  **C — Comportamento:**
  - ANTES linha 30: `->whereIn('name', ['manager', AuthRole::SUPER_ADMIN])`
  - DEPOIS linha 30: `->whereIn('id', [AuthRole::GERENTE_ID, AuthRole::ADMINISTRADOR_ID])`

  **E — Evidência:**
  - [ ] `tests/Feature/ChatTicketEvaluationTest.php` passa
  - [ ] Notificação de avaliação baixa é enviada para Gerentes

  **Status:** Pendente
  **Agente:** BACKEND

### 3.10 — App Service Provider (BACKEND)

- [ ] **TASK-3.10.1**: Gate global — `hasRole('admin')` → `isInquilino()`

  **T — Tarefa:** Substituir verificação de role no gate global.

  **A — Arquivo:** `api/app/Providers/AppServiceProvider.php`

  **C — Comportamento:**
  - ANTES linha 120: `$user->hasRole('admin')`
  - DEPOIS linha 120: `$user->isInquilino()`

  **E — Evidência:**
  - [ ] `tests/Feature/Security/AuthorizationSecurityTest.php` passa
  - [ ] Usuário com role `Inquilino` tem gate global concedido

  **Status:** Pendente
  **Agente:** BACKEND

### 3.11 — Seeders (BACKEND)

- [ ] **TASK-3.11.1**: DatabaseSeeder — 4 roles com UUIDs fixos

  **T — Tarefa:** Refatorar para criar 4 roles com UUIDs fixos e nomes maiúsculos. Remover `admin`.

  **A — Arquivo:** `api/database/seeders/DatabaseSeeder.php`

  **C — Comportamento:**
  - ANTES: Cria `super-admin`, `inquilino`, `gerente`, `atendente` com strings
  - DEPOIS: Cria `Administrador`, `Inquilino`, `Gerente`, `Atendente` com UUIDs fixos via `firstOrCreate(['id' => UUID])`

  **E — Evidência:**
  - [ ] `php artisan db:seed` roda sem erro
  - [ ] `SELECT name, id FROM auth_roles` retorna 4 rows com UUIDs fixos

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.11.2**: RolePermissionSeeder — lookup por UUID

  **T — Tarefa:** Substituir `where('name', '...')` por `where('id', AuthRole::*_ID)`.

  **A — Arquivo:** `api/database/seeders/RolePermissionSeeder.php`

  **C — Comportamento:**
  - ANTES linhas 20-22: `->where('name', 'inquilino')`, `->where('name', 'gerente')`, `->where('name', 'atendente')`
  - DEPOIS: `->where('id', AuthRole::INQUILINO_ID)`, `->where('id', AuthRole::GERENTE_ID)`, `->where('id', AuthRole::ATENDENTE_ID)`

  **E — Evidência:**
  - [ ] `php artisan db:seed --class=RolePermissionSeeder` roda sem erro
  - [ ] Permissões atribuídas corretamente

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.11.3**: AuthExtraUsersSeeder + DemoDataSeeder + TestCompaniesSeeder — constantes de ID

  **T — Tarefa:** Atualizar 3 seeders para usar constantes de UUID em vez de strings.

  **A — Arquivo:** `api/database/seeders/AuthExtraUsersSeeder.php`
  **A — Arquivo:** `api/database/seeders/DemoDataSeeder.php`
  **A — Arquivo:** `api/database/seeders/TestCompaniesSeeder.php`

  **C — Comportamento:**
  - ANTES: strings `'gerente'`, `'atendente'`, `'admin'`, `AuthRole::MANAGER`
  - DEPOIS: `AuthRole::GERENTE_ID`, `AuthRole::ATENDENTE_ID`, `AuthRole::INQUILINO_ID`, `AuthRole::GERENTE_NAME`

  **E — Evidência:**
  - [ ] Todos os seeders rodam sem erro
  - [ ] Zero referências a `'admin'` como nome de role nos seeders

  **Status:** Pendente
  **Agente:** BACKEND

### 3.12 — Testes (BACKEND)

- [ ] **TASK-3.12.1**: Atualizar testes de Feature — Auth e Security

  **T — Tarefa:** Atualizar 10 arquivos de teste para usar UUIDs fixos e helpers.

  **A — Arquivos:**
  1. `api/tests/Feature/AuthRbacTest.php`
  2. `api/tests/Feature/AuthRoleControllerTest.php`
  3. `api/tests/Feature/AuthUserControllerTest.php`
  4. `api/tests/Feature/AuthSuperAdminProtectionTest.php`
  5. `api/tests/Feature/Security/TenantIsolationTest.php`
  6. `api/tests/Feature/Security/AuthorizationSecurityTest.php`
  7. `api/tests/Feature/RbacChatControllerTest.php`
  8. `api/tests/Feature/ChatTicketEvaluationTest.php`
  9. `api/tests/Feature/BillingSubscriptionTest.php`
  10. `api/tests/Feature/BillingChangePlanTest.php`

  **C — Comportamento:**
  - ANTES: `AuthRole::create(['name' => 'admin', 'guard_name' => 'sanctum'])`
  - DEPOIS: `AuthRole::firstOrCreate(['id' => AuthRole::INQUILINO_ID], ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum'])`
  - ANTES: `$user->hasRole('admin')`
  - DEPOIS: `$user->isInquilino()`

  **E — Evidência:**
  - [ ] `pest tests/Feature/Auth*` — todos passam
  - [ ] `pest tests/Feature/Security/*` — todos passam
  - [ ] `pest tests/Feature/Billing*` — todos passam

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.12.2**: Atualizar testes de Feature — Platform, Billing, Chat

  **T — Tarefa:** Atualizar 10 arquivos de teste para usar UUIDs fixos e helpers.

  **A — Arquivos:**
  1. `api/tests/Feature/Platform/PlatformUserImpersonateTest.php`
  2. `api/tests/Feature/Platform/PlatformTenantImpersonateTest.php`
  3. `api/tests/Feature/Platform/PlatformTenantDetailsTest.php`
  4. `api/tests/Feature/Platform/PlatformBillingInvoiceControllerTest.php`
  5. `api/tests/Feature/Platform/PlatformPlanControllerTest.php`
  6. `api/tests/Feature/Platform/PlatformPlanEnforcementTest.php`
  7. `api/tests/Feature/Platform/PlatformTenantPurgeTest.php` *(adicionado na revisão)*
  8. `api/tests/Feature/BillingOwnerProtectionTest.php`
  9. `api/tests/Feature/Billing/BillingInvoiceListTest.php`
  10. `api/tests/Feature/Chat/CloseInactiveTicketsTest.php`
  11. `api/tests/Feature/Chat/ChatRoutingQueueControllerTest.php`
  12. `api/tests/Feature/ReproduceTenantCreationTest.php` *(adicionado na revisão)*

  **C — Comportamento:** Mesmo padrão do TASK-3.12.1

  **E — Evidência:**
  - [ ] `pest tests/Feature/Platform/*` — todos passam
  - [ ] `pest tests/Feature/Chat/*` — todos passam

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.12.3**: Atualizar testes de Unit

  **T — Tarefa:** Atualizar 6 arquivos de teste unitário para usar UUIDs fixos e helpers.

  **A — Arquivos:**
  1. `api/tests/Unit/Auth/AuthUserActionsTest.php`
  2. `api/tests/Unit/Auth/AuthRoleActionsTest.php`
  3. `api/tests/Unit/Platform/PlatformPlanEnforcementServiceTest.php`
  4. `api/tests/Unit/Platform/PlatformPlanPolicyTest.php`
  5. `api/tests/Unit/Reports/ReportsPolicyTest.php`
  6. `api/tests/Unit/Domain/Ai/Policies/AiPromptPolicyTest.php`
  7. `api/tests/Unit/Platform/Actions/PlatformTenantHardDeleteActionTest.php` *(adicionado na revisão)*

  **C — Comportamento:** Mesmo padrão do TASK-3.12.1

  **E — Evidência:**
  - [ ] `pest tests/Unit/*` — todos passam

  **Status:** Pendente
  **Agente:** BACKEND

- [ ] **TASK-3.12.4**: Atualizar testes de Feature — AI Controllers

  **T — Tarefa:** Atualizar 5 arquivos de teste de AI controllers para usar UUIDs fixos.

  **A — Arquivos:**
  1. `api/tests/Feature/Domain/Ai/Http/Controllers/AiPromptPlanControllerTest.php`
  2. `api/tests/Feature/Domain/Ai/Http/Controllers/AiPromptMasterControllerTest.php`
  3. `api/tests/Feature/Domain/Ai/Http/Controllers/AiPromptSegmentControllerTest.php`
  4. `api/tests/Feature/Domain/Ai/Http/Controllers/AiPromptQuarantineControllerTest.php`
  5. `api/tests/Feature/Domain/Ai/Http/Controllers/AiTenantSegmentControllerTest.php`

  **C — Comportamento:** Mesmo padrão do TASK-3.12.1

  **E — Evidência:**
  - [ ] `pest tests/Feature/Domain/Ai/*` — todos passam

  **Status:** Pendente
  **Agente:** BACKEND

### Revisão de Fase 3 (REVIEWER + QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Migrations reviewed | DBA | aguardando |
| Domain layer puro PHP | REVIEWER | aguardando |
| `BelongsToTenant` aplicado | REVIEWER | N/A (sem novos models) |
| `composer gate:all` passa | QA | aguardando |
| Coverage >= 80% | QA | aguardando |
| Zero `hasRole('admin')` no código | REVIEWER | aguardando |
| Zero `where('name', 'admin')` no código | REVIEWER | aguardando |

---

## FASE 6: INTEGRATION

### 6.1 — Validação Final

- [ ] **TASK-6.1.1**: Gate completo — `composer gate:all`

  **T — Tarefa:** Executar pipeline completo de qualidade.

  **A — Arquivo:** `api/` (workspace)

  **C — Comportamento:**
  - ANTES: código com comparações por string nome
  - DEPOIS: código com comparações por UUID fixo, todos os gates passando

  **E — Evidência:**
  - [ ] `cd api && composer gate:all` — zero erros
  - [ ] `pest` — zero falhas, zero skipped
  - [ ] `phpstan analyse` — nível 6 limpo
  - [ ] `pint` — zero formatação pendente

  **Status:** Pendente
  **Agente:** QA

- [ ] **TASK-6.1.2**: Validação de critérios de aceite

  **T — Tarefa:** Verificar todos os 14 critérios de aceite da feature doc.

  **A — Arquivo:** `.context/DOCS/FEATURES/FEAT-001-roles-uuid-fixos.md`

  **C — Comportamento:**
  - CA-1 a CA-14 verificados e marcados como atendidos

  **E — Evidência:**
  - [ ] Todos os CA marcados na feature doc
  - [ ] `rg "hasRole\('admin'" api/src` — zero resultados
  - [ ] `rg "where\('name', 'admin'" api/src` — zero resultados
  - [ ] `rg "where\('name', 'inquilino'" api/src` — zero resultados

  **Status:** Pendente
  **Agente:** QA / PM

### Revisão de Fase 6 (PM)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Critérios de aceite atendidos | PM | aguardando |
| CHANGELOG atualizado | DOC | aguardando |
| MEMORY (se aplicável) | DOC | aguardando |
| project-state.yaml atualizado | DOC | aguardando |
