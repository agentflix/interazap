# PLAN-010 — Bugfix: Ocultar `super-admin` de inquilinos na tela de usuários

## Objetivo

Ocultar o perfil `super-admin` da lista de roles retornada pela API `GET /api/auth/roles` quando o usuário autenticado **não for** um super-admin. Assim, a tela `/settings/users` jamais exibirá a opção `super-admin` para inquilinos.

## Módulo relacionado

Auth | Platform

## PRD relacionado

N/A (bugfix de segurança)

## Problema

Na tela `/settings/users` (`SettingsUserFormComponent`), o dropdown de perfis de acesso (`roleOptions`) carrega **todos** os roles via `GET /api/auth/roles`. Inquilinos (usuários com role `inquilino`, `gerente`, etc.) enxergam e podem atribuir o perfil `super-admin` a outros usuários — o que viola o princípio de menor privilégio.

## Escopo

### Incluído

**Camada 1 — Filtragem na listagem (evita exposição no UI):**
- Filtrar `super-admin` da listagem de roles na API (`AuthRoleActions::list()`) quando o usuário logado **não for** super-admin
- Garantir que a mesma lógica se aplique ao endpoint de roles (página de gestão de roles em `/settings/roles`)

**Camada 2 — Validação na atribuição (evita API direta):**
- `AuthUserActions::create()` — rejeitar atribuição de `super-admin` se o usuário autenticado **não for** super-admin
- `AuthUserActions::update()` — mesma rejeição na atualização
- `AuthUserStoreRequest` — validação de entrada no FormRequest (defense-in-depth)
- `AuthUserUpdateRequest` — validação de entrada no FormRequest (defense-in-depth)

- Testar que super-admin continua vendo e atribuindo todos os roles

### Excluído

- Alteração no frontend (filtragem é 100% backend)
- Criação de migration — não há alteração de schema

## Etapas propostas

### Etapa 1 — Backend: Modificar `AuthRoleActions::list()`

**Arquivo:** `api/src/Domain/Auth/Actions/AuthRoleActions.php`

**Mudança:** Adicionar parâmetro `$excludeSuperAdmin bool` ao método `list()`. Quando `true`, exclui `AuthRole::SUPER_ADMIN` da query.

```php
public function list(AuthRoleFiltersDTO $filters, bool $excludeSuperAdmin = false): LengthAwarePaginator
{
    $query = AuthRole::query()
        ->withCount(['permissions', 'users'])
        ->with('permissions:id,name');

    if ($filters->search !== null && $filters->search !== '') {
        $query->where('name', 'ilike', SearchSanitizer::likeContains($filters->search));
    }

    if ($excludeSuperAdmin) {
        $query->where('name', '!=', AuthRole::SUPER_ADMIN);
    }

    return $query
        ->orderBy($filters->sanitizedSortBy(), $filters->sanitizedSortDirection())
        ->paginate($filters->sanitizedPerPage());
}
```

**Nota:** Optou-se por adicionar um parâmetro em vez de inferir o usuário internamente, mantendo o actions stateless e testável.

### Etapa 2 — Backend: Modificar `AuthRoleController::index()`

**Arquivo:** `api/src/Domain/Auth/Http/Controllers/AuthRoleController.php`

**Mudança:** Passar para o actions a informação de se o usuário logado é super-admin.

```php
public function index(Request $request): JsonResponse
{
    $this->authorize('viewAny', AuthRole::class);

    $filters = AuthRoleFiltersDTO::fromArray($request->only([
        'search', 'sort_by', 'sort_direction', 'per_page',
    ]));

    $excludeSuperAdmin = !$request->user()->isSuperAdmin();
    $paginator = $this->actions->list($filters, $excludeSuperAdmin);
    $paginator->getCollection()->transform(fn ($item) => new AuthRoleResource($item));

    return $this->paginated($paginator, 'Perfis listados com sucesso');
}
```

**Dependência:** `AuthUser` já tem `isSuperAdmin()` — nenhum model novo necessário.

### Etapa 3 — Backend: Garantir consistência em `permissions()`

**Arquivo:** `api/src/Domain/Auth/Http/Controllers/AuthRoleController.php`

O método `permissions()` lista permissões agrupadas. Não há necessidade de filtro aqui pois não expõe roles diretamente. Manter como está.

### Etapa 4 — Verificar Policy existente

**Arquivo:** `api/src/Domain/Auth/Policies/AuthRolePolicy.php`

Verificar se o gate `viewAny` para `AuthRole` já impede inquilinos de acessar a página de roles. Se não, ajustar. Esse é um belt-and-suspenders — a camada de listagem já filtra, mas a policy deve também controlar o acesso ao endpoint inteiro.

### Etapa 5 — Backend: Gate + Testes

Rodar `composer gate:all` em `api/`. Se houver falha, ajustar.

Se não houver teste existente para `AuthRoleActions::list()` com filtro, criar:

**Arquivo:** `api/tests/Feature/AuthRoleTest.php` (ou adicionar a arquivo existente)

```php
it('excludes super-admin role for non-super-admin users', function () {
    $tenantUser = actingAsTenantUser(); // helper a verificar

    $response = apiAs($tenantUser)->getJson('/api/auth/roles');

    $response->assertOk();
    $roles = $response->json('data');
    expect(array_column($roles, 'name'))->not->toContain('super-admin');
});

it('includes super-admin role for super-admin users', function () {
    $superAdmin = actingAsSuperAdmin();

    $response = apiAs($superAdmin)->getJson('/api/auth/roles');

    $response->assertOk();
    $roles = $response->json('data');
    expect(array_column($roles, 'name'))->toContain('super-admin');
});
```

### Etapa 6 — Frontend: Validação visual (opcional, mas recomendado)

Após o fix backend, verificar manualmente em `/settings/users` com um usuário inquilino que `super-admin` não aparece na lista de perfis. Não há mudança de código necessária no frontend — a API filtrada reflete automaticamente no componente existente.

## Arquivos a modificar

### Backend (Laravel)

| Arquivo | Ação | Detalhe |
|---------|------|---------|
| `api/src/Domain/Auth/Actions/AuthRoleActions.php` | modificar | Adicionar `$excludeSuperAdmin` em `list()` + where clause |
| `api/src/Domain/Auth/Http/Controllers/AuthRoleController.php` | modificar | Passar `!$request->user()->isSuperAdmin()` para `actions->list()` |

## Tarefas derivadas

| Task | Descrição | Agente | Depende de |
|------|-----------|--------|------------|
| TASK-010-BE | Modificar `AuthRoleActions::list()` + `AuthRoleController::index()` | @BACKEND | — |
| TASK-010-TEST | Criar teste Pest para filtro `super-admin` | @BACKEND | TASK-010-BE |
| TASK-010-QA | Verificar manualmente com inquilino e super-admin | @QA | TASK-010-BE |

## Riscos e dependências

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Incompatibilidade com overload de `AuthRoleActions::list()` em outros callers | Baixa | Alto | Verificar todos os callers de `->list()` antes de alterar assinatura |
| Super-admin perde acesso acidental ao `super-admin` na listagem | Muito baixa | Alto | Teste específico `it('includes super-admin role for super-admin users')` |

## Evidências da code inspection

- `AuthRole::SUPER_ADMIN = 'super-admin'` — constante já definida em `AuthRole.php:7`
- `AuthUser::isSuperAdmin()` — método já existe em `AuthUser.php`
- `AuthRoleActions::list()` — único caller: `AuthRoleController::index()` (确认ar se há outros)
- `SettingsUserFormComponent::loadRoles()` — chama `roleService.listAll()` → `/api/auth/roles` → sem filtro local

## Validação

- [ ] Backend: `composer gate:all` em `api/`
- [ ] Frontend: `pnpm run gate:all` em `app/`
- [ ] Teste Pest passa (exclui super-admin para inquilino, inclui para super-admin)
- [ ] Teste manual: `/settings/users` com usuário inquilino não mostra `super-admin`
- [ ] Teste manual: `/settings/users` com super-admin continua mostrando `super-admin`

## Estimativa

| Item | Valor |
|------|-------|
| Complexidade | Baixa |
| Camadas afetadas | Backend (1 camada) |
| Migrações necessárias | Nenhuma |
| Arquivos modificados | 2 (ações + controller) |
| Impacto em módulos existentes | Mínimo — Auth |
