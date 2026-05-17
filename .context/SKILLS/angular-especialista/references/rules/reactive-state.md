# Estado Reativo

## Signals

- Use `signal` para estado local síncrono de UI e stores: seleção, loading, erro, dados carregados e flags.
- Use `computed` para estado derivado sem side effects.
- Evite mutar objetos/arrays em lugar; publique novo objeto/array para preservar previsibilidade.
- Não coloque chamadas HTTP dentro de `computed`.

## RxJS

- Use `Observable` para HTTP, realtime, eventos assíncronos e streams com cancelamento.
- Em componentes, finalize subscriptions com `takeUntilDestroyed(inject(DestroyRef))` ou converta para sinal quando for adequado.
- Use `switchMap` para fluxos em que a requisição anterior deve ser cancelada; `concatMap` para ordem obrigatória; `exhaustMap` para evitar submits concorrentes; `mergeMap` para paralelismo controlado.
- Use `finalize` para desligar loading e `catchError` para fallback explícito.
- Evite `subscribe` aninhado; componha pipe.

## Interop

- Ao combinar `signal` e `Observable`, defina qual camada é fonte da verdade.
- Para HTTP simples acionado por page, `Observable` no service e `signal` na page/store é o padrão local.
- Para realtime, mantenha listeners e subjects centralizados em service/store para evitar múltiplas conexões e vazamento de eventos.
