# Memory: Logger do Gateway — modo DEV colorido vs PROD estruturado

## Metadados

| Campo        | Valor                                                 |
| ------------ | ----------------------------------------------------- |
| **Tipo**     | ⚠️ Armadilha                                          |
| **Data**     | 2026-04-21                                            |
| **Autor**    | DEBUG Agent                                           |
| **Contexto** | Poluição de logs no console após integração Telegram  |
| **Tags**     | gateway, logger, observabilidade, websocket, telegram |

---

## Situação

Após integração do Telegram, o console local do gateway passou a exibir apenas JSON estruturado, sem cores, com alta verbosidade em eventos de conexão WebSocket.

---

## Decisão / Aprendizado

O bootstrap do Nest estava forçando `StructuredLoggerService` global via `app.useLogger(...)`, o que remove o formatter colorido padrão do Nest no terminal local.

Decisão aplicada:

- Dev: usar logger padrão do Nest (colorido) por padrão.
- Prod: usar logger estruturado JSON por padrão.
- Controlar via env:
    - `LOG_STRUCTURED` (`false` dev, `true` prod)
    - `LOG_LEVEL` (`log` por padrão)

Também foi reduzido ruído esperado de autenticação (`JWT -> Sanctum`) de `debug` para `verbose`.

---

## Alternativas Consideradas

| Alternativa                                          | Por que descartada                                                                      |
| ---------------------------------------------------- | --------------------------------------------------------------------------------------- |
| Manter logger JSON estruturado sempre ativo          | Péssima DX local para troubleshooting rápido; saída excessivamente verbosa no terminal. |
| Remover logs do realtime gateway indiscriminadamente | Perde observabilidade útil em cenários de incidente.                                    |

---

## Consequências

### Positivas

- Console local volta a ser legível e colorido.
- Time pode ligar/desligar logger estruturado sem alterar código.
- Menos ruído durante handshakes WebSocket comuns.

### Negativas / Trade-offs

- Com `LOG_STRUCTURED=false`, tracing estruturado no console local fica menos rico (permanece disponível ao ativar a flag).

---

## Referências

- `gateway/src/main.ts`
- `gateway/src/domains/realtime/services/ws-authentication.service.ts`
- Feature: `.context/DOCS/FEATURES/PLAN-041-telegram-integration.md`
