# Inconsistência de serialização REST vs WebSocket

**Tipo:** Aprendizado / Armadilha
**Data:** 2026-05-19
**Autor:** BUILDER (fix-webchat-message-order)
**Tags:** webchat, websocket, serialization, snake_case, camelCase, angular

## Situação

Durante a correção do bug de ordenação de mensagens no webchat público, descobrimos que:

- O **Backend REST** (Laravel) retorna mensagens em `camelCase`: `{ createdAt: '...', fileUrl: '...' }`
- O **Gateway WebSocket** (NestJS) emite mensagens em `snake_case`: `{ created_at: '...', file_url: '...' }`

Isso criou uma inconsistência: o frontend mapeava `fileUrl`, `mimeType`, `fileName` explicitamente, mas esqueceu de mapear `createdAt`. O spread `...(msgData as unknown as WebChatMessage)` não faz a conversão snake_case → camelCase, deixando `createdAt` como `undefined`.

## Decisão / Aprendizado

1. **Sempre mapear explicitamente** campos de payload externo, nunca confiar no spread com cast forçado.
2. **Padronizar serialização no gateway** para camelCase (igual ao REST) eliminaria a necessidade de mapeamento duplo no frontend.
3. **O spread `as unknown as Type`** é um anti-pattern que esconde campos não mapeados. Preferir mappers explícitos ou normalização de payload.

## Alternativas Consideradas

| Alternativa | Por que descartada |
|---|---|
| Tornar `createdAt` opcional no tipo | Quebraria contratos em múltiplos lugares; ordenação depende do campo |
| Normalizar no backend (Laravel) | Fora do escopo do bugfix; gateway é quem emite snake_case |
| Usar biblioteca de case-conversion (lodash) | Adicionaria dependência desnecessária para um único arquivo |

## Consequências

- **Positivas:** Bug corrigido; mapeamento defensivo com fallback `new Date().toISOString()` garante que ordenação nunca quebre.
- **Negativas / Trade-offs:** Fallback usa clock do cliente — risco teórico de ordenação incorreta se relógio desincronizado (aceitável para bugfix).
- **Ação necessária:** Refatorar `webchat.service.ts` para extrair método privado de mapeamento; considerar padronizar gateway para camelCase.

---

# Ordenação de mensagens e layout visual

**Tipo:** Aprendizado / Armadilha
**Data:** 2026-05-19
**Autor:** BUILDER (fix-webchat-message-order)
**Tags:** chat, ordenação, flexbox, scroll, layout, angular

## Situação

No chat interno de agente, o commit `f8e86b7` alterou `mergeAndSortMessagesAsc` → `mergeAndSortMessagesDesc` sem considerar o layout visual. O componente usa `flex-col` + `scrollToBottom`, onde:

- **ASC:** mensagem antiga no índice 0 = topo do DOM → scroll para o fundo mostra mensagem nova ✅
- **DESC:** mensagem nova no índice 0 = topo do DOM → scroll para o fundo mostra mensagem antiga ❌

## Decisão / Aprendizado

**A ordenação de dados deve sempre considerar o componente de renderização.** Nunca alterar sort sem validar contra:
1. Direção do flex (`flex-col` vs `flex-col-reverse`)
2. Comportamento de scroll (`scrollToBottom` vs `scrollToTop`)
3. Expectativa do usuário (mais recente no topo ou no fundo?)

## Consequências

- **Positivas:** Ordenação corrigida para ASC; layout visual restaurado.
- **Ação necessária:** Adicionar teste de integração que valide ordenação + scroll behavior.
