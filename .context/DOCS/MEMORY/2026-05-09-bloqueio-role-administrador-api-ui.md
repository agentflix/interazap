# Memory: Bloqueio da role Administrador fora de seeders

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-09 |
| **Autor** | DEV agent |
| **Contexto** | Hardening da tela `/platform/users` para impedir elevação de privilégio via UI/API |
| **Tags** | auth, roles, seguranca, rbac, platform-users |

---

## Situação
A API permitia que super-admin atribuísse a role `Administrador` por endpoints autenticados de usuários. Além disso, a listagem de roles devolvia `Administrador` para super-admin e a UI renderizava a opção sem filtro.

---

## Decisão / Aprendizado
Bloquear `Administrador` para qualquer fluxo autenticado de atribuição de roles (`create`, `update`, `syncRoles`, `removeRole`) e remover `Administrador` da listagem pública de roles para todos os perfis, mantendo apenas criação pré-provisionada por seeders/migrations.

No frontend, manter filtro explícito de `Administrador` em `roleOptions` como defesa em profundidade.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter permissão para super-admin atribuir `Administrador` | Mantém vetor de escalonamento horizontal de privilégio via UI/API |
| Bloquear apenas no frontend | Não protege chamadas diretas à API e não atende requisito de segurança |

---

## Consequências
### Positivas
- Elimina criação de novos usuários `Administrador` por qualquer usuário autenticado.
- Reduz exposição acidental de role crítica na interface.
- Mantém provisionamento controlado de administradores por seed/migration.

### Negativas / Trade-offs
- Operações legítimas de emergência para promover usuário a `Administrador` passam a depender de fluxo operacional fora da API autenticada (seed/migration/script controlado).

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-001-roles-uuid-fixos.md`
- Arquivos: `api/src/Domain/Auth/Actions/AuthUserActions.php`, `api/src/Domain/Auth/Http/Controllers/AuthRoleController.php`, `app/src/app/pages/platform/users/platform-users.ts`
