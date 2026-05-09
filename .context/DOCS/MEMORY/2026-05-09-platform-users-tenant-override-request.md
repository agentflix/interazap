# Memory: Override indevido de tenant em request de usuário de plataforma

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Armadilha |
| **Data** | 2026-05-09 |
| **Autor** | DEV agent |
| **Contexto** | Bug em `/platform/users` criando usuário sempre no tenant InteraZap |
| **Tags** | platform, auth, request, tenant, regressao |

---

## Situação
O `AuthUserStoreRequest` (e equivalente de update) assumia que apenas super-admin sem `tenant_id` seria "platform admin". Quando o super-admin autenticado tinha `tenant_id` preenchido, o `prepareForValidation()` sobrescrevia `tenant_id` com o tenant do usuário logado, ignorando `company_id/tenant_id` enviado pela tela de plataforma.

---

## Decisão / Aprendizado
Detecção de contexto de plataforma não deve depender apenas de `tenant_id` vazio no usuário autenticado.

Para rotas `platform/users`, super-admin deve preservar o tenant selecionado no payload; para demais rotas, mantém-se o comportamento tenant-scoped.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Exigir super-admin sem `tenant_id` | Incompatível com cenários reais de super-admin associado a tenant |
| Corrigir apenas frontend | Não impede sobrescrita silenciosa no backend |

---

## Consequências
### Positivas
- Criação de usuário em `/platform/users` passa a respeitar a empresa selecionada.
- Regressão coberta por teste de feature dedicado.

### Negativas / Trade-offs
- Endpoint de update em plataforma ainda depende de ajustes adicionais na action/repository para edição cross-tenant completa.

---

## Referências
- Arquivos: `api/src/Domain/Auth/Http/Requests/AuthUserStoreRequest.php`, `api/src/Domain/Auth/Http/Requests/AuthUserUpdateRequest.php`, `api/tests/Feature/Platform/PlatformUserControllerTest.php`
