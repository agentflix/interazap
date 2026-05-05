# Feature: Roles com UUID Fixo e Nomes Padronizados

## Metadados

| Campo | Valor |
|-------|-------|
| ID | FEAT-001 |
| Bounded Context | Auth, Platform, Billing, Ai, Configuration |
| Workspaces afetados | api |
| Complexidade | G |
| Status | Em Review |
| Autor (PM) | ARCHITECT |
| Aberta em | 2026-05-05 |
| Fechada em | |

---

## Resumo

Migração do sistema de verificação de perfis de acesso (roles) de comparação por **string nome** para comparação por **UUID fixo**. As roles `admin` e `inquilino` são unificadas em `Inquilino` (tenant owner), eliminando duplicação acidental. As 4 roles restantes recebem IDs imutáveis e não podem mais ser removidas. Os nomes são padronizados com primeira letra maiúscula exclusivamente para exibição. Isso elimina bugs de case-sensitivity, torna o sistema resiliente a renomeações e protege roles críticas contra exclusão acidental.

---

## Objetivo de Negócio

- **Eliminar duplicação de roles**: `admin` e `inquilino` representam a mesma entidade (tenant owner) mas com nomes diferentes — `inquilino` tem permissões no seeder mas nunca é verificado no código; `admin` é verificado em 14+ pontos mas não tem permissões no seeder
- **Eliminar inconsistência de case**: `AuthRole::MANAGER = 'Gerente'` mas o banco armazena `'gerente'`, exigindo `LOWER()` como workaround na impersonação
- **Proteger roles do sistema**: nenhuma das 4 roles fundamentais pode ser excluída, nem por erro de código nem por ação administrativa
- **Resiliência a renomeações**: se o nome de uma role mudar no futuro, a lógica de autorização não quebra porque compara por UUID
- **Padronização visual**: todos os nomes de roles com primeira letra maiúscula para consistência na UI

---

## Hierarquia de Roles

```
┌──────────────────────────────────────────────────────────────────┐
│ ADMINISTRADOR (platform-level)                                   │
│ UUID: 00000000-0000-4000-8000-000000000001                       │
│ Escopo: TODOS os tenants — SÓ conta interazap.com.br             │
│ Acesso: Plataforma inteira (billing, planos, tenants, impersonar) │
│ Exibição: Exclusivo para admin@interazap.com.br                  │
├──────────────────────────────────────────────────────────────────┤
│ INQUILINO (tenant-level owner) ← unifica admin + inquilino       │
│ UUID: 00000000-0000-4000-8000-000000000003                       │
│ Escopo: APENAS seu próprio tenant                                │
│ Acesso: Tudo dentro do tenant (50+ permissões)                   │
│ Exibição: Clientes SaaS — dono/admin da sua conta                │
├──────────────────────────────────────────────────────────────────┤
│ GERENTE (tenant-level manager)                                   │
│ UUID: 00000000-0000-4000-8000-000000000004                       │
│ Escopo: APENAS seu próprio tenant                                │
│ Acesso: CRM + Chat + Reports (sem gerenciar usuários)            │
├──────────────────────────────────────────────────────────────────┤
│ ATENDENTE (tenant-level agent)                                   │
│ UUID: 00000000-0000-4000-8000-000000000005                       │
│ Escopo: APENAS seu próprio tenant                                │
│ Acesso: Chat básico + CRM leitura                                │
└──────────────────────────────────────────────────────────────────┘
```

---

## Bounded Context(s)

- `Auth` — Modelo AuthRole, AuthUser, Policies, Actions, Requests (core da mudança)
- `Platform` — PlatformTenantImpersonateAction, PlatformBillingInvoicePolicy, PlatformPlanPolicy
- `Billing` — BillingSubscriptionController, BillingDowngradeEnforcementAction, BillingPlanChangePreviewAction, Billing Requests
- `Ai` — AiPromptGuardianJob, AiBudgetThresholdNotificationListener
- `Configuration` — EvaluationNotificationListener
- `Shared` — AppServiceProvider (Gate global)

---

## Escopo

### Incluído

- [ ] Unificar `admin` + `inquilino` → `Inquilino` (UUID fixo)
- [ ] Criar 4 UUIDs fixos para roles de sistema (Administrador, Inquilino, Gerente, Atendente)
- [ ] Adicionar constantes `*_ID` e `*_NAME` em `AuthRole`
- [ ] Adicionar métodos helpers em `AuthUser`: `hasRoleId()`, `isInquilino()`, `isManager()`, `isAgent()`, `isAnyTenantAdmin()`
- [ ] Refatorar `isSuperAdmin()` para usar UUID em vez de nome
- [ ] Criar migration de backfill: unificar roles, renomear, atribuir UUIDs fixos, reatribuir usuários
- [ ] Substituir todas as comparações `where('name', '...')` por `where('id', AuthRole::*_ID)`
- [ ] Substituir todas as comparações `hasRole('admin', ...)` por `$user->isInquilino()`
- [ ] Substituir todas as comparações `in_array(AuthRole::SUPER_ADMIN, ...)` por `in_array(AuthRole::ADMINISTRADOR_ID, ...)`
- [ ] Proteger as 4 roles contra exclusão em `AuthRoleActions::delete()`
- [ ] Corrigir bug silencioso: `AiBudgetThresholdNotificationListener` usa `['admin', 'owner']` → `owner` nunca existiu
- [ ] Corrigir bug silencioso: `EvaluationNotificationListener` usa `['manager', ...]` → case mismatch com `'Gerente'`
- [ ] Atualizar todos os seeders para usar UUIDs fixos
- [ ] Atualizar todos os testes (~34 arquivos) para usar constantes de UUID
- [ ] Manter constantes antigas como `@deprecated` para retrocompatibilidade temporária

### Fora de Escopo

- Roles customizadas criadas pelo usuário (ex: "supervisor") — continuam com UUID gerado e comparação normal
- Gateway (NestJS) — não usa roles diretamente
- App/Electron — consomem roles via API, não fazem comparação
- Remoção do Gate global do AppServiceProvider (melhoria de segurança separada)

---

## Critérios de Aceite

- [ ] CA-1: As 4 roles de sistema existem no banco com UUIDs fixos após rodar a migration
- [ ] CA-2: Nenhuma das 4 roles de sistema pode ser excluída via API (retorna 403)
- [ ] CA-3: `AuthUser::isSuperAdmin()` retorna `true` para usuário com role `ADMINISTRADOR_ID`
- [ ] CA-4: `AuthUser::isInquilino()` retorna `true` para usuário com role `INQUILINO_ID`
- [ ] CA-5: `AuthUser::isManager()` retorna `true` para usuário com role `GERENTE_ID`
- [ ] CA-6: `AuthUser::isAgent()` retorna `true` para usuário com role `ATENDENTE_ID`
- [ ] CA-7: Impersonação de tenant funciona sem `LOWER()` — busca direta por `GERENTE_ID`
- [ ] CA-8: Todos os seeders rodam sem erro com roles usando UUIDs fixos
- [ ] CA-9: `composer gate:all` passa (Pint + PHPStan L6 + Pest)
- [ ] CA-10: Zero ocorrências de `where('name', 'admin')` ou `hasRole('admin')` no código de produção
- [ ] CA-11: Zero ocorrências de `where('name', 'inquilino')` no código de produção
- [ ] CA-12: Cache do Spatie Permission é limpo após a migration
- [ ] CA-13: Todos os usuários que tinham role `admin` ou `inquilino` agora têm role `Inquilino`
- [ ] CA-14: Roles `admin` e `inquilino` (antigas) foram removidas do banco

---

## Dependências

- Bounded contexts: ver `.context/ARCHITECTURE/modules.yaml`
- Integrações externas: nenhuma
- Features prévias: nenhuma

---

## Riscos

| Risco | Impacto | Mitigação |
|-------|---------|-----------|
| Spatie `assignRole()` não aceita UUID como parâmetro | Alto | Testar explicitamente; fallback: resolver role por ID antes de `assignRole()` |
| Cache do Spatie Permission não invalidado após migration | Alto | Adicionar `permission:cache-reset` na migration |
| Testes falham por roles não encontradas (UUID vs nome) | Médio | Criar roles por `firstOrCreate(['id' => UUID])` em todos os testes |
| Branches paralelas usando constantes antigas (`SUPER_ADMIN`) | Médio | Manter `@deprecated` por 1 sprint; comunicar time |
| Migration falha em banco com roles já existentes com nomes diferentes | Médio | Usar `upsert` com `guard_name` como chave secundária; rollback plano |
| Gate global no AppServiceProvider com performance degradada | Baixo | `hasRoleId()` usa `roles()` relationship já carregada em auth; medir se necessário |
| Usuários com ambas as roles (`admin` + `inquilino`) geram duplicação | Baixo | Migration faz `syncRoles` para garantir apenas `Inquilino` |

### Notas

- **Migration antiga `2026_04_29_090000_backfill_chat_transmission_list_permissions.php`** usa strings `'super-admin'`, `'admin'`, `'inquilino'`, `'gerente'`. Não precisa ser alterada pois já foi executada em produção. Em caso de `migrate:fresh`, ela roda antes da nova migration de unificação e funciona corretamente (os nomes antigos ainda existem no banco nesse momento).

---

## Estratégia de Migração

```
1. Criar role `Inquilino` com UUID fixo (se não existir)
2. Reatribuir TODOS os usuários de `admin` → `Inquilino`
3. Reatribuir TODOS os usuários de `inquilino` → `Inquilino`
4. Copiar permissões do `inquilino` para `Inquilino` (herda do seeder existente)
5. Deletar roles `admin` e `inquilino` (se existiam separadas)
6. Limpar cache do Spatie Permission
7. Rodar testes
```

---

## Tabela de Roles

| Role | UUID Fixo | Nome (display) | Constante ID | Constante NAME |
|------|-----------|----------------|--------------|----------------|
| Administrador | `00000000-0000-4000-8000-000000000001` | `Administrador` | `ADMINISTRADOR_ID` | `ADMINISTRADOR_NAME` |
| ~~Admin~~ | ~~`00000000-0000-4000-8000-000000000002`~~ | ~~`Admin`~~ | *REMOVIDO — unificado em Inquilino* |
| Inquilino | `00000000-0000-4000-8000-000000000003` | `Inquilino` | `INQUILINO_ID` | `INQUILINO_NAME` |
| Gerente | `00000000-0000-4000-8000-000000000004` | `Gerente` | `GERENTE_ID` | `GERENTE_NAME` |
| Atendente | `00000000-0000-4000-8000-000000000005` | `Atendente` | `ATENDENTE_ID` | `ATENDENTE_NAME` |

---

## Mapa de Arquivos

### Camada 1 — Modelo + Migration (base)

| Arquivo | Tipo | Mudança |
|---------|------|---------|
| `api/src/Domain/Auth/Models/AuthRole.php` | Modificar | Novas constantes `*_ID` e `*_NAME`, deprecar antigas, remover `ADMIN_ID` |
| `api/src/Domain/Auth/Models/AuthUser.php` | Modificar | Helpers `hasRoleId()`, `isInquilino()`, `isManager()`, `isAgent()`, `isAnyTenantAdmin()` |
| `api/database/migrations/2026_05_05_000000_unify_roles_to_uuid.php` | Criar | Unificar admin+inquilino → Inquilino, UUIDs fixos, renomear, limpar cache |

### Camada 2 — Actions

| Arquivo | Tipo | Mudança |
|---------|------|---------|
| `api/src/Domain/Auth/Actions/AuthRoleActions.php` | Modificar | Proteção por ID em `delete()` e `list()` |
| `api/src/Domain/Auth/Actions/AuthUserActions.php` | Modificar | `in_array` por UUID em vez de nome |
| `api/src/Domain/Platform/Actions/PlatformTenantImpersonateAction.php` | Modificar | `where('id', GERENTE_ID)` em vez de `LOWER(name)` |
| `api/src/Domain/Billing/Actions/BillingDowngradeEnforcementAction.php` | Modificar | `where('id', ADMINISTRADOR_ID)` |
| `api/src/Domain/Billing/Actions/BillingPlanChangePreviewAction.php` | Modificar | `where('id', ADMINISTRADOR_ID)` |

### Camada 3 — Policies

| Arquivo | Tipo | Mudança |
|---------|------|---------|
| `api/src/Domain/Auth/Policies/AuthRolePolicy.php` | Modificar | Comparação por ID |
| `api/src/Domain/Auth/Policies/AuthUserPolicy.php` | Modificar | `$user->isInquilino()` em vez de `hasRole('admin')` |
| `api/src/Domain/Platform/Policies/PlatformBillingInvoicePolicy.php` | Modificar | `$user->isInquilino()` em vez de `hasRole('admin')` |
| `api/src/Domain/Platform/Policies/PlatformPlanPolicy.php` | Modificar | `$user->isInquilino()` em vez de `hasRole('admin')` |
| `api/src/Domain/Billing/Policies/BillingSubscriptionPolicy.php` | Modificar | `$user->isInquilino()` em vez de `hasRole('admin')` |

### Camada 4 — Services

| Arquivo | Tipo | Mudança |
|---------|------|---------|
| `api/src/Domain/Platform/Services/PlatformPlanEnforcementService.php` | Modificar | `$user->isInquilino()` |

### Camada 5 — Controllers

| Arquivo | Tipo | Mudança |
|---------|------|---------|
| `api/src/Domain/Billing/Http/Controllers/BillingSubscriptionController.php` | Modificar | `$user->isInquilino()` |

### Camada 6 — Form Requests

| Arquivo | Tipo | Mudança |
|---------|------|---------|
| `api/src/Domain/Billing/Http/Requests/BillingPlanChangePreviewRequest.php` | Modificar | `$user->isInquilino()` |
| `api/src/Domain/Billing/Http/Requests/BillingChangePlanRequest.php` | Modificar | `$user->isInquilino()` |
| `api/src/Domain/Platform/Http/Requests/PlatformBillingInvoiceIndexRequest.php` | Modificar | `$user->isInquilino()` |
| `api/src/Domain/Platform/Http/Requests/PlatformBillingInvoiceStoreRequest.php` | Modificar | `$user->isInquilino()` |
| `api/src/Domain/Auth/Http/Requests/AuthUserStoreRequest.php` | Modificar | `in_array` por UUID |
| `api/src/Domain/Auth/Http/Requests/AuthUserUpdateRequest.php` | Modificar | `in_array` por UUID |

### Camada 7 — Jobs e Listeners

| Arquivo | Tipo | Mudança |
|---------|------|---------|
| `api/src/Domain/Ai/Jobs/AiPromptGuardianJob.php` | Modificar | `whereIn('id', [ADMINISTRADOR_ID])` — remover `admin` |
| `api/src/Domain/Ai/Listeners/AiBudgetThresholdNotificationListener.php` | Modificar | `whereIn('id', [INQUILINO_ID, ADMINISTRADOR_ID])` — corrigir bug `owner` |
| `api/src/Domain/Configuration/Listeners/EvaluationNotificationListener.php` | Modificar | `whereIn('id', [GERENTE_ID, ADMINISTRADOR_ID])` — corrigir bug `manager` |

### Camada 8 — App Service Provider

| Arquivo | Tipo | Mudança |
|---------|------|---------|
| `api/app/Providers/AppServiceProvider.php` | Modificar | Gate `$user->isInquilino()` em vez de `hasRole('admin')` |

### Camada 9 — Seeders

| Arquivo | Tipo | Mudança |
|---------|------|---------|
| `api/database/seeders/DatabaseSeeder.php` | Modificar | 4 roles com UUIDs fixos, remover `admin` |
| `api/database/seeders/RolePermissionSeeder.php` | Modificar | Lookup por `INQUILINO_ID`, `GERENTE_ID`, `ATENDENTE_ID` |
| `api/database/seeders/AuthExtraUsersSeeder.php` | Modificar | Constantes de ID (4 roles) |
| `api/database/seeders/DemoDataSeeder.php` | Modificar | Constantes de ID (4 roles) |
| `api/database/seeders/TestCompaniesSeeder.php` | Modificar | Constantes de ID + NAME (remover `admin`) |

### Camada 10 — Testes (~34 arquivos)

Todos os testes que criam roles inline ou comparam por nome precisam ser atualizados para usar UUIDs fixos via `firstOrCreate(['id' => AuthRole::*_ID])` e helpers `isInquilino()`, `isSuperAdmin()`, etc.

Lista completa:
1. `api/tests/Feature/AuthRbacTest.php`
2. `api/tests/Feature/AuthRoleControllerTest.php`
3. `api/tests/Feature/AuthUserControllerTest.php`
4. `api/tests/Feature/AuthSuperAdminProtectionTest.php`
5. `api/tests/Feature/Platform/PlatformUserImpersonateTest.php`
6. `api/tests/Feature/Platform/PlatformTenantImpersonateTest.php`
7. `api/tests/Feature/Platform/PlatformTenantDetailsTest.php`
8. `api/tests/Feature/Platform/PlatformBillingInvoiceControllerTest.php`
9. `api/tests/Feature/Platform/PlatformPlanControllerTest.php`
10. `api/tests/Feature/Platform/PlatformPlanEnforcementTest.php`
11. `api/tests/Feature/Platform/PlatformTenantPurgeTest.php`
12. `api/tests/Feature/BillingSubscriptionTest.php`
13. `api/tests/Feature/BillingChangePlanTest.php`
14. `api/tests/Feature/BillingOwnerProtectionTest.php`
15. `api/tests/Feature/Billing/BillingInvoiceListTest.php`
16. `api/tests/Feature/Security/TenantIsolationTest.php`
17. `api/tests/Feature/Security/AuthorizationSecurityTest.php`
18. `api/tests/Feature/RbacChatControllerTest.php`
19. `api/tests/Feature/ChatTicketEvaluationTest.php`
20. `api/tests/Feature/Chat/CloseInactiveTicketsTest.php`
21. `api/tests/Feature/Chat/ChatRoutingQueueControllerTest.php`
22. `api/tests/Feature/ReproduceTenantCreationTest.php`
23. `api/tests/Unit/Auth/AuthUserActionsTest.php`
24. `api/tests/Unit/Auth/AuthRoleActionsTest.php`
25. `api/tests/Unit/Platform/PlatformPlanEnforcementServiceTest.php`
26. `api/tests/Unit/Platform/PlatformPlanPolicyTest.php`
27. `api/tests/Unit/Reports/ReportsPolicyTest.php`
28. `api/tests/Unit/Domain/Ai/Policies/AiPromptPolicyTest.php`
29. `api/tests/Unit/Platform/Actions/PlatformTenantHardDeleteActionTest.php`
30. `api/tests/Feature/Domain/Ai/Http/Controllers/AiPromptPlanControllerTest.php`
31. `api/tests/Feature/Domain/Ai/Http/Controllers/AiPromptMasterControllerTest.php`
32. `api/tests/Feature/Domain/Ai/Http/Controllers/AiPromptSegmentControllerTest.php`
33. `api/tests/Feature/Domain/Ai/Http/Controllers/AiPromptQuarantineControllerTest.php`
34. `api/tests/Feature/Domain/Ai/Http/Controllers/AiTenantSegmentControllerTest.php`

---

## Tasks

> Decomposição detalhada em `.context/DOCS/TASKS/feat-001-roles-uuid-tasks.md`

---

## Histórico

- 2026-05-05: Criada por ARCHITECT (análise + planejamento)
- 2026-05-05: Atualizada — unificação `admin` + `inquilino` → `Inquilino`, 4 roles no total
- 2026-05-05: Revisão crítica — adicionados 3 testes esquecidos (total 34), nota sobre migration antiga
