# Mobile e Offline

## Capacitor/Ionic

- Use `app/capacitor.config.ts` para `appId`, `appName`, `webDir` e plugins configurados.
- Ambiente `mobile` usa `baseHref: "./"` e pode trocar estratégia de localização para `HashLocationStrategy`.
- Encapsule APIs Capacitor em services de plataforma para manter pages testáveis.
- Trate permissões nativas de câmera, filesystem, push, network e notifications com fluxos explícitos e mensagens claras.

## Offline

- Use services existentes de network/offline/cache/queue quando a feature envolver conectividade instável.
- Operações offline devem declarar: chave de cache, escopo por usuário/tenant, política de expiração, replay, idempotência e resolução de conflito.
- Nunca enfileire operação sensível sem confirmação clara do usuário e sem mecanismo de falha visível.
- Interceptor de offline deve sinalizar indisponibilidade sem mascarar erros reais da API.

## UX mobile

- Garanta áreas clicáveis adequadas, foco visível, teclado virtual sem cobrir ações críticas e feedback para operações demoradas.
- Evite layouts dependentes de hover.
- Teste rotas críticas no build `mobile` quando alterar navegação, assets ou recursos nativos.
