# FEATURE — Modal de Usuários por Role

> **ID:** FEAT-002
> **Status:** Planned
> **Criado em:** 2026-05-05
> **Workspace:** `api/` + `app/`

---

## 📋 RESUMO

Tornar o badge "Usuários" na listagem de roles clicável, abrindo um modal com tabela paginada dos usuários que possuem aquela role, com ações de **editar**, **desvincular** e **inativar**.

---

## 🗺️ MAPA DE IMPACTO

| Workspace | Modificados | Novos |
|-----------|-------------|-------|
| `api/` | 6 | 0 |
| `app/` | 5 | 1 |

### Arquivos

| # | Arquivo | Tipo | Mudança |
|---|---------|------|---------|
| 1 | `api/src/Domain/Auth/Repositories/EloquentAuthUserRepository.php` | MODIFY | Filtro `role` no `paginate()` |
| 2 | `api/src/Domain/Auth/Actions/AuthRoleActions.php` | MODIFY | Método `listUsersByRole()` |
| 3 | `api/src/Domain/Auth/Actions/AuthUserActions.php` | MODIFY | Método `removeRole()` |
| 4 | `api/src/Domain/Auth/Http/Controllers/AuthRoleController.php` | MODIFY | Método `users()` |
| 5 | `api/src/Domain/Auth/Routes/auth.php` | MODIFY | Rota `GET /roles/{id}/users` |
| 6 | `api/src/Domain/Auth/Policies/AuthRolePolicy.php` | MODIFY | Método `viewUsers()` |
| 7 | `api/tests/Feature/AuthRoleControllerTest.php` | MODIFY | Testes do endpoint |
| 8 | `app/src/app/core/services/role.service.ts` | MODIFY | Método `listUsersByRole()` |
| 9 | `app/src/app/core/models/role.model.ts` | MODIFY | Interface `RoleUser` |
| 10 | `app/src/app/pages/auth/roles/roles.ts` | MODIFY | Estado/handlers do modal |
| 11 | `app/src/app/pages/auth/roles/roles.html` | MODIFY | Badge clicável + modal |
| 12 | `app/src/app/core/services/user.service.ts` | MODIFY | Bug fix toggle |
| 13 | `app/src/app/pages/auth/roles/components/role-users-modal/` | CREATE | Componente dedicado |

---

## 📐 DECISÕES ARQUITETURAIS

### D1 — Endpoint dedicado `GET /auth/roles/{id}/users`
Criar no `AuthRoleController` (não reusar `AuthUserController::index`). Segue DDD — cada bounded context expõe seus recursos. Autorização é `viewUsers` na role, não `viewAny` em users.

### D2 — Filtro `role` no repositório
O `AuthUserFiltersDTO` já tem `role` mas o repositório ignora. Corrigir isso resolve dívida técnica e serve além deste feature.

### D3 — "Desvincular" remove apenas a role alvo
Criar `AuthUserActions.removeRole()` que carrega roles atuais, remove a target, e faz `syncRoles()` com o restante. Não usar `syncRoles([])` que removeria TODAS.

### D4 — Componente dedicado `role-users-modal`
Modal tem estado próprio (paginação, loading, ações). Segue padrão existente com `role-form`.

### D5 — Reuso do `AuthUserResource`
Nenhuma transformação adicional necessária. Já retorna `roles`, `is_active`, `name`, `email`.

---

## ⚠️ PONTOS DE ATENÇÃO

1. **Bug existente:** `UserService.toggleActive()` chama `PATCH /toggle-active` mas backend espera `POST /toggle`
2. **Autorização:** `viewUsers()` segue `viewAny()` — apenas super admins inicialmente
3. **users_count:** Atualiza no próximo `loadRoles()` após fechar modal
4. **Proteção super-admin:** `toggleActive()` e `removeRole()` devem proteger super-admins
