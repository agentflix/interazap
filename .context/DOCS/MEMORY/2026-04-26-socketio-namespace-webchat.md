# MEMORY — Socket.IO Namespace: cliente deve incluir namespace na URL

**Data:** 2026-04-26
**Contexto:** Bug 4 — WebSocket webchat sem atualização em tempo real em produção

---

## Decisão / Aprendizado

Em Socket.IO v4, **namespace e path são conceitos distintos**:

| Conceito | O que é | Como configurar (cliente) |
|----------|---------|--------------------------|
| `path`   | Caminho HTTP do servidor socket.io | `io(url, { path: '/ws' })` |
| `namespace` | Canal lógico dentro do servidor | Incluído na **URL**: `io('https://host/webchat', ...)` |

O `path` controla onde o servidor atende as requisições HTTP de handshake.
O `namespace` controla qual gateway NestJS recebe a conexão.

**Exemplo correto:**
```typescript
// Conecta ao namespace /webchat, HTTP path /ws
io('https://gateway.interazap.com.br/webchat', { path: '/ws' })
```

**Erro que ocorreu:**
```typescript
// Conectava ao namespace / (raiz = EventsGateway), NÃO ao /webchat
io('https://gateway.interazap.com.br', { path: '/ws' })
```

---

## Por que o bug existia

O comentário em `webchat.gateway.ts` dizia:
> "O namespace '/webchat' combinado com path '/ws' resulta em '/ws/webchat'"

Isso é **tecnicamente incorreto**. O namespace não afeta o path HTTP — ele é negociado via protocolo Socket.IO após o handshake. O path HTTP é sempre `/ws/socket.io` independente do namespace.

---

## Regra para o futuro

> **Todo novo gateway WebSocket com namespace customizado exige que o cliente Angular inclua o namespace na URL do `io()`.**

Se o NestJS tiver:
```typescript
@WebSocketGateway({ namespace: '/foo', path: '/ws' })
```

O cliente Angular DEVE usar:
```typescript
io(`${apiBase}/foo`, { path: '/ws' })
```

---

## Arquivos afetados

- `app/src/app/pages/webchat/services/webchat.service.ts` — fix aplicado
- `gateway/src/domains/realtime/gateways/webchat.gateway.ts` — comentário incorreto (não corrigido, baixo risco)
- `gateway/src/domains/realtime/services/event-fanout.service.ts` — correto, emite via `webChatGateway` para namespace `/webchat`
