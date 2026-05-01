# MEMORY.md

## Bugs pré-existentes na suite de testes (Vitest)

- **`gate:test` falha com 14 testes** relacionados a `ResizeObserver is not a constructor`
    - Origem: `user-chat-thread.component.ts:178` chama `new ResizeObserver(...)` sem mock no `src/test-setup.ts`
    - Afeta 6 spec files; 815/829 testes passam normalmente
    - **NÃO é regressão** de Capacitor/instalação de deps — é problema de setup de jsdom + Vitest 4
    - Detectado em TASK-047.1 (instalação Capacitor 6) — Capacitor não toca em `src/`
    - Fix correto: adicionar mock de `ResizeObserver` em `src/test-setup.ts` (task separada, fora do escopo)

- **Specs com plugins Capacitor podem falhar em runtime com `property is not defined` ao usar `vi.spyOn` direto em `Camera`/`Preferences`**
    - Sintoma: `vi.spyOn(Camera, 'requestPermissions')` e `vi.spyOn(Preferences, 'get')` quebram no builder de teste
    - Contexto observado: TASK-047.12/13 (fase 6 mobile)
    - Estratégia estável: mockar wrappers do serviço (`requestCameraPermissions`, `getPhoto`, `resolvePhotoBlob`, `readFromNativeStorage`, `writeToNativeStorage`) em vez do objeto do plugin
