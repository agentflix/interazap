# Memory: Permissões de Lista de Transmissão para perfis admin

## Metadados

| Campo        | Valor                                                                    |
| ------------ | ------------------------------------------------------------------------ |
| **Tipo**     | 🧠 Decisão                                                               |
| **Data**     | 2026-04-29                                                               |
| **Autor**    | DEBUG agent                                                              |
| **Contexto** | Redirecionamento ao clicar em "Listas de Transmissão" para usuário admin |
| **Tags**     | chat, rbac, permissions, seeder, migration                               |

---

## Situação

A rota frontend `chat/transmission-list` exige `chat.transmission_lists.view`, mas o catálogo principal de permissões (`AuthPermissionSeeder`) não garantia esse conjunto. Em ambientes já provisionados, papéis administrativos ficavam sem as permissões novas e o guard redirecionava.

---

## Decisão / Aprendizado

Padronizar `chat.transmission_lists.view/create/update/delete` no seed principal e garantir concessão para papéis administrativos padrão (`inquilino`, `gerente`) no `RolePermissionSeeder`.

Para ambientes existentes, aplicar migration idempotente de backfill para criar permissões ausentes e conceder aos papéis `super-admin`, `admin`, `inquilino`, `gerente`.

---

## Evidência

- `api/database/seeders/AuthPermissionSeeder.php`
- `api/database/seeders/RolePermissionSeeder.php`
- `api/database/migrations/2026_04_29_090000_backfill_chat_transmission_list_permissions.php`
- `api/tests/Feature/AuthRbacTest.php`

---

## Consequência prática

Se surgir bug de redirecionamento em rota protegida por permissão nova, validar primeiro:

1. se a permissão está no `AuthPermissionSeeder` principal,
2. se os papéis padrão recebem a permissão,
3. se existe backfill para bases já ativas.
