# Memory: Exclusão seletiva de role de sistema na listagem pública

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Aprendizado |
| **Data** | 2026-05-09 |
| **Autor** | DEV agent |
| **Contexto** | Correção da tela `/platform/users` após hardening de `Administrador` |
| **Tags** | auth, roles, formulario, regressao |

---

## Situação
Ao endurecer a regra para ocultar `Administrador` na listagem de roles, o filtro aplicado no backend removeu todas as roles de sistema por ID (`Administrador`, `Inquilino`, `Gerente`, `Atendente`), deixando o formulário sem opções.

---

## Decisão / Aprendizado
Para listagem pública de roles no formulário, a exclusão deve ser seletiva apenas para `Administrador`.

Bloqueio de atribuição de `Administrador` permanece no guard de usuários; a listagem não deve esconder roles legítimas do tenant.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter exclusão de todas as roles de sistema | Quebra funcional da UI e impede gestão normal de usuários |
| Exibir `Administrador` novamente para super-admin | Reabre superfície de erro operacional e risco de elevação indevida |

---

## Consequências
### Positivas
- Formulário volta a exibir `Inquilino`, `Gerente` e `Atendente`.
- Regra de segurança crítica (`Administrador` não atribuível via API) continua intacta.

### Negativas / Trade-offs
- Necessidade de testes explícitos para evitar regressão de filtro em futuras mudanças de RBAC.

---

## Referências
- Arquivos: `api/src/Domain/Auth/Actions/AuthRoleActions.php`, `api/tests/Feature/AuthRoleControllerTest.php`
- Memory relacionada: `.context/DOCS/MEMORY/2026-05-09-bloqueio-role-administrador-api-ui.md`
