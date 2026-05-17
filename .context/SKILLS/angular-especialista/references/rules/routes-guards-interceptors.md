# Routes, Guards e Interceptors

## Routes

- Use rotas standalone com `loadComponent` para pages e `children` para áreas internas.
- Toda rota visível deve ter `data.title` compatível com `AppTitleStrategy`.
- Aplique `MainLayoutComponent` a áreas autenticadas e `AuthLayoutComponent` a fluxos públicos de autenticação, seguindo `app.routes.ts`.
- Coloque permissões em `data.permission` ou chaves já usadas pelo guard existente.

## Guards

- Guards funcionais (`CanActivateFn`, `CanMatchFn`, `CanDeactivateFn`) devem usar `inject()`.
- Guards de permissão melhoram UX, mas não substituem autorização backend.
- Retorne `UrlTree` para redirecionamento em vez de navegar manualmente quando possível.
- Para alterações com risco de perda de dados, use o padrão de `unsaved-changes.guard`.

## Interceptors

- Interceptors ficam em `core/interceptors` e são registrados em `app.config.ts` na ordem intencional.
- Preserve tratamento existente de trace, timeout, offline, API key do Gateway, bearer, auth e billing lockout.
- Interceptors não devem acoplar regra de negócio de page. Eles tratam autenticação, headers, observabilidade, erros transversais e conectividade.
- Ao adicionar header, garanta que não exponha segredo e que respeite CORS/proxy.
- Teste interceptors com `provideHttpClient`, `withInterceptors` e `provideHttpClientTesting`.
