# Arquitetura Angular/Ionic

## Fontes locais

- Use `app/package.json` para versões e scripts efetivos. A configuração atual usa Angular standalone, Vitest, Capacitor, RxJS, Socket.IO, Tailwind CSS e bibliotecas visuais como `lucide-angular`, `ngx-sonner` e ApexCharts.
- Use `app/angular.json` para builders, budgets, ambientes, assets, proxy, test runner e configurações `production`, `staging`, `development` e `mobile`.
- Use `app/src/app/app.config.ts` para providers globais, interceptors, roteamento, locale, estratégia de título, ícones e providers condicionais mobile.
- Use `app/src/app/app.routes.ts` para layout, proteção de rotas e lazy loading.

## Organização

- `core/`: serviços transversais, guards, interceptors, models, realtime, estratégias, utilitários e integrações de plataforma.
- `shared/`: componentes reutilizáveis, pipes, directives, models e utilities sem dependência de domínio específico.
- `pages/`: telas roteáveis. Recursos privados da tela ficam próximos dela: `components/`, `services/`, `models/`, `*.routes.ts`, `*.spec.ts`.
- `environments/`: URLs e flags por ambiente. Nunca coloque segredos aqui.

## Princípios

- Prefira standalone components e imports explícitos no próprio componente.
- Preserve `ChangeDetectionStrategy.OnPush` em componentes novos ou alterados, salvo incompatibilidade documentada.
- Use `loadComponent` e arquivos de rotas locais para dividir áreas grandes.
- Centralize comportamento transversal em `core`; não duplique lógica de auth, permissões, notificações, realtime, offline ou tema em pages.
- Modele DTOs e responses com interfaces próximas do domínio consumidor, respeitando envelope `{ data: ... }` usado pela API.
- Não crie abstrações globais antes de haver repetição real em mais de um domínio.
