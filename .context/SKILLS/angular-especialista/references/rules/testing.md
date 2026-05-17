# Testes Vitest

## Padrão

- Todo comportamento novo ou alterado em `app/` deve ter `*.spec.ts`.
- Use Vitest com Angular TestBed, conforme `app/vitest.config.ts` e `app/angular.json`.
- Coloque testes próximos do arquivo testado.
- Use `describe`, `it`, `expect`, `beforeEach` e `vi` de `vitest`.

## Components e pages

- Configure `TestBed` com `imports: [ComponenteStandalone]`.
- Use mocks para services de domínio; não faça chamada real de rede.
- Verifique sinais e DOM quando o comportamento for visual.
- Teste estados de loading, sucesso, erro e vazio quando existirem.

## Services

- Para HTTP, use `provideHttpClient()` e `provideHttpClientTesting()`.
- Teste URL, método, params, body, headers relevantes e tratamento de erro.
- Para services com Capacitor, isole chamadas nativas atrás de mocks.

## Stores, guards e interceptors

- Stores devem testar estado inicial, ações, erro, concorrência e computed selectors.
- Guards devem testar permissão concedida, negada e ausência de metadado.
- Interceptors devem testar headers, propagação de erro, redirecionamentos e casos de bypass.

## Cuidados

- Não dependa de ordem acidental de testes ou estado global de `localStorage`.
- Limpe mocks, timers e storage entre casos.
- Preserve stubs existentes, como alias de `lucide-angular`, quando necessário para estabilidade.
