# Memory: Chat /chat usa listener legado e nao atualiza lista em ticket.new

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão |
| **Data** | 2026-04-21 |
| **Autor** | Codex (DEBUG) |
| **Contexto** | DEBUG-CHAT-EXTERNAL-TICKET-LIST |
| **Tags** | chat, websocket, realtime, ticket.new, listagem |

---

## Situação
> O que estava acontecendo? Qual o contexto?

Ao iniciar um chamado externo (webchat), o ticket era criado no backend e aparecia somente após F5 na rota `/chat`.

O backend já publicava websocket corretamente via `chat.activity` com subevent `ticket.new` (e room `tenant:{id}`), mas a página principal `/chat` estava escutando apenas o evento legado `message.received` através de `RealtimeService`.

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

Na tela `/chat`, adicionamos escuta explícita para:
- `chat.activity` com detecção de `subevents[].type === 'ticket.new'`
- `chat.ticket.new` (fallback/compat)

Quando detectado ticket novo, a tela dispara `ChatRefreshService.request()` para recarregar a listagem sem depender de F5.

Foi adicionado cooldown curto (300ms) para evitar rajadas de refresh em eventos duplicados.

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|------------|-------------------|
| Migrar toda a tela `/chat` para `ChatRealtimeService` e `ChatListStateService` imediatamente | Mudanca ampla para um bug pontual; risco maior de regressao no fluxo atual |
| Resolver apenas no backend emitindo `message.received` extra para criacao de ticket | Mantem contrato legado e perpetua desalinhamento de eventos no frontend |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Novos tickets externos entram na lista do atendente em tempo real.
- Reduz necessidade de refresh manual na operacao.
- Mantem impacto pequeno e localizado no frontend atual.

### Negativas / Trade-offs
- A tela `/chat` continua com arquitetura mista (listener legado + contrato novo).
- Ainda existe debito tecnico para consolidar em um unico stack realtime.

---

## Referências
- Arquivo: `app/src/app/pages/chat/chat.ts`
- Teste: `app/src/app/pages/chat/chat.spec.ts`
- Feature relacionada: `.context/DOCS/FEATURES/FEAT-043-websocket-realtime-fix.md`
- Changelog: `.context/DOCS/CHANGELOG/2026-04-21.md`
