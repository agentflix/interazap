# FEAT-040 — WebChat Widget

## 1. Objetivo

Criar um canal de chat web embedável que permite:
- Visitante acessar via URL pública (`/chat/{tenantSlug}`) ou iframe em sites externos
- Pré-chat: coleta nome + WhatsApp, busca/cria contato automaticamente
- Conversa com IA Autopilot (auto-reply)
- Atendente InteraZap vê como chat normal na interface existente

## 2. Arquitetura

```
┌─────────────────────────────────────────────────────────────────┐
│                      VISITANTE (Browser)                        │
│  Angular Page / iframe embebed                                   │
│  Socket.io client + JWT anonymous token                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      GATEWAY (NestJS)                            │
│  WebChatController                                              │
│    - POST /api/webchat/sessions (cria sessão)                    │
│    - POST /api/webchat/messages (recebe msg)                    │
│  WebChatGateway (Socket.io)                                      │
│    - Autenticação JWT (HMAC local)                               │
│    - Room management por sessionId                              │
│    - Broadcast events: webchat:ai_response                      │
│  EventFanoutService (já existe)                                  │
│    - Subscreve em Redis ws.events                                │
│    - Faz broadcast para rooms Socket.io                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      BACKEND (Laravel)                           │
│  ChatSessionController                                          │
│    - POST /api/webchat/sessions                                  │
│    - GET /api/webchat/sessions/{id}                              │
│  ChatMessageController                                           │
│    - POST /api/webchat/messages                                  │
│  ChatAutopilotResponder (já existe)                              │
│    - Dispara AiRunRequested event                               │
│  Redis Publisher (ws.events)                                    │
│    - Publica eventos para consumo Gateway                        │
└─────────────────────────────────────────────────────────────────┘
```

## 3. Data Model

### 3.1 ChatSession (nova entidade - Backend)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | uuid | PK |
| tenant_id | uuid | FK para tenant |
| contact_id | uuid | FK para contact |
| ticket_id | uuid | FK para chat_ticket |
| token | string | JWT público (não secret) |
| client_info | json | User-agent, origin_url |
| last_activity_at | timestamp | Último movimento |
| created_at | timestamp | Criação |

### 3.2 ChatTicket (atualização)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| provider | enum | Adicionar `case WEB = 'web'` |

### 3.3 Contact (busca existente)

Busca por `whatsapp` (E.164). Se não encontrar, cria novo.

## 4. API Endpoints

### 4.1 Gateway (NestJS)

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/api/webchat/sessions` | Cria sessão + contact + ticket |
| GET | `/api/webchat/sessions/{id}` | Busca sessão existente |
| POST | `/api/webchat/messages` | Recebe mensagem do visitante |
| GET | `/api/webchat/health` | Health check |

### 4.2 Backend (Laravel)

| Método | Rota | Descrição | Auth |
|--------|------|-----------|------|
| POST | `/api/webchat/sessions` | Cria/busca contact + ticket + session | - |
| GET | `/api/webchat/sessions/{id}` | Busca sessão | - |
| POST | `/api/webchat/messages` | Salva msg + dispara AI | - |
| POST | `/api/webchat/publish-event` | Publica no Redis ws.events | Internal |

## 5. WebSocket Events (Socket.io)

| Evento | Direção | Descrição |
|--------|---------|-----------|
| `webchat:join` | Client→Server | Join room com token JWT |
| `webchat:message` | Client→Server | Visitante envia mensagem |
| `webchat:ai_response` | Server→Client | Resposta da IA (via EventFanout) |
| `webchat:typing` | Server→Client | Indicador de digitação |
| `webchat:error` | Server→Client | Erros de sessão/mensagem |

## 6. JWT Structure

```json
{
  "sub": "{sessionId}",
  "tid": "{tenantId}",
  "ctid": "{contactId}",
  "ttid": "{ticketId}",
  "exp": 1234567890
}
```

- Assinado com HMAC-SHA256
- Secret: `WEBCHAT_JWT_SECRET` (compartilhado Gateway↔Backend)
- Validação: Gateway valida localmente com HMAC (não precisa de HTTP)

## 7. Redis Pub/Sub (evento existente)

**Canal:** `ws.events`

**Payload para webchat:**
```json
{
  "event": "webchat.ai_response",
  "data": {
    "sessionId": "xxx",
    "message": {
      "id": "msg-uuid",
      "content": "resposta da IA",
      "direction": "outgoing",
      "source": "ai",
      "created_at": "2026-04-12T..."
    }
  },
  "tenant_id": "tenant-uuid",
  "rooms": ["session:xxx"]
}
```

## 8. Fluxo Completo

### 8.1 Criação de Sessão

```
1. Visitante abre /chat/{tenantSlug}
2. Frontend → POST /api/webchat/sessions { name, whatsapp }
3. Backend:
   a. Busca Contact por whatsapp
   b. Se não existe → cria Contact
   c. Cria ChatTicket (provider='web')
   d. Cria ChatSession (gera JWT token)
   e. Retorna { token, sessionId, ticketId }
4. Frontend armazena token em localStorage
```

### 8.2 Conexão WebSocket

```
1. Frontend conecta Socket.io ao Gateway
2. Envia evento webchat:join com JWT no handshake
3. Gateway valida HMAC localmente
4. Se válido → join room "session:{sessionId}"
5. Se inválido → disconnect
```

### 8.3 Envio de Mensagem

```
1. Visitante digita e envia
2. Frontend → POST /api/webchat/messages { sessionId, content }
3. Backend:
   a. Valida sessão existe
   b. Cria ChatMessage (direction='incoming')
   c. Dispara ChatAutopilotResponder
   d. AI processa e salva resposta (direction='outgoing', source='ai')
   e. Publica evento em Redis ws.events
4. Gateway (EventFanoutService):
   a. Recebe evento webchat.ai_response
   b. Broadcast para room session:{sessionId}
5. Frontend recebe webchat:ai_response via Socket.io
```

### 8.4 Atendente Vê Chat Normal

```
1. ChatMessage é salvo normalmente (provider='web')
2. Frontend existente (ChatRealtimeService) recebe via WebSocket
3. Atendente vê mensagem na interface de chat normal
```

## 9. Allowed Origins

```typescript
// Gateway CORS config
allowedOrigins = [
  'https://interazap.com',
  'https://app.interazap.com',
  // + origins from config
]
```

## 10. Security

| Aspecto | Implementação |
|---------|---------------|
| JWT validation | HMAC-SHA256 local no Gateway |
| Allowed origins | Whitelist CORS no Gateway |
| Rate limiting | Socket.io middleware + HTTP rate limit |
| Message validation | Schema Zod no Gateway |

## 11. Tasks Derivadas

Consulte `.context/DOCS/TASKS/FEAT-040-tasks.md`

## 12. Riscos e Mitigações

| Risco | Mitigação |
|-------|-----------|
| Token JWT expira durante conversa | Frontend renova token antes do expiry |
| Socket.io reconnect storm | Debounce reconexões no cliente |
| AI não responde (timeout) | Timeout de 30s, retorna erro ao visitante |
| Contact duplicado | Busca por whatsapp E.164 normalizado |

## 14. Wireframes (ASCII)

### 14.1 Pré-chat (Pre-Chat Form)

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│                         ┌─────────┐                        │
│                         │  ◇◇◇◇◇  │  (logo)                 │
│                         └─────────┘                        │
│                                                             │
│                  ┌──────────────────────┐                   │
│                  │  💬 Atendimento      │                   │
│                  │      via Chat        │                   │
│                  └──────────────────────┘                   │
│                                                             │
│              Olá! Como podemos ajudar?                      │
│    Preencha seus dados abaixo para iniciar uma              │
│           conversa com nossa equipe.                        │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 👤  Seu nome                                          │   │
│  │     Digite seu nome completo                          │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 🇧🇷 +55  WhatsApp para contato                       │   │
│  │     (11) 99999-9999                                  │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │            ➜  Iniciar Conversa                      │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│              🔒 Seus dados estão protegidos                 │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 14.2 Chat Window (Janela de Chat)

```
┌─────────────────────────────────────────────────────────────┐
│  💬  Atendimento Web                    ─  □  ✕             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│                                                             │
│         ┌─────────────────────────────────┐                 │
│         │  Olá! Como posso ajudar hoje?   │                 │
│         │  14:32                          │                 │
│         └─────────────────────────────────┘                 │
│                                                             │
│                                                             │
│                    ┌─────────────────────────────────┐       │
│                    │  Preciso de ajuda com um        │       │
│                    │  pedido que fiz ontem...         │       │
│                    │  14:33                          │       │
│                    └─────────────────────────────────┘       │
│                                                             │
│                                                             │
│         ┌─────────────────────────────────┐                 │
│         │  Claro! Me conta mais detalhes  │               │
│         │  sobre o seu pedido...          │               │
│         │  14:33                          │                 │
│         └─────────────────────────────────┘                 │
│                                                             │
│                    (typing indicator ...)                   │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────┐  ┌───────────┐   │
│  │  Digite sua mensagem...              │  │   ➜       │   │
│  └─────────────────────────────────────┘  └───────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### 14.3 Embed Version (Iframe)

```
┌─────────────────────────────────────────────────────────────┐
│                         SITE EXTERNO                        │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  About Us  |  Products  |  Contact  |  Chat 💬       │   │
│  ├─────────────────────────────────────────────────────┤   │
│  │                                                     │   │
│  │   Welcome to our store!                             │   │
│  │                                                     │   │
│  │   [banner image]                                    │   │
│  │                                                     │   │
│  │   ┌───────────────────────────────┐                │   │
│  │   │                               │   FLOATING    │   │
│  │   │   produto 1    produto 2      │   CHAT WIDGET │   │
│  │   │                               │                │   │
│  │   └───────────────────────────────┘   ┌───────────┐ │   │
│  │                                        │ 💬 Chat  │ │   │
│  │   Preciso de ajuda                    └───────────┘ │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘

→ Widget flutuante (canto inferior direito) ao clicar abre:
   ┌──────────────────────────────────┐
   │  💬  Chat          ─  □  ✕        │
   ├──────────────────────────────────┤
   │  ┌────────────────────────────────┐│
   │  │ Olá! Como podemos ajudar?      ││
   │  └────────────────────────────────┘│
   │  ┌────────────────────────────────┐│
   │  │ Digite sua mensagem...     ➜  ││
   │  └────────────────────────────────┘│
   └──────────────────────────────────┘
```

### 14.4 Mobile View (Pré-chat + Chat)

```
PRÉ-CHAT MOBILE (375px):

┌─────────────────────┐
│                     │
│     ┌───────┐       │
│     │ ◇◇◇◇◇ │       │
│     └───────┘       │
│                     │
│   Atendimento       │
│   via Chat          │
│                     │
│  ┌───────────────┐  │
│  │ 👤 Nome       │  │
│  │ digite...     │  │
│  └───────────────┘  │
│                     │
│  ┌───────────────┐  │
│  │ 🇧🇷 +55       │  │
│  │ (11) 99999... │  │
│  └───────────────┘  │
│                     │
│  ┌───────────────┐  │
│  │ Iniciar Conv  ➜│  │
│  └───────────────┘  │
│                     │
└─────────────────────┘


CHAT MOBILE (375px):

┌─────────────────────┐
│ 💬 Chat    ─  □  ✕  │
├─────────────────────┤
│                     │
│    ┌────────────┐   │
│    │ Olá! Como  │   │
│    │ posso...   │   │
│    └────────────┘   │
│                     │
│ ┌──────────────┐    │
│ │ Preciso de   │    │
│ │ ajuda...     │    │
│ └──────────────┘    │
│                     │
│    ┌────────────┐   │
│    │ Claro! Me  │   │
│    │ conte...   │   │
│    └────────────┘   │
│                     │
├─────────────────────┤
│ ┌──────────────┐ ▓ │ ← (indicator typing)
│ │ Mensagem...  │ ➜ │
│ └──────────────┘   │
└─────────────────────┘
```

## 15. Dependências

- EventFanoutService (existente)
- ChatAutopilotResponder (existente)
- ChatMessageActions (existente)
- RedisPubSub (existente)
