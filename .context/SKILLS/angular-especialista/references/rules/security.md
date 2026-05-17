# Segurança Frontend

## Autenticação e sessão

- Siga `AuthService`, `AuthStoreService`, `AuthStorageService` e interceptors existentes para tokens, cookies e logout.
- Web usa cookies HttpOnly quando o backend emite sessão; mobile pode usar token persistido por storage de plataforma.
- Não grave tokens em logs, mensagens de erro, query string ou analytics.
- Impersonação deve ficar explícita no estado e nunca misturar tokens original/alvo sem fluxo existente.

## Autorização e tenant

- Guards e menus escondem funcionalidades, mas a API é a autoridade.
- Nunca confie em `tenant_id`, permissões ou plano vindos do cliente para autorizar ação.
- Não envie `tenant_id` manualmente se o contrato backend inferir por sessão; quando contrato exigir, valide que vem de estado autenticado e não de input arbitrário.

## XSS e dados sensíveis

- Evite `innerHTML`; quando inevitável, sanitize e documente a origem.
- Não renderize HTML vindo de usuário sem sanitização.
- Não exponha segredos em `environment*`, assets, bundle, storage ou testes.
- Trate anexos, previews e mídia como conteúdo não confiável.

## Erros

- Mensagens ao usuário devem ser úteis sem revelar stack trace, tokens, IDs internos desnecessários ou detalhes de infraestrutura.
- Logs de console em produção devem ser mínimos e nunca conter payload sensível.
