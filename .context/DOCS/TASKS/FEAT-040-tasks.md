# FEAT-040 — WebChat Widget Tasks

## Tarefas Derivadas

---

## FASE 3: Backend

---

### TASK-3.040.1 — ChatSession Entity + Migration

**T: Tarefa**
Criar entidade ChatSession para gerenciar sessões do chat web (webchat).

**A: Arquivo**
- `api/src/Domain/Chat/Models/ChatSession.php`
- `api/database/migrations/YYYY_MM_DD_HHMMSS_create_chat_sessions_table.php`

**C: Comportamento**
- Antes: Não existe ChatSession
- Depois: Entidade existe com campos: id, tenant_id, contact_id, ticket_id, token, client_info, last_activity_at, created_at

**E: Evidência**
- Migration executa com sucesso
- ChatSession::find() funciona
- Tests: create, find, update last_activity_at

---

### TASK-3.040.2 — ProviderType Web Enum

**T: Tarefa**
Adicionar `case WEB = 'web'` ao ProviderType enum.

**A: Arquivo**
- `api/src/Domain/Chat/Enums/ProviderType.php`

**C: Comportamento**
- Antes: ProviderType tem UAZAPI, ZAPI, META
- Depois: ProviderType tem UAZAPI, ZAPI, META, WEB

**E: Evidência**
- Test: ProviderType::WEB->value === 'web'
- ChatTicket cria com provider='web' sem erro

---

### TASK-3.040.3 — ChatSessionController

**T: Tarefa**
Criar controller para endpoints de sessão webchat.

**A: Arquivo**
- `api/src/Domain/Chat/Http/Controllers/WebChatSessionController.php`
- `api/src/Domain/Chat/Routes/webchat.php`

**C: Comportamento**
- Antes: Não existem endpoints webchat
- Depois:
  - POST /api/webchat/sessions → cria/busca contact + ticket + session, retorna JWT token
  - GET /api/webchat/sessions/{id} → retorna sessão

**E: Evidência**
- POST retorna { token, sessionId, ticketId }
- GET retorna sessão com contact + ticket
- Test: criar sessão, buscar sessão existente

---

### TASK-3.040.4 — ChatMessageController Webchat

**T: Tarefa**
Criar controller para receber mensagens do visitante webchat.

**A: Arquivo**
- `api/src/Domain/Chat/Http/Controllers/WebChatMessageController.php`
- Atualizar `api/src/Domain/Chat/Routes/webchat.php`

**C: Comportamento**
- Antes: webchat não recebe mensagens
- Depois: POST /api/webchat/messages:
  1. Valida sessão existe
  2. Cria ChatMessage (incoming)
  3. Dispara ChatAutopilotResponder
  4. Retorna { messageId }

**E: Evidência**
- Mensagem é persistida no banco
- ChatAutopilotResponder::respond() é chamado
- Retorna messageId para frontend

---

### TASK-3.040.5 — WebChat Redis Publisher

**T: Tarefa**
Criar serviço para publicar eventos webchat no Redis ws.events.

**A: Arquivo**
- `api/src/Domain/Chat/Services/WebChatRedisPublisher.php`

**C: Comportamento**
- Antes: Backend não publica eventos webchat no Redis
- Depois: publishAiResponse(sessionId, message) publica no canal ws.events com rooms=['session:{sessionId}']

**E: Evidência**
- Evento chega no EventFanoutService do Gateway
- Payload contém event='webchat.ai_response', data.message, rooms

---

### TASK-3.040.6 — ChatAutopilotResponder Integration

**T: Tarefa**
Integrar webhook de resposta da IA com WebChatRedisPublisher.

**A: Arquivo**
- `api/src/Domain/Chat/Listeners/AiResponseListener.php`
- OU atualizar ChatAutopilotResponder para chamar WebChatRedisPublisher

**C: Comportamento**
- Antes: AI responde e salva no banco, mas não notifica Gateway
- Depois: Quando AI salva resposta (source='ai', direction='outgoing'), publica no Redis ws.events

**E: Evidência**
- Ao receber resposta AI, WebChatRedisPublisher::publishAiResponse() é chamado
- Evento chega no Gateway via Redis Pub/Sub

---

### TASK-3.040.7 — JWT Helper

**T: Tarefa**
Criar helper para gerar e validar JWT tokens do webchat.

**A: Arquivo**
- `api/src/Domain/Chat/Services/WebChatJwtService.php`

**C: Comportamento**
- Antes: Não existe serviço de JWT para webchat
- Depois:
  - generateToken(sessionId, tenantId, contactId, ticketId): string
  - validateToken(token): payload|null

**E: Evidência**
- generateToken() retorna JWT válido com claims corretos
- validateToken() retorna payload para token válido
- validateToken() retorna null para token inválido/expirado
- Frontend consegue decodificar token

---

### TASK-3.040.8 — Health Check Endpoint

**T: Tarefa**
Criar endpoint de health check para o módulo webchat.

**A: Arquivo**
- `api/src/Domain/Chat/Http/Controllers/WebChatHealthController.php`

**C: Comportamento**
- Antes: Não existe health check para webchat
- Depois: GET /api/webchat/health retorna { status: 'ok', redis: boolean }

**E: Evidência**
- Endpoint responde 200 com status
- Verifica conexão Redis antes de responder

---

## FASE 4: Gateway

---

### TASK-4.040.1 — WebChat Module + Controller

**T: Tarefa**
Criar módulo webchat no Gateway com controller REST.

**A: Arquivo**
- `gateway/src/domains/chat/webchat/webchat.module.ts`
- `gateway/src/domains/chat/webchat/webchat.controller.ts`
- `gateway/src/domains/chat/webchat/webchat.service.ts`
- `gateway/src/domains/chat/webchat/dto/`

**C: Comportamento**
- Antes: Não existe módulo webchat
- Depois:
  - POST /api/webchat/sessions → faz HTTP para Backend
  - GET /api/webchat/sessions/{id} → faz HTTP para Backend
  - POST /api/webchat/messages → faz HTTP para Backend

**E: Evidência**
- Endpoints fazem chamada HTTP correta para Backend
- Retornam dados formatados para cliente

---

### TASK-4.040.2 — WebChat Socket.io Gateway

**T: Tarefa**
Criar Socket.io gateway para eventos webchat em tempo real.

**A: Arquivo**
- `gateway/src/domains/chat/webchat/webchat.gateway.ts`
- Atualizar `gateway/src/domains/chat/webchat/webchat.module.ts`

**C: Comportamento**
- Antes: Socket.io não sabe de webchat
- Depois:
  - webchat:join → autentica JWT, join room session:{id}
  - webchat:message → forward para Backend HTTP
  - webchat:ai_response → broadcast para room (via EventFanout existente)

**E: Evidência**
- Cliente conecta e recebe eventos
- JWT inválido causa disconnect
- Eventos são broadcastados para room correta

---

### TASK-4.040.3 — WebChat JWT Guard

**T: Tarefa**
Criar guard para validar JWT webchat via HMAC.

**A: Arquivo**
- `gateway/src/domains/chat/webchat/guards/webchat-jwt.guard.ts`

**C: Comportamento**
- Antes: Gateway não valida JWT webchat
- Depois: validateToken(token) usando WEBCHAT_JWT_SECRET (HMAC-SHA256)

**E: Evidência**
- Token válido passa no guard
- Token forjado/inválido retorna erro
- Claims são extraídos corretamente

---

### TASK-4.040.4 — EventFanout WebChat Handler

**T: Tarefa**
Atualizar EventFanoutService para fazer broadcast de webchat:ai_response.

**A: Arquivo**
- `gateway/src/domains/realtime/services/event-fanout.service.ts`

**C: Comportamento**
- Antes: EventFanout processa eventos padrão, webchat não existe
- Depois: Evento 'webchat.ai_response' é broadcastado para rooms=['session:{sessionId}']

**E: Evidência**
- Quando Backend publica webchat.ai_response no Redis, cliente Socket.io recebe
- Cliente join na room correta recebe o evento

---

### TASK-4.040.5 — WebChat DTOs e Validation

**T: Tarefa**
Criar DTOs com validação Zod para webchat.

**A: Arquivo**
- `gateway/src/domains/chat/webchat/dto/create-session.dto.ts`
- `gateway/src/domains/chat/webchat/dto/send-message.dto.ts`
- `gateway/src/domains/chat/webchat/dto/session-response.dto.ts`

**C: Comportamento**
- Antes: Sem validação de DTOs
- Depois: Todos os inputs são validados com Zod schema

**E: Evidência**
- Payload inválido retorna 400 Bad Request
- Payload válido passa pela camada

---

### TASK-4.040.6 — CORS Config

**T: Tarefa**
Configurar CORS para permitir orígenes específicos.

**A: Arquivo**
- `gateway/src/domains/chat/webchat/webchat.config.ts`
- Atualizar CORS config

**C: Comportamento**
- Antes: CORS padrão
- Depois: allowedOrigins inclui domínios configurados + localhost dev

**E: Evidência**
- Requests de orígenes permitidos funcionam
- Requests de orígenes não permitidos retornam CORS error

---

## FASE 5: Frontend

---

### TASK-5.040.1 — WebChat Page Component

**T: Tarefa**
Criar página standalone de chat web.

**A: Arquivo**
- `app/src/app/pages/webchat/webchat-page.component.ts`
- `app/src/app/pages/webchat/webchat-page.component.html`

**C: Comportamento**
- Antes: Página webchat não existe
- Depois:
  - Rota /chat/:tenantSlug
  - Componente pré-chat (nome + WhatsApp)
  - Componente chat (mensagens + input)
  - Socket.io connection management

**E: Evidência**
- Página acessível via URL
- Pré-chat coleta dados
- Chat mostra mensagens e permite envio

---

### TASK-5.040.2 — WebChat Service

**T: Tarefa**
Criar serviço Angular para gerenciar comunicação com Gateway.

**A: Arquivo**
- `app/src/app/pages/webchat/services/webchat.service.ts`

**C: Comportamento**
- Antes: Não existe serviço webchat
- Depois:
  - createSession(tenantSlug, name, whatsapp): Observable<SessionResponse>
  - connectWebSocket(token): Socket.io connection
  - sendMessage(sessionId, content): Observable
  - onAiResponse(): Observable<Message>

**E: Evidência**
- createSession() retorna token e sessionId
- Socket.io conecta e recebe eventos
- Mensagens são enviadas e respostas recebidas

---

### TASK-5.040.3 — Pre-Chat Component

**T: Tarefa**
Criar formulário de pré-chat (nome + WhatsApp).

**A: Arquivo**
- `app/src/app/pages/webchat/components/pre-chat/pre-chat.component.ts`
- `app/src/app/pages/webchat/components/pre-chat/pre-chat.component.html`

**C: Comportamento**
- Antes: Não existe pré-chat
- Depois: Form com campos name + WhatsApp + botão iniciar

**E: Evidência**
- Validação de campos
- Submissão cria sessão e avança para chat
- Erro mostra mensagem ao usuário

---

### TASK-5.040.4 — Chat Window Component

**T: Tarefa**
Criar componente de janela de chat (mensagens + input).

**A: Arquivo**
- `app/src/app/pages/webchat/components/chat-window/chat-window.component.ts`
- `app/src/app/pages/webchat/components/chat-window/chat-window.component.html`
- `app/src/app/pages/webchat/components/chat-window/chat-window.component.scss`

**C: Comportamento**
- Antes: Não existe componente de chat
- Depois:
  - Lista de mensagens
  - Input de mensagem
  - Indicador de digitação (typing indicator)
  - Auto-scroll para novas mensagens

**E: Evidência**
- Mensagens são exibidas em ordem
- Input permite envio com Enter
- Novas mensagens fazem auto-scroll
- Typing indicator aparece quando AI está digitando

---

### TASK-5.040.5 — WebChat Widget Embed (iframe)

**T: Tarefa**
Criar suporte para embed via iframe em sites externos.

**A: Arquivo**
- `app/src/app/pages/webchat/embed/webchat-embed.component.ts`
- `app/src/app/pages/webchat/embed/webchat-embed.component.html`
- Snippet JavaScript para sites externos

**C: Comportamento**
- Antes: Chat não pode ser embedado
- Depois:
  - iframe com src="/chat/{tenantSlug}?embed=true"
  - Script snippet para sites externos

**E: Evidência**
- iframe funciona em página externa
- Snippet carrega chat corretamente
- Estilos não vazam para fora do iframe

---

### TASK-5.040.6 — WebChat Routes

**T: Tarefa**
Configurar rotas Angular para webchat.

**A: Arquivo**
- `app/src/app/app.routes.ts`

**C: Comportamento**
- Antes: Rotas webchat não existem
- Depois:
  - /chat/:tenantSlug → WebChatPageComponent
  - /embed/:tenantSlug → WebChatEmbedComponent

**E: Evidência**
- Navegação para /chat/{slug} carrega página correta
- Rota embed funciona

---

## FASE 6: Integration

---

### TASK-6.040.1 — E2E Test Flow Completo

**T: Tarefa**
Criar teste E2E para fluxo completo webchat.

**A: Arquivo**
- `e2e/webchat/webchat-full-flow.spec.ts`

**C: Comportamento**
- Antes: Sem teste E2E
- Depois: Teste cobre:
  1. Acesso à página
  2. Pré-chat → criar sessão
  3. Envio de mensagem
  4. Recebimento de resposta AI
  5. Reconnect WebSocket

**E: Evidência**
- Todos os passos passam
- Sem erros no console

---

### TASK-6.040.2 — Load Test (opcional)

**T: Tarefa**
Teste de carga para validar performance com múltiplas sessões simultâneas.

**A: Arquivo**
- `load-tests/webchat-load-test.yaml`

**C: Comportamento**
- Antes: Sem testes de carga
- Depois: Simula N sessões conectadas enviando mensagens

**E: Evidência**
- Sistema sustenta N sessões sem degradar
- Latência < 2s para resposta AI

---

## Resumo de Tasks

| Phase | Task | Descrição |
|-------|------|-----------|
| 3 | 3.040.1 | ChatSession Entity + Migration |
| 3 | 3.040.2 | ProviderType WEB enum |
| 3 | 3.040.3 | ChatSessionController |
| 3 | 3.040.4 | ChatMessageController |
| 3 | 3.040.5 | WebChat Redis Publisher |
| 3 | 3.040.6 | Autopilot → Redis Integration |
| 3 | 3.040.7 | JWT Helper |
| 3 | 3.040.8 | Health Check |
| 4 | 4.040.1 | WebChat Module + Controller |
| 4 | 4.040.2 | Socket.io Gateway |
| 4 | 4.040.3 | JWT Guard |
| 4 | 4.040.4 | EventFanout Handler |
| 4 | 4.040.5 | DTOs + Validation |
| 4 | 4.040.6 | CORS Config |
| 5 | 5.040.1 | WebChat Page Component |
| 5 | 5.040.2 | WebChat Service |
| 5 | 5.040.3 | Pre-Chat Component |
| 5 | 5.040.4 | Chat Window Component |
| 5 | 5.040.5 | Embed Support |
| 5 | 5.040.6 | Routes |
| 6 | 6.040.1 | E2E Test |
| 6 | 6.040.2 | Load Test (opcional) |
