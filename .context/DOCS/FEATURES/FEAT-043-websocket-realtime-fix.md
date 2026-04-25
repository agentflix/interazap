# FEAT-043 — Correção WebSocket Realtime — Chat Público

## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | FEAT-043 |
| **Nome** | Correção WebSocket Realtime — Chat Público |
| **Bounded Context** | Chat |
| **Complexidade** | G |
| **Prioridade** | Must |
| **Status** | 🟢 Aprovada para decomposição |
| **Criada em** | 2026-04-21 |
| **Última atualização** | 2026-04-21 |

---

## Resumo

Corrigir 4 bugs no pipeline de mensagens temps real do chat público para que:
1. Contato envie mensagem → agente veja em tempo real (sem F5)
2. Agente responda → contato veja em tempo real (sem refresh)
3. Respostas de IA cheguem corretamente ao frontend webchat

---

## Objetivo

Garantir que mensagens do chat público sejam entregues em tempo real via WebSocket para todos os participantes, eliminando necessidade de refresh manual (F5).

---

## Escopo

### Dentro do Escopo ✅

- [x] Adicionar broadcast realtime em `WebChatMessageController` para quando contato enviar mensagem
- [x] Adicionar broadcast realtime em `SendChatMessageAction` para mensagens outgoing do agente
- [x] Corrigir event name mismatch `webchat.ai_response` → `webchat:ai_response`
- [x] Investigar e corrigir handler `webchat:join` no gateway
- [x] Validação E2E do fluxo completo realtime

### Fora do Escopo ❌

- Alteração de schema/migration
- Novos endpoints públicos
- Funcionalidade de encerramento de ticket (FEAT-042)
- Alterações no console interno do atendente (apenas o fluxo de broadcast)

---

## Bugs Identificados

| # | Severidade | Arquivo | Problema |
|---|-----------|---------|----------|
| 1 | 🔴 HIGH | `WebChatMessageController.php` | Não emite broadcast quando contato envia mensagem |
| 2 | 🔴 HIGH | `SendChatMessageAction.php` | Não emite para outgoing (contato não recebe) |
| 3 | 🟡 MEDIUM | `WebChatRedisPublisher.php` | Event name usa ponto (`webchat.ai_response`) mas frontend usa dois pontos (`webchat:ai_response`) |
| 4 | 🔴 HIGH | `EventsGateway` | Não tem handler para `webchat:join` — webchat usa path `/ws/webchat` |

---

## Arquitetura de Mensagens (Fluxo Correto)

```
┌──────────────────────────────────────────────────────────────────────┐
│  FLUXO REALTIME CORRIGIDO                                          │
│                                                                      │
│  ┌─────────────┐    HTTP POST    ┌─────────────┐                    │
│  │   Cliente   │ ───────────────► │   Laravel   │                    │
│  │  (Angular)  │                 │  (API PHP)  │                    │
│  └─────────────┘                 └──────┬──────┘                    │
│                                         │                           │
│                         ┌───────────────┼───────────────┐           │
│                         ▼               ▼               ▼           │
│                   ┌──────────┐  ┌──────────┐  ┌─────────────┐     │
│                   │  Emit     │  │  Redis   │  │  HTTP       │     │
│                   │  via      │  │  PubSub  │  │  Fallback   │     │
│                   │  Redis    │  │  ws.events│  │             │     │
│                   └────┬─────┘  └────┬─────┘  └─────────────┘     │
│                        │            │                              │
│                        └────────────┼──────────────────────────────│
│                                     ▼                              │
│                           ┌──────────────────┐                     │
│                           │   NestJS Gateway │                     │
│                           │ (EventsGateway)  │                     │
│                           └────────┬─────────┘                     │
│                                    │                               │
│                    ┌───────────────┼────────────────┐              │
│                    ▼               ▼                ▼              │
│               tenant:{id}      ticket:{id}      session:{id}      │
│                                    │                               │
│                                    ▼                               │
│                              ┌─────────┐                          │
│                              │ Room    │                          │
│                              │ Clients │                          │
│                              └─────────┘                          │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Dependências

| Feature/Sistema | Tipo | Status | Blocker |
|-----------------|------|--------|---------|
| Redis PubSub `ws.events` | Infra | Pronta | Não |
| EventsGateway | Gateway | Pronta (falta handler) | Não |
| ChatActivityBroadcastService | Domain | Pronta | Não |

---

## Critérios de Aceite

| ID | Critério | Verificável | Status |
|----|----------|-------------|--------|
| CA-001 | Contato envia mensagem → agente vê em < 2s via WebSocket | [ ] | ❌ |
| CA-002 | Agente envia mensagem → contato vê em < 2s via WebSocket | [ ] | ❌ |
| CA-003 | Resposta de IA chega ao webchat público | [ ] | ❌ |
| CA-004 | Nenhum F5/refresh manual necessário para ver mensagens | [ ] | ❌ |
| CA-005 | Testes E2E passando cobrem o fluxo completo | [ ] | ❌ |

---

## Tasks

| Task ID | Descrição | Status |
|---------|-----------|--------|
| TASK-3.043.1 | Broadcast quando contato envia mensagem | ⏳ |
| TASK-3.043.2 | Broadcast para mensagens outgoing do agente | ⏳ |
| TASK-3.043.3 | Corrigir event name webchat.ai_response | ⏳ |
| TASK-3.043.4 | Handler webchat:join no gateway | ⏳ |
| TASK-4.043.1 | Validar listener webchat:ai_response | ⏳ |
| TASK-5.043.1 | Teste E2E fluxo realtime completo | ⏳ |

---

## Notas

- Bug #4 pode resultar em criação de gateway webchat separado OU adição de namespace ao EventsGateway existente
- Event name do Bug #3: backend usa `.` (dot), frontend usa `:` (colon) — padronizar para `:` (colon) por ser convenção socket.io mais comum
