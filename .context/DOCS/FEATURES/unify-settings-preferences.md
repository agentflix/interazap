# Feature: Unificar Settings em Tela de Preferências

## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | FEAT-024 |
| **Nome** | unify-settings-preferences |
| **Bounded Context** | Configuration / Auth |
| **Workspaces** | app |
| **Complexidade** | M |
| **Status** | ✅ Concluída |
| **Data** | 2026-05-18 |
| **Autor** | Rafael Silva |

## Flags

- [ ] ⚠️ MULTI-TENANT — toca Platform ou BelongsToTenant
- [ ] ⚠️ RISCO FINANCEIRO — toca Billing ou ASAAS
- [ ] ⚠️ WHATSAPP — verificar UazAPI + Z-API
- [ ] 🚨 BREAKING CHANGE
- [ ] 🔒 SEGURANÇA — toca Auth ou tokens

## Resumo

Hoje existem duas telas separadas de configurações (`/settings/preferences` e `/settings/tenant`) que fragmentam a experiência do admin. A proposta é mover as seções de tenant para dentro de `/settings/preferences`, exibindo-as condicionalmente para quem tem permissão `platform.tenants.manage`. A rota `/settings/tenant` passa a redirecionar para `/settings/preferences`.

## Problema que Resolve

- Admin precisa navegar entre duas telas para configurar preferências pessoais + configurações globais do workspace.
- Menu lateral tem dois itens de configuração que se sobrepõem conceitualmente ("Minhas Preferências" e "Configurações do Inquilino").
- Usuários sem permissão `platform.tenants.manage` nunca veem a segunda tela, então ela fica escondida mesmo quando necessária.

## Solução Proposta

Mover os blocos de Localização, Privacidade e Auto-fechamento do componente `TenantSettingsComponent` para dentro do `PreferencesComponent`. As seções de tenant são renderizadas condicionalmente baseado na permissão do usuário autenticado. O componente unificado faz duas chamadas de API independentes (uma por store/service) e dois salvamentos paralelos no mesmo botão "Salvar". A rota `/settings/tenant` recebe um `redirectTo: '/settings/preferences'`. O item "Configurações do Inquilino" é removido do sidenav.

## Escopo

### Incluído ✅
- [x] Adicionar seções Localização, Privacidade e Auto-fechamento ao `PreferencesComponent` (condicionais por permissão)
- [x] Integrar `TenantSettingsService` ao `PreferencesComponent` com estado local (signals)
- [x] Salvar preferências pessoais + tenant em paralelo no mesmo `save()`
- [x] Reset unificado que recarrega ambos os dados
- [x] Redirecionar `/settings/tenant` → `/settings/preferences` via `redirectTo`
- [x] Remover item "Configurações do Inquilino" do `menu-config.ts`
- [x] Deletar `TenantSettingsComponent` e seus arquivos (`tenant-settings.ts`, `tenant-settings.html`, `tenant-settings.routes.ts`, `tenant-settings.spec.ts`)
- [x] Atualizar spec `preferences.spec.ts` para cobrir as novas seções (mock condicional de permissão)

### Fora de Escopo ❌
- Mudanças na API (backend inalterado)
- Migração de dados ou schemas
- Alterações no `gateway/`
- Criação de tabs/accordion — usar seções `<section>` como padrão existente
- Mudanças em `electron/`

## Dependências

| Tipo | Descrição | Status |
|------|-----------|--------|
| Módulo | `PreferencesStore` (`@core/services/preferences.store`) | ativo |
| Módulo | `TenantSettingsService` (`@core/services/tenant-settings.service`) | ativo |
| Módulo | `AuthStoreService` — checar `user().permissions` ou usar guard | ativo |
| Módulo | `permissionGuard` — já usado nas rotas | ativo |

## Critérios de Aceite

- [x] `/settings/preferences` exibe seções de Localização, Privacidade e Auto-fechamento **somente** para usuário com `platform.tenants.manage`
- [x] `/settings/preferences` NÃO exibe seções de tenant para usuário sem `platform.tenants.manage`
- [x] Botão "Salvar" persiste preferências pessoais + tenant em uma única ação (paralelo)
- [x] Botão "Descartar" recarrega dados de ambas as fontes
- [x] Navegar para `/settings/tenant` redireciona para `/settings/preferences`
- [x] Item "Configurações do Inquilino" não aparece mais no sidenav
- [x] Arquivos de `tenant-settings/` removidos sem referências órfãs
- [x] `preferences.spec.ts` atualizado: testa renderização condicional das seções de tenant

## Análise Técnica

### Estado de cada tela hoje

**`PreferencesComponent`** (`pages/auth/preferences/`)
- Usa `PreferencesStore` (signal store) para carregar/salvar preferências do usuário
- 5 form groups: `appearanceForm`, `behaviorForm`, `crmDefaultsForm`, `accessibilityForm`, `securityForm`
- Notificações via `NotificationPreferencesService`
- Permissão: `settings.general.view`

**`TenantSettingsComponent`** (`pages/platform/tenant-settings/`)
- Usa `TenantSettingsService` com `AuthStoreService.user().tenant_id`
- 3 form groups: `localizationForm`, `privacyForm`, `chatForm`
- Estado local via signals (`isLoading`, `isSaving`, `error`)
- Permissão: `platform.tenants.manage`

### Estratégia de merge

1. Injetar `TenantSettingsService`, `AuthStoreService` e `ChangeDetectorRef` no `PreferencesComponent`
2. Adicionar signals de estado de tenant: `tenantIsLoading`, `tenantIsSaving`, `tenantError`
3. Adicionar computed `hasTenantPermission` via `AuthStoreService` (checar array de permissions)
4. Adicionar os 3 form groups de tenant ao `PreferencesComponent`
5. `ngOnInit`: se `hasTenantPermission`, chamar `loadTenantSettings()` em paralelo com `store.load()`
6. `save()`: se `hasTenantPermission`, chamar `saveTenantSettings()` em paralelo após salvar prefs pessoais
7. `reset()`: recarregar ambos

### Verificar `hasTenantPermission`

Padrão a investigar no `AuthStoreService`:
```ts
// Hipótese — confirmar antes de implementar
readonly hasTenantPermission = computed(() =>
  this.authStore.user()?.permissions?.includes('platform.tenants.manage') ?? false
);
```
> ⚠️ Confirmar como `permissionGuard` verifica permissões — pode ser via `PermissionService` ou campo diferente no model de user.

## Tasks

Ver: `.context/DOCS/TASKS/unify-settings-preferences-tasks.md`

| Task | Título | Status | Workspace |
|------|--------|--------|-----------|
| TASK-5.1.1 | Integrar TenantSettingsService no PreferencesComponent | ✅ | app |
| TASK-5.1.2 | Adicionar seções de tenant no template | ✅ | app |
| TASK-5.2.1 | Substituir rota settings/tenant por redirectTo | ✅ | app |
| TASK-5.2.2 | Remover item "Configurações do Inquilino" do sidenav | ✅ | app |
| TASK-5.2.3 | Deletar TenantSettingsComponent e arquivos | ✅ | app |
| TASK-5.3.1 | Atualizar preferences.spec.ts com testes de tenant | ✅ | app |

**Progresso:** 6 / 6 tasks concluídas
