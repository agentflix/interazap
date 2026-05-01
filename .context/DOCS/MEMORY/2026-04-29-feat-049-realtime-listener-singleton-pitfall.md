# MEMORY — Singleton service com flag `started` quebra remount (FEAT-049)

**Data:** 2026-04-29
**Contexto:** FEAT-049 (decomposição do host `chat.ts`)
**Tipo:** Aprendizado / Anti-padrão

## Decisão

Em services Angular `@Injectable({ providedIn: 'root' })` que expõem método `start(destroyRef)` para subscrever observables, **NÃO usar flag interno (`private started = false`) com early-return**.

O flag transforma o singleton em "ouvinte dormente" após o primeiro destroy: o `takeUntilDestroyed(destroyRef)` desfaz as subscriptions corretamente, mas o early-return impede que um remount do componente reassine. Resultado: a tela de Chat reabre e nenhum evento de realtime chega à UI.

## Padrão correto

```ts
@Injectable({ providedIn: 'root' })
export class ChatRealtimeListenerService {
  start(destroyRef: DestroyRef): void {
    this.realtime.connect(); // idempotente no nível do Socket.IO

    this.realtime
      .on(EVENT)
      .pipe(takeUntilDestroyed(destroyRef))
      .subscribe(...);
    // ... idem para outros eventos
  }
}
```

- O singleton sobrevive entre remounts do componente — isto está correto e desejável (mantém estado de cooldown, contadores, etc.).
- Cada chamada `start()` cria subscriptions novas ligadas ao DestroyRef do caller atual.
- `realtime.connect()` é idempotente no Socket.IO client; chamar várias vezes não duplica conexão.

## Anti-padrão (não fazer)

```ts
private started = false;

start(destroyRef: DestroyRef): void {
  if (this.started) return; // ❌ quebra remount
  this.started = true;       // ❌ nunca é resetado
  // subscriptions...
}
```

## Sintoma

- Primeira navegação para `/chat`: tudo funciona.
- Sair e voltar para `/chat`: lista de tickets não atualiza por realtime; som de notificação para de tocar.
- Sem erro no console — o flag silenciosamente skippa o re-subscribe.

## Como detectar

Test de regressão obrigatório em qualquer service que use o pattern `start(destroyRef)`:

```ts
it('re-subscribes after the previous DestroyRef is destroyed (remount)', () => {
  service.start(destroyRef);
  const initialOnCalls = realtime.on.mock.calls.length;
  destroyRef.destroy();

  const nextDestroyRef = createDestroyRef();
  service.start(nextDestroyRef);

  expect(realtime.on.mock.calls.length).toBe(initialOnCalls * 2);
  // dispara evento e valida side effect
});
```

## Alternativas consideradas

1. **Trocar para `providedIn: <Component>`** (instância por componente): também resolve, mas perde estado entre remounts (cooldown timestamps zerados). Rejeitado.
2. **Resetar flag em `destroyRef.onDestroy(...)`**: funciona mas adiciona complexidade desnecessária. O `takeUntilDestroyed` já cuida do cleanup; o flag só introduz bug.

## Arquivos de referência

- `app/src/app/pages/chat/services/chat-realtime-listener.service.ts`
- `app/src/app/pages/chat/services/chat-realtime-listener.service.spec.ts` (teste de regressão)
- FEAT-049: `.context/DOCS/FEATURES/FEAT-049-chat-host-decomposition.md`
