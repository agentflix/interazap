---
name: angular-especialista
description: Especialista Angular/Ionic do InteraZap. Use quando Codex precisar implementar, revisar ou planejar frontend em app/ com Angular standalone, Ionic/Capacitor, Vitest, rotas, guards, interceptors, services, stores com signal/Observable, integração API/Gateway, offline/mobile, acessibilidade, performance, bundle e gates específicos deste repositório.
---

# Angular Especialista

## Metadados

- Autor: Rafael Silva
- Versão: 1.0

## Fluxo

Use esta skill para qualquer trabalho em `app/`. Antes de implementar, leia a task ou documentação funcional correspondente quando existir, MEMORY relevante em `.context/DOCS/MEMORY/`, o workflow em `.context/SKILLS/workflow-prevc/references/prevc.md` e os gates em `.context/SKILLS/workflow-prevc/references/validation-flow.md`.

Quando houver divergência entre documentação geral e configuração local, trate os arquivos do workspace como fonte operacional: `app/package.json`, `app/angular.json`, `app/vitest.config.ts`, `app/src/app/app.config.ts`, `app/src/app/app.routes.ts`, `app/capacitor.config.ts` e `app/src/environments/*`.

## Regras

- Responda em português brasileiro formal e preserve nomes técnicos como `signal`, `Observable`, `guard`, `interceptor`, `Gateway`, `Socket.IO` e `Vitest`.
- Siga a arquitetura existente em `app/src/app`: `core/` para serviços transversais, `shared/` para componentes/utilitários reutilizáveis, `pages/` para telas e seus recursos locais.
- Prefira componentes standalone, `inject()`, `ChangeDetectionStrategy.OnPush`, lazy loading por `loadComponent`/rotas filhas e estado explícito com `signal`, `computed` e `Observable`.
- Todo comportamento novo deve ter teste Vitest (`*.spec.ts`) no mesmo domínio do arquivo alterado.
- Frontend nunca acessa banco diretamente; integração ocorre via API Laravel (`environment.apiUrl`) e Gateway (`environment.gateway`) por contratos existentes.
- Segurança de tenant, permissões e autorização é responsabilidade final do backend; o app apenas reflete UX, reduz chamadas indevidas e trata respostas `401`, `402` e `403`.
- Não adicione segredos a `environment*`, interceptors, services, assets ou testes. Chaves públicas/configurações devem ser documentadas e inofensivas.
- Rode `pnpm --filter app test && pnpm --filter app build` quando a task tocar `app/`; se aplicável, rode também lint/quality local. Se não rodar, registre o motivo.

## Regras Por Tema

Carregue apenas os arquivos necessários conforme a demanda em `references/rules/`:

- `references/rules/architecture.md`: estrutura do app, responsabilidades de `core`, `shared`, `pages`, ambientes e fronteiras.
- `references/rules/components-pages.md`: componentes, pages, templates, formulários, layout, UI Kit e padrões visuais.
- `references/rules/services-stores.md`: services, stores, modelos, cache local, side effects e organização de dependências.
- `references/rules/reactive-state.md`: `signal`, `computed`, `Observable`, RxJS, lifecycle, limpeza de inscrições e concorrência.
- `references/rules/routes-guards-interceptors.md`: rotas standalone, guards, interceptors HTTP, títulos, permissões e tratamento de erro.
- `references/rules/security.md`: hardening frontend, auth, tokens, XSS, dados sensíveis, permissões e multi-tenancy.
- `references/rules/api-gateway-integration.md`: contratos REST, envelopes, paginação, erros, Socket.IO, Gateway e fronteira App/API/Gateway.
- `references/rules/mobile-offline.md`: Ionic/Capacitor, mobile, offline, fila local, storage, notificações e diferenças web/mobile.
- `references/rules/performance-bundle.md`: performance Angular, bundle budgets, lazy loading, listas, charts, assets e build.
- `references/rules/accessibility-ux.md`: acessibilidade, semântica, foco, teclado, contraste, estados vazios, loading e erros.
- `references/rules/testing.md`: Vitest, Angular TestBed, mocks, HttpTesting, stores, guards, interceptors e componentes.
- `references/rules/validation.md`: gates obrigatórios, evidências e comandos de validação do app.
