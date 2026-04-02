# PLAN-011 — Tela de Preferências do Usuário

## Context

Hoje não existe uma tela de preferências completa para o usuário no AgentFlix. Já existe `/settings/profile` (dados pessoais, senha, 2FA) e `/settings/preferences` aparece no menu dropdown do topbar, mas é uma rota placeholder sem implementação. O modelo `ConfigurationNotificationPreference` já existe no backend com suporte a preferências por tipo de notificação, canais e horários de silêncio — mas nenhuma UI expõe isso ao usuário.

**Objetivo:** Criar:
1. Uma tela de preferências pessoais (`/settings/preferences`) para o usuário configurar sua experiência individual
2. Uma tela de configurações do inquilino (`/settings/tenant`) para admin/gerente configurar local e privacidade global

---

## Escopo

### Incluído — Preferências do Usuário (`/settings/preferences`)

**1. Notificações (UI — usa `ConfigurationNotificationPreference` existente)**
- Toggle geral: "Receber notificações no aplicativo"
- Grid por tipo × canal (UI, Email, Push, WhatsApp, Webhook) usando `<af-checkbox-input>`
  - Tipos: `new_ticket`, `ticket_assigned`, `ticket_updated`, `ticket_closed`, `reminder`, `event`, `mention`, `system`, `billing`
- Horários de silêncio por tipo (usa campos existentes `quiet_start` / `quiet_end` do modelo)

**2. Aparência**
- Tema: Claro / Escuro / Sistema
- Densidade da interface: Compacta / Normal / Expandida
- Tamanho da fonte: Pequeno / Normal / Grande

**3. Comportamento**
- Som de notificações: Ligado / Desligado
- Notificação ao receber mensagem no chat: Ligado / Desligado
- Resposta rápida habilitada no chat
- Confirmar antes de enviar mensagens em massa
- Modo de abertura de ticket: Modal / Página inteira

**4. CRM — Valores padrão pessoais**
- Tipo de negociação padrão ao criar
- Status padrão ao criar tarefa (validado contra `CRMTaskStatus` enum)
- Exibir pipeline: Kanban / Lista
- Ordem padrão de negociações: Data / Valor / Probabilidade

**5. Segurança**
- Timeout de sessão (30min / 1h / 2h / Nunca = `null`)
- Atalho para autenticação em duas etapas (`/settings/two-factor`)

**6. Acessibilidade**
- Modo de alto contraste
- Reduzir animações

### Incluído — Configurações do Inquilino (`/settings/tenant`) — admin/gerente

**7. Localização**
- Fuso horário
- Formato de data
- Formato de hora (12h / 24h)
- Formato de moeda

**8. Privacidade**
- Status de presença: Visível para todos / Somente equipe / Oculto
- Confirmação de leitura no chat
- Mostrar preview nas notificações

### Excluído

- **Listar dispositivos/sessões ativas** — feature futura, fora do escopo desta task
- Configuração de uaZapi (escopo admin de TI)
- Configuração de integrações externas (e-mail, calendário)
- Preferências de IA (autopilot, simulador)
- Preferências de webhook

---

## Arquitetura de Dados

### User Preferences — `preferences` JSONB em `auth_users`

```php
// AuthUser::$casts = ['preferences' => 'array']
// Keys no JSONB (SEM notificações — ver ConfigurationNotificationPreference):
{
  "appearance": {
    "theme": "dark",         // light | dark | system
    "density": "normal",     // compact | normal | expanded
    "fontSize": "medium"     // small | medium | large
  },
  "behavior": {
    "sound": true,
    "chatNotify": true,
    "quickReply": false,
    "confirmBulk": true,
    "ticketOpenMode": "modal" // modal | page
  },
  "crmDefaults": {
    "negotiationType": "basic",   // basic | advanced | full
    "taskStatus": "pending",      // pending | in_progress | done
    "pipelineView": "kanban",     // kanban | list
    "negotiationOrder": "date"    // date | value | probability
  },
  "security": {
    "sessionTimeout": 60  // null = nunca
  },
  "accessibility": {
    "highContrast": false,
    "reducedMotion": false
  }
}
```

**Defaults aplicados pelo Action (não no banco):**

```php
private const DEFAULTS = [
    'appearance' => [
        'theme' => 'system',
        'density' => 'normal',
        'fontSize' => 'medium',
    ],
    'behavior' => [
        'sound' => true,
        'chatNotify' => true,
        'quickReply' => false,
        'confirmBulk' => true,
        'ticketOpenMode' => 'modal',
    ],
    'crmDefaults' => [
        'negotiationType' => 'basic',
        'taskStatus' => 'pending',
        'pipelineView' => 'kanban',
        'negotiationOrder' => 'date',
    ],
    'security' => [
        'sessionTimeout' => 60,
    ],
    'accessibility' => [
        'highContrast' => false,
        'reducedMotion' => false,
    ],
];
```

### Tenant Settings — `settings_localization` e `settings_privacy` JSONB em `platform_tenants`

```php
{
  "settings_localization": {
    "timezone": "America/Sao_Paulo",
    "dateFormat": "DD/MM/YYYY",  // DD/MM/YYYY | MM/DD/YYYY | YYYY-MM-DD
    "timeFormat": "24h",         // 12h | 24h
    "currencyFormat": "BRL"       // BRL | USD | EUR
  },
  "settings_privacy": {
    "presence": "team",           // all | team | hidden
    "readReceipt": true,
    "notificationPreview": true
  }
}
```

---

## Dependências

| Dependência | Status | Observação |
|------------|--------|------------|
| `ConfigurationNotificationPreference` (backend model) | ✅ Pronto | Fonte de dados para notificações |
| `ConfigurationNotificationController` | ✅ Pronto | Endpoints de preferências já existem (`preferences()`, `updatePreference()`, `updateAllPreferences()`) |
| `AuthProfileController` | ✅ Pronto | Já existe em `Auth/Http/Controllers/` — rota `/auth/profile/*` |
| `ThemeService` (frontend) | ✅ Pronto | localStorage, sincronizar com backend |
| `ToastService` | ✅ Pronto | Serviço, não `<af-toast>` |
| `PlatformTenantPolicy` — `platform.tenants.manage` | ✅ Pronto | Já existe |
| `CRMTaskStatus` enum | ✅ Pronto | Usado para validação de `crmDefaults.taskStatus` |
| `AfSwitchInputComponent` | ✅ Pronto | Para toggles |
| `AfSelectInputComponent` | ✅ Pronto | Para dropdowns |
| `AfRadioInputComponent` | ✅ Pronto | Para opções únicas |
| `AfCardComponent` | ✅ Pronto | Para seções |
| `AfCheckboxInputComponent` | ✅ Pronto | Para grid de notificações |
| `AuthUserPolicy` | ✅ Pronto | Adicionar `updatePreferences` |

---

## Arquivos a Criar / Modificar

> ⚠️ **Regra obrigatória (AGENTS.md):** Todo arquivo PHP listado deve começar com `declare(strict_types=1);`

### Backend (Laravel) — User Preferences

| Arquivo | Ação | Caminho |
|---------|------|---------|
| Migration `add_preferences_to_auth_users` | criar | `database/migrations/YYYY_MM_DD_HHMMSS_add_preferences_to_auth_users_table.php` |
| `AuthUser` — `$casts['preferences'] = 'array'` + `$fillable` incluir `'preferences'` | modificar | `api/src/Domain/Auth/Models/AuthUser.php` |
| `UserPreferenceDTO` — `readonly` com `fromArray` + `fromRequest` | criar | `api/src/Domain/Auth/DTOs/UserPreferenceDTO.php` |
| `GetUserPreferencesAction` | criar | `api/src/Domain/Auth/Actions/GetUserPreferencesAction.php` |
| `UpdateUserPreferencesAction` | criar | `api/src/Domain/Auth/Actions/UpdateUserPreferencesAction.php` |
| `UpdateUserPreferencesRequest` (FormRequest com validação) | criar | `api/src/Domain/Auth/Http/Requests/UpdateUserPreferencesRequest.php` |
| `AuthProfileController` — adicionar `preferences()` + `updatePreferences()` | modificar | `api/src/Domain/Auth/Http/Controllers/AuthProfileController.php` |
| `AuthUserPolicy` — criar `updatePreferences(AuthUser $user, AuthUser $model): bool` com `return $user->id === $model->id` (owner-only) | modificar | `api/src/Domain/Auth/Policies/AuthUserPolicy.php` |
| Rotas `GET|PATCH /auth/profile/preferences` em `auth.php` | modificar | `api/src/Domain/Auth/Routes/auth.php` |
| **Testes** | criar | `tests/Feature/AuthUserPreferencesTest.php` |
| **Testes** | criar | `tests/Unit/UserPreferenceDTOTest.php` |

### Backend (Laravel) — Tenant Settings

| Arquivo | Ação | Caminho |
|---------|------|---------|
| Migration `add_settings_to_platform_tenants` | criar | `database/migrations/YYYY_MM_DD_HHMMSS_add_settings_to_platform_tenants_table.php` |
| `PlatformTenant` — `$casts['settings_localization'] = 'array'` + `$casts['settings_privacy'] = 'array'` + `$fillable` incluir ambas colunas | modificar | `api/src/Domain/Platform/Models/PlatformTenant.php` |
| `TenantSettingDTO` — `readonly` com `fromArray` + `fromRequest` | criar | `api/src/Domain/Platform/DTOs/TenantSettingDTO.php` |
| `GetTenantSettingsAction` | criar | `api/src/Domain/Platform/Actions/GetTenantSettingsAction.php` |
| `UpdateTenantSettingsAction` | criar | `api/src/Domain/Platform/Actions/UpdateTenantSettingsAction.php` |
| `UpdateTenantSettingsRequest` (FormRequest com validação) | criar | `api/src/Domain/Platform/Http/Requests/UpdateTenantSettingsRequest.php` |
| `PlatformTenantController` — adicionar `settings()` + `updateSettings()` | modificar | `api/src/Domain/Platform/Http/Controllers/PlatformTenantController.php` |
| `PlatformTenantPolicy` — criar `updateSettings(AuthUser $user, PlatformTenant $tenant): bool` com: `isSuperAdmin($user) ? true : ($user->tenant_id === $tenant->id && $user->hasPermissionTo('platform.tenants.manage'))` | modificar | `api/src/Domain/Platform/Policies/PlatformTenantPolicy.php` |
| Rotas `GET|PATCH /platform/tenants/{id}/settings` | modificar | `api/src/Domain/Platform/Routes/api.php` |
| **Testes** | criar | `tests/Feature/TenantSettingsTest.php` |
| **Testes** | criar | `tests/Unit/TenantSettingDTOTest.php` |

### Frontend (Angular) — User Preferences

| Arquivo | Ação | Caminho |
|---------|------|---------|
| `preferences.model.ts` | criar | `app/src/app/shared/models/preferences.model.ts` |
| `preferences.service.ts` | criar | `app/src/app/core/services/preferences.service.ts` |
| `preferences.store.ts` — signal store com `isDirty`, `isSaving`, `save()` explícito | criar | `app/src/app/core/services/preferences.store.ts` |
| `unsaved-changes.guard.ts` — `CanDeactivateFn` para confirmar descarte | criar | `app/src/app/core/guards/unsaved-changes.guard.ts` |
| `preferences.html` | criar | `app/src/app/pages/auth/preferences/preferences.html` |
| `preferences.ts` — `standalone: true`, `OnPush`, `inject()`, `takeUntilDestroyed` | criar | `app/src/app/pages/auth/preferences/preferences.ts` |
| `preferences.routes.ts` | criar | `app/src/app/pages/auth/preferences/preferences.routes.ts` |
| `app.routes.ts` — `/settings/preferences` → `preferences.routes` | modificar | `app/src/app/app.routes.ts` |
| `ThemeService` — expandir tipo do signal para `'light' \| 'dark' \| 'system'`; `loadTheme()` detectar `system`; `effect()` aplicar `system` via `prefers-color-scheme` media query; persistir em localStorage | modificar | `app/src/app/core/services/theme.service.ts` |
| `AuthStoreService` — carregar preferências no login | modificar | `app/src/app/core/services/auth-store.service.ts` |
| **Testes** | criar | `app/src/app/core/services/preferences.service.spec.ts` |
| **Testes** | criar | `app/src/app/core/services/preferences.store.spec.ts` |
| **Testes** | criar | `app/src/app/pages/auth/preferences/preferences.spec.ts` |

### Frontend (Angular) — Tenant Settings

| Arquivo | Ação | Caminho |
|---------|------|---------|
| `tenant-settings.model.ts` | criar | `app/src/app/shared/models/tenant-settings.model.ts` |
| `tenant-settings.service.ts` | criar | `app/src/app/core/services/tenant-settings.service.ts` |
| `tenant-settings.html` | criar | `app/src/app/pages/platform/tenant-settings/tenant-settings.html` |
| `tenant-settings.ts` — `standalone: true`, `OnPush`, `inject()`, `takeUntilDestroyed` | criar | `app/src/app/pages/platform/tenant-settings/tenant-settings.ts` |
| `tenant-settings.routes.ts` | criar | `app/src/app/pages/platform/tenant-settings/tenant-settings.routes.ts` |
| `app.routes.ts` — `/settings/tenant` (guard: `auth`, permission: `platform.tenants.manage`) | modificar | `app/src/app/app.routes.ts` |
| `menu-config.ts` — adicionar link em Configurações | modificar | `app/src/app/layout/components/sidenav/menu-config.ts` |

---

## Estrutura da Tela (UI)

### User Preferences — `/settings/preferences`

```
┌─ Preferências do Usuário ──────────────────────────────┐
│  ┌─ Notificações ──────────────────────────────────┐   │
│  │  <af-switch-input> Receber notificações          │   │
│  │                                                   │   │
│  │  Tipo de notificação    UI  Email Push WhatsApp │   │
│  │  ├─ Novo ticket    <af-checkbox-input>           │   │
│  │  ├─ Ticket atribuído <af-checkbox-input>         │   │
│  │  ├─ Lembrete       <af-checkbox-input>           │   │
│  │  ├─ Cobrança       <af-checkbox-input>           │   │
│  │  └─ Sistema        <af-checkbox-input>           │   │
│  │  Horário silencioso: 22:00 – 08:00              │   │
│  └─────────────────────────────────────────────────┘   │
│  ┌─ Aparência ────────────────────────────────────┐   │
│  │  Tema:    ○ Claro  ● Escuro  ○ Sistema         │   │
│  │  Densidade: [Normal ▾]                         │   │
│  │  Fonte:   ○ Pequena  ● Normal  ○ Grande       │   │
│  └─────────────────────────────────────────────────┘   │
│  ┌─ Comportamento ────────────────────────────────┐   │
│  │  <af-switch-input> Som de notificações          │   │
│  │  <af-switch-input> Notificar ao receber chat    │   │
│  │  <af-switch-input> Resposta rápida             │   │
│  │  <af-switch-input> Confirmar antes de enviar    │   │
│  │  Modo de ticket: ○ Modal  ● Página            │   │
│  └─────────────────────────────────────────────────┘   │
│  ┌─ CRM Padrões ─────────────────────────────────┐   │
│  │  Tipo de negociação: [Básico ▾]               │   │
│  │  Exibir pipeline:  ○ Kanban  ● Lista         │   │
│  │  Ordem: [Data (recente) ▾]                      │   │
│  └─────────────────────────────────────────────────┘   │
│  ┌─ Acessibilidade ───────────────────────────────┐   │
│  │  <af-switch-input> Modo de alto contraste     │   │
│  │  <af-switch-input> Reduzir animações         │   │
│  └─────────────────────────────────────────────────┘   │
│  ┌─ Segurança ─────────────────────────────────────┐   │
│  │  Timeout de sessão: [● 1 hora ▾]               │   │
│  │  [→] Ativar autenticação em duas etapas         │   │
│  └─────────────────────────────────────────────────┘   │
│  [ Salvar preferências ]                               │
└─────────────────────────────────────────────────────────┘
```

### Tenant Settings — `/settings/tenant` (admin/gerente)

```
┌─ Configurações do Inquilino ────────────────────────────┐
│  ┌─ Localização ────────────────────────────────────┐   │
│  │  Fuso horário: [America/Sao_Paulo ▾]             │   │
│  │  Formato de data: [DD/MM/YYYY ▾]                 │   │
│  │  Formato de hora:  ○ 12h  ● 24h                 │   │
│  │  Formato de moeda: [R$ 1.234,56 ▾]              │   │
│  └─────────────────────────────────────────────────┘   │
│  ┌─ Privacidade ───────────────────────────────────┐   │
│  │  Status de presença:                             │   │
│  │    ○ Visível para todos                         │   │
│  │    ○ Somente equipe                             │   │
│  │    ○ Oculto                                     │   │
│  │  <af-switch-input> Confirmação de leitura       │   │
│  │  <af-switch-input> Mostrar preview             │   │
│  └─────────────────────────────────────────────────┘   │
│  [ Salvar configurações ]                              │
└─────────────────────────────────────────────────────────┘
```

**Padrão UI:**
- Cada seção dentro de `<af-card>`
- `<af-switch-input>` para toggles
- `<af-checkbox-input>` para grid de notificações (canal × tipo)
- `<af-radio-input>` para opções únicas
- `<af-select-input>` para dropdowns
- Botão "Salvar" com `af-loading-button` — estratégia **manual save** (sem auto-save)
  - `isDirty` no store detecta alterações e habilita/desabilita o botão
  - `isSaving` no store bloqueia clicks duplos durante a request
- Loading state com `<af-skeleton>`
- Sucesso/erro via `ToastService` (`inject(ToastService).success(...)`)
- `CanDeactivateFn` (guard `unsaved-changes.guard.ts`) — exibe confirmação antes de sair com `isDirty = true`

---

## Passos de Implementação (PREVC)

### Fase 1 — Backend: User Preferences

> Todos os arquivos PHP: `declare(strict_types=1);` + phpDoc + `final class` (Actions/DTOs/Requests)

1. Criar migration `add_preferences_to_auth_users` (`preferences JSONB DEFAULT '{}'`)
2. Adicionar `$casts['preferences'] = 'array'` + `$fillable[] = 'preferences'` em `AuthUser`
3. Criar `UserPreferenceDTO` — `readonly class` com `fromArray(array $data): self` e `fromRequest(array $data): self`
4. Criar `GetUserPreferencesAction` — faz merge de `auth_users.preferences` com `DEFAULTS` (nunca retorna null)
5. Criar `UpdateUserPreferencesRequest` com validação por key:
   - `appearance.theme`: `in:light,dark,system`
   - `appearance.density`: `in:compact,normal,expanded`
   - `appearance.fontSize`: `in:small,medium,large`
   - `behavior.*`: `boolean`
   - `behavior.ticketOpenMode`: `in:modal,page`
   - `crmDefaults.negotiationType`: `in:basic,advanced,full`
   - `crmDefaults.taskStatus`: `Rule::enum(CRMTaskStatus::class)` (validado contra enum existente)
   - `crmDefaults.pipelineView`: `in:kanban,list`
   - `crmDefaults.negotiationOrder`: `in:date,value,probability`
   - `security.sessionTimeout`: `sometimes|nullable|integer|min:1` (`null` = nunca) — se `security` for objeto ausente, mantém o valor existente
   - `accessibility.*`: `boolean`
   - Deep merge: partial update preserva seções não alteradas
   - Campos desconhecidos no payload são ignorados
6. Criar `UpdateUserPreferencesAction` — valida via FormRequest, deep merge, salva
7. Adicionar `preferences()` (GET) e `updatePreferences()` (PATCH) em `AuthProfileController`:
   - `$this->authorize('updatePreferences', auth()->user())` no início
   - Sem route model binding — usa `auth()->user()` diretamente
8. Criar `AuthUserPolicy::updatePreferences(AuthUser $user, AuthUser $model): bool` — `return $user->id === $model->id`
9. Registrar rotas em `auth.php`: `GET|PATCH /auth/profile/preferences`
10. Criar `tests/Feature/AuthUserPreferencesTest.php` (Pest)
11. Criar `tests/Unit/UserPreferenceDTOTest.php` (Pest)
12. `composer gate:all`

### Fase 2 — Backend: Tenant Settings

> Todos os arquivos PHP: `declare(strict_types=1);` + phpDoc + `final class` (Actions/DTOs/Requests)

1. Criar migration `add_settings_to_platform_tenants` (`settings_localization JSONB DEFAULT '{}'`, `settings_privacy JSONB DEFAULT '{}'`)
2. Adicionar em `PlatformTenant`:
   - `$casts['settings_localization'] = 'array'`
   - `$casts['settings_privacy'] = 'array'`
   - `$fillable[] = 'settings_localization'`
   - `$fillable[] = 'settings_privacy'`
3. Criar `TenantSettingDTO` — `readonly class` com `fromArray()` + `fromRequest()`
4. Criar `GetTenantSettingsAction` — retorna localization + privacy com defaults
5. Criar `UpdateTenantSettingsRequest` com validação de schema JSONB (timezone, dateFormat, timeFormat, currencyFormat, presence, etc.)
6. Criar `UpdateTenantSettingsAction` — valida via FormRequest, deep merge, salva
7. Adicionar `settings()` (GET) e `updateSettings()` (PATCH) em `PlatformTenantController`
8. Criar `PlatformTenantPolicy::updateSettings(AuthUser $user, PlatformTenant $tenant): bool`:
   ```php
   return $user->isSuperAdmin()
       ? true
       : ($user->tenant_id === $tenant->id && $user->hasPermissionTo('platform.tenants.manage'));
   ```
9. Registrar rotas `GET|PATCH /platform/tenants/{id}/settings` em `api.php`
10. Criar `tests/Feature/TenantSettingsTest.php` (Pest)
11. Criar `tests/Unit/TenantSettingDTOTest.php` (Pest)
12. `composer gate:all`

### Fase 3 — Frontend: User Preferences

1. Criar `preferences.model.ts` — interfaces TypeScript (sem `any`/`unknown`)
2. Criar `preferences.service.ts` — GET/PATCH `/auth/profile/preferences`
3. Criar `preferences.store.ts`:
   ```typescript
   // preferences = signal<UserPreferences>
   // isDirty = signal(false) — set true on any change
   // isSaving = signal(false)
   // save() = PATCH → reset isDirty on success
   // reset() = reload from API
   ```
4. Criar `unsaved-changes.guard.ts`:
   ```typescript
   export const unsavedChangesGuard: CanDeactivateFn<PreferencesComponent> =
     (component) => component.store.isDirty()
       ? confirm('Descartar alterações não salvas?')
       : true;
   ```
5. Criar `preferences.html` — 6 seções com `<af-card>`, `<af-switch-input>`, `<af-checkbox-input>`, `<af-radio-input>`, `<af-select-input>`
6. Criar `preferences.ts` — `standalone: true, ChangeDetectionStrategy.OnPush, inject(), takeUntilDestroyed()`; usar `ReactiveFormsModule` com `FormBuilder` (seguir padrão `profile.ts`). Cada `<af-checkbox-input>` recebe seu próprio `FormControl<boolean>`.
7. Criar `preferences.routes.ts` com `loadComponent` + `canDeactivate: [unsavedChangesGuard]`
8. Registrar rota `/settings/preferences` em `app.routes.ts`
9. Atualizar `ThemeService`:
   - Tipo do signal: `'light' | 'dark' | 'system'`
   - `loadTheme()`: detectar `system` → usar `window.matchMedia('(prefers-color-scheme: dark)')`
   - `effect()`: aplicar `system` → escuta `change` do media query e atualiza classe
10. Atualizar `AuthStoreService` — carrega preferências após login
11. Criar `preferences.service.spec.ts` (Vitest — mocks HttpClient)
12. Criar `preferences.store.spec.ts` (Vitest — verifica isDirty/isSaving)
13. Criar `preferences.spec.ts` (Vitest — componente)
14. `pnpm run gate:all`

### Fase 4 — Frontend: Tenant Settings

1. Criar `tenant-settings.model.ts`
2. Criar `tenant-settings.service.ts` — GET/PATCH `/platform/tenants/{id}/settings`
3. Criar `tenant-settings.html` — 2 seções com `<af-card>`, `<af-switch-input>`, `<af-radio-input>`, `<af-select-input>`
4. Criar `tenant-settings.ts` — `standalone: true, ChangeDetectionStrategy.OnPush, inject(), takeUntilDestroyed()`
5. Criar `tenant-settings.routes.ts` com `loadComponent`
6. Registrar rota `/settings/tenant` com guard de permissão `platform.tenants.manage`
7. Adicionar link em `menu-config.ts` (dentro de Configurações, visível para admin/gerente)
8. `pnpm run gate:all`

---

## Riscos e Mitigações

| Risco | Prob. | Impacto | Mitigação |
|-------|-------|---------|-----------|
| Tema "flash" antes de carregar preferências | Alta | Baixo | `ThemeService` inicia em `system`; substitui após load; localStorage como cache |
| Usuário legacy com `preferences = null` | Baixa | Médio | Action aplica `DEFAULTS` via `$user->preferences ?? []` |
| PATCH parcial substitui preferências incorretamente | Média | Alto | Deep merge no Action, não replace total |
| SuperAdmin acessa settings de outro tenant | Baixa | Alto | Policy: SuperAdmin bypass OU (`tenant_id match` + `platform.tenants.manage`) |
| Múltiplos clicks no botão "Salvar" | Baixa | Baixo | `isSaving` signal bloqueia clicks duplos via `af-loading-button` |
| Validação JSONB aceita campos inválidos | Média | Alto | `Rule::enum()` para enums; `in:` para strings; campos desconhecidos ignorados |

---

## Critérios de Validação

- [ ] `composer gate:all` passa no backend
- [ ] `pnpm run gate:all` passa no frontend
- [ ] GET `/auth/profile/preferences` retorna defaults completos ao usuário novo
- [ ] PATCH `/auth/profile/preferences` com `{ "appearance": { "theme": "dark" } }` faz deep merge
- [ ] PATCH parcial preserva campos `behavior` e `crmDefaults` existentes no banco (deep merge, não replace)
- [ ] PATCH com valor inválido retorna 422
- [ ] Policy: usuário não consegue alterar preferências de outro usuário (403)
- [ ] `crmDefaults.taskStatus` aceita apenas valores do enum `CRMTaskStatus`
- [ ] Tenant settings: admin com `platform.tenants.manage`consegue ler/escrever settings do próprio tenant
- [ ] Tenant settings: SuperAdmin consegue ler/escrever settings de qualquer tenant
- [ ] Tenant settings: usuário sem permissão recebe 403
- [ ] Tela `/settings/preferences` carrega com skeleton, exibe todas as 6 seções
- [ ] Toggle de tema muda imediatamente a aparência (sem reload)
- [ ] Manual save: botão "Salvar" desabilitado quando `isDirty = false`
- [ ] Guard `unsavedChangesGuard` exibe confirmação ao sair com `isDirty = true`
- [ ] Toast de sucesso após salvar; toast de erro se request falhar
- [ ] Navegação entre `/settings/profile` e `/settings/preferences` funciona
- [ ] Menu lateral exibe "Configurações do Inquilino" para admin/gerente
- [ ] Testes Pest passam (backend)
- [ ] Testes Vitest passam (frontend)
- [ ] `preferences.store.spec.ts`: `isDirty` vira `true` ao alterar; `isSaving` vira `true` durante `save()`; erro mantém `isDirty = true`
