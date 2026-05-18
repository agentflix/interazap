# Tasks: Unificar Settings em Tela de Preferências (FEAT-024)

Feature doc: `.context/DOCS/FEATURES/unify-settings-preferences.md`
Status geral: ✅ Concluída | 6/6 tasks concluídas

---

## FASE 5: FRONTEND (app/)

> Feature é 100% frontend. Nenhuma mudança em api/ ou gateway/.
> Ordem de execução: 5.1.1 → 5.1.2 → 5.2.1 + 5.2.2 (paralelo) → 5.2.3 → 5.3.1

---

### 5.1 — Componente unificado

---

- [x] **TASK-5.1.1** ✅: Integrar TenantSettingsService no PreferencesComponent

  **T — Tarefa:** Adicionar toda a lógica de configurações de tenant ao `PreferencesComponent`: injetar serviços, criar form groups, signals de estado, método de carga, save e reset paralelos, além de marcar o store como dirty quando forms de tenant mudam.

  **A — Arquivo:** `app/src/app/pages/auth/preferences/preferences.ts`

  **C — Comportamento:**

  ANTES:
  - `PreferencesComponent` gerencia apenas preferências do usuário (5 form groups)
  - Não conhece `TenantSettingsService`, `AuthStoreService`, nem tenant

  DEPOIS:
  - Injeta `TenantSettingsService`, `AuthStoreService`, `ChangeDetectorRef`
  - Computed `hasTenantPermission = computed(() => this.authStore.hasPermission('platform.tenants.manage'))`
  - Adiciona `localizationForm`, `privacyForm`, `chatForm` (idênticos ao `TenantSettingsComponent` atual)
  - Adiciona signals: `tenantIsLoading`, `tenantIsSaving`, `tenantError`
  - `ngOnInit`: se `hasTenantPermission()`, chama `loadTenantSettings()` em paralelo com `store.load()`
  - `save()`: após salvar prefs pessoais, se `hasTenantPermission()`, chama `saveTenantSettings()` em paralelo
  - `reset()`: também chama `loadTenantSettings()` se `hasTenantPermission()`
  - Forms de tenant assinados em `subscribeToChanges()` para chamar `store.markDirty()` — reutiliza guarda de unsaved changes existente sem alteração

  Detalhes de `loadTenantSettings()`:
  ```ts
  private loadTenantSettings(): void {
    const tenantId = this.authStore.user()?.tenant_id;
    if (!tenantId) return;
    this.tenantIsLoading.set(true);
    this.tenantError.set(null);
    this.tenantSettingsService.getSettings(String(tenantId))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const s = response.data;
          this.localizationForm.patchValue(s.settings_localization, { emitEvent: false });
          this.privacyForm.patchValue(s.settings_privacy, { emitEvent: false });
          if (s.settings_chat) this.chatForm.patchValue(s.settings_chat, { emitEvent: false });
          this.tenantIsLoading.set(false);
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.tenantError.set(err.error?.message ?? 'Não foi possível carregar configurações do workspace.');
          this.tenantIsLoading.set(false);
          this.cdr.markForCheck();
        },
      });
  }
  ```

  Detalhes de `saveTenantSettings()` (chamado dentro de `save()`):
  ```ts
  private saveTenantSettings(): void {
    const tenantId = this.authStore.user()?.tenant_id;
    if (!tenantId) return;
    this.tenantIsSaving.set(true);
    const settings: Partial<TenantSettings> = {
      settings_localization: this.localizationForm.getRawValue(),
      settings_privacy: this.privacyForm.getRawValue(),
      settings_chat: this.chatForm.getRawValue() as TenantChatAutoCloseSettings,
    };
    this.tenantSettingsService.updateSettings(String(tenantId), settings)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => { this.tenantIsSaving.set(false); this.cdr.markForCheck(); },
        error: (err) => {
          this.tenantError.set(err.error?.message ?? 'Não foi possível salvar configurações do workspace.');
          this.tenantIsSaving.set(false);
          this.cdr.markForCheck();
        },
      });
  }
  ```

  Em `subscribeToChanges()` adicionar:
  ```ts
  [this.localizationForm, this.privacyForm, this.chatForm].forEach(form =>
    form.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => this.store.markDirty())
  );
  ```

  O `isAutoCloseEnabled` toSignal vem de `chatForm.controls.auto_close_inactivity_enabled.valueChanges`.

  **E — Evidência:**
  - [x] `pnpm --filter app build` sucede sem erros de TypeScript
  - [x] `pnpm --filter app lint` retorna 0 warnings
  - [x] `hasTenantPermission` é computed via `authStore.hasPermission('platform.tenants.manage')`
  - [x] `ngOnInit` chama `loadTenantSettings()` somente quando `hasTenantPermission()` é verdadeiro
  - [x] `save()` chama `saveTenantSettings()` somente quando `hasTenantPermission()` é verdadeiro
  - [x] Forms de tenant disparam `store.markDirty()` via `subscribeToChanges()`

  **Status:** ✅ Concluída
  **Dependência:** nenhuma

---

- [x] **TASK-5.1.2** ✅: Adicionar seções de tenant no template do PreferencesComponent

  **T — Tarefa:** Adicionar as seções HTML de Localização, Privacidade e Auto-fechamento ao template `preferences.html`, condicionais via `@if (hasTenantPermission())`, com loading/error state de tenant e opções idênticas às do `TenantSettingsComponent`.

  **A — Arquivo:** `app/src/app/pages/auth/preferences/preferences.html`

  **C — Comportamento:**

  ANTES:
  - Template não tem seções de Localização, Privacidade, Auto-fechamento

  DEPOIS:
  - Bloco `@if (hasTenantPermission())` envolve as três seções de tenant
  - Loading state de tenant: `@if (tenantIsLoading())` — skeleton dentro do bloco
  - Error state de tenant: `@if (tenantError() && !tenantIsLoading())` — `af-alert` com botão retry
  - Seção **Localização**: grid com `localizationForm` (timezone, dateFormat, timeFormat, currencyFormat)
  - Seção **Privacidade**: flex col com `privacyForm` (presence, readReceipt, notificationPreview)
  - Seção **Auto-fechamento**: toggle + campos condicionais `@if (isAutoCloseEnabled())`
  - Seções inseridas após a seção "Segurança" e antes dos botões de ação
  - Botão "Salvar" exibe loading quando `store.isSaving() || tenantIsSaving()`
  - Botão "Descartar" desabilitado quando `!store.hasUnsavedChanges()` (comportamento atual mantido)
  - Classes CSS: usar padrão `card card-body` (consistente com tenant-settings.html atual) OU padrão `bg-white dark:bg-neutral-900 rounded-xl border...` (padrão do preferences.html) — **usar o padrão do preferences.html** para consistência visual

  **E — Evidência:**
  - [x] Usuário com `platform.tenants.manage`: vê seções de Localização, Privacidade, Auto-fechamento
  - [x] Usuário sem `platform.tenants.manage`: não vê essas seções (testado com mock de permissão)
  - [x] `pnpm --filter app build` sucede
  - [x] `pnpm --filter app lint` retorna 0 warnings
  - [x] Botão "Salvar" fica em loading enquanto `tenantIsSaving()` é verdadeiro

  **Status:** ✅ Concluída
  **Dependência:** TASK-5.1.1 concluída

---

### 5.2 — Roteamento e limpeza

---

- [x] **TASK-5.2.1** ✅: Substituir rota settings/tenant por redirectTo

  **T — Tarefa:** Trocar a definição da rota `settings/tenant` em `app.routes.ts` de lazy-load com `permissionGuard` para um simples `redirectTo: '/settings/preferences'`.

  **A — Arquivo:** `app/src/app/app.routes.ts`

  **C — Comportamento:**

  ANTES:
  ```ts
  {
    path: 'settings/tenant',
    canActivate: [permissionGuard],
    loadChildren: () =>
      import('./pages/platform/tenant-settings/tenant-settings.routes').then((m) => [m.default]),
    data: { title: 'Configurações do Inquilino', permission: 'platform.tenants.manage' },
  },
  ```

  DEPOIS:
  ```ts
  { path: 'settings/tenant', redirectTo: '/settings/preferences', pathMatch: 'full' },
  ```

  **E — Evidência:**
  - [x] Navegar para `/settings/tenant` no browser redireciona para `/settings/preferences`
  - [x] `pnpm --filter app build` sucede sem referência ao `tenant-settings.routes`

  **Status:** ✅ Concluída
  **Dependência:** TASK-5.1.1 e TASK-5.1.2 concluídas (redirect só faz sentido quando destino está pronto)

---

- [x] **TASK-5.2.2** ✅: Remover item "Configurações do Inquilino" do sidenav

  **T — Tarefa:** Remover o objeto de menu com `link: '/settings/tenant'` do array `children` do accordion "Configurações" em `menu-config.ts`.

  **A — Arquivo:** `app/src/app/layout/components/sidenav/menu-config.ts`

  **C — Comportamento:**

  ANTES:
  ```ts
  {
    type: 'item',
    label: 'Configurações do Inquilino',
    link: '/settings/tenant',
    iconName: 'building',
    requiredPermission: 'platform.tenants.manage',
  },
  ```

  DEPOIS:
  - Objeto removido. Accordion "Configurações" mantém os outros itens intactos.

  **E — Evidência:**
  - [x] Admin não vê item "Configurações do Inquilino" no sidenav
  - [x] Outros itens do accordion "Configurações" permanecem visíveis
  - [x] `pnpm --filter app build` sucede

  **Status:** ✅ Concluída
  **Dependência:** pode executar em paralelo com TASK-5.2.1

---

- [x] **TASK-5.2.3** ✅: Deletar TenantSettingsComponent e arquivos

  **T — Tarefa:** Remover os 4 arquivos do `TenantSettingsComponent` após confirmar que nenhuma referência órfã existe (rota já substituída por redirect, sidenav já sem link).

  **A — Arquivos a deletar:**
  - `app/src/app/pages/platform/tenant-settings/tenant-settings.ts`
  - `app/src/app/pages/platform/tenant-settings/tenant-settings.html`
  - `app/src/app/pages/platform/tenant-settings/tenant-settings.routes.ts`
  - `app/src/app/pages/platform/tenant-settings/tenant-settings.spec.ts`

  **C — Comportamento:**

  ANTES:
  - 4 arquivos existem em `pages/platform/tenant-settings/`
  - `tenant-settings.routes.ts` importa `TenantSettingsComponent`

  DEPOIS:
  - Diretório `pages/platform/tenant-settings/` removido
  - `pnpm --filter app build` não referencia esses arquivos

  Verificar antes de deletar:
  ```bash
  grep -r "tenant-settings\|TenantSettingsComponent" app/src --include="*.ts" -l
  ```
  Somente os 4 arquivos a deletar devem aparecer. Se outros arquivos referenciam, corrigi-los primeiro.

  **E — Evidência:**
  - [x] `grep -r "TenantSettingsComponent" app/src` retorna vazio
  - [x] `grep -r "tenant-settings.routes" app/src` retorna vazio
  - [x] `pnpm --filter app build` sucede
  - [x] `pnpm --filter app lint` retorna 0 warnings

  **Status:** ✅ Concluída
  **Dependência:** TASK-5.2.1 e TASK-5.2.2 concluídas

---

### 5.3 — Testes

---

- [x] **TASK-5.3.1** ✅: Atualizar preferences.spec.ts com testes de tenant

  **T — Tarefa:** Atualizar `preferences.spec.ts` para cobrir: renderização condicional das seções de tenant (com/sem permissão), save paralelo, e mock de `TenantSettingsService` + `AuthStoreService`.

  **A — Arquivo:** `app/src/app/pages/auth/preferences/preferences.spec.ts`

  **C — Comportamento:**

  ANTES:
  - Spec cobre apenas prefs pessoais (appearance, behavior, crm, accessibility)
  - Não injeta `TenantSettingsService` nem `AuthStoreService`

  DEPOIS:
  - `MockTenantSettingsService`: retorna `Observable<{ data: TenantSettings }>` com dados stub
  - `MockAuthStoreService`: expõe `hasPermission(p: string): boolean` — controlável por variável no teste
  - Suite "sem permissão de tenant": `hasPermission` retorna `false` → `hasTenantPermission` é `false` → `loadTenantSettings` não chamado
  - Suite "com permissão de tenant": `hasPermission` retorna `true` → `hasTenantPermission` é `true` → `localizationForm` populado após load
  - Teste: `save()` com permissão chama `tenantSettingsService.updateSettings()`
  - Teste: `save()` sem permissão NÃO chama `tenantSettingsService.updateSettings()`
  - Manter todos os testes existentes passando

  **E — Evidência:**
  - [x] `pnpm --filter app test` retorna 0 falhas no arquivo `preferences.spec.ts`
  - [x] Cobertura de branches de `hasTenantPermission` verificada (true e false)
  - [x] `pnpm --filter app lint` retorna 0 warnings

  **Status:** ✅ Concluída
  **Dependência:** TASK-5.1.1 e TASK-5.1.2 concluídas

---

### Revisão de Fase 5 (@REVIEWER + @QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Componentes standalone, sem imports desnecessários | @REVIEWER | ✅ |
| Nenhuma referência a `TenantSettingsComponent` ou `tenant-settings.routes` | @REVIEWER | ✅ |
| `hasTenantPermission` via `authStore.hasPermission()` (não hardcoded) | @REVIEWER | ✅ |
| Redirect `/settings/tenant` → `/settings/preferences` funcionando | @QA | ✅ |
| Vitest: 0 falhas em `preferences.spec.ts` | @QA | ✅ |
| ESLint: 0 warnings | @QA | ✅ |
| Build: `pnpm --filter app build` sucesso | @QA | ✅ |

**Gate de Qualidade Fase 5:** ✅ Passou
```bash
cd app && npm run gate:all 2>&1
```

---

## Progresso Geral

| Fase | Tasks | Concluídas | Gate |
|------|-------|-----------|------|
| 5 — Frontend | 6 | 6 | ✅ |
| **Total** | **6** | **6** | |

## Ordem de Execução

```
5.1.1 → 5.1.2 → 5.2.1 ─┐
                          ├─→ 5.2.3 → 5.3.1
               5.2.2 ────┘
```

- 5.2.1 e 5.2.2 podem rodar em paralelo
- 5.2.3 requer 5.2.1 + 5.2.2 concluídas
- 5.3.1 requer 5.1.1 + 5.1.2 concluídas (pode rodar em paralelo com 5.2.x)
