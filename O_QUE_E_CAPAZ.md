# InteraZap

Plataforma de automação de WhatsApp com CRM integrado, gateway de integrações e inteligência artificial para classificação e streaming de mensagens.

## O que o InteraZap é capaz de fazer

### 1. Automação de WhatsApp multi-provedor
- Integra com **UazAPI** e **Z-API** para envio e recebimento de mensagens via WhatsApp Business API.
- Suporta múltiplas instâncias e tokens, com normalização de payloads entre provedores.
- Webhooks inbound/outbound com autenticação HMAC e idempotência via Redis.

### 2. CRM integrado
- Backend em **Laravel 12** com arquitetura DDD (Controller → DTO → Action → Resource).
- Multi-tenant com isolamento por tenant via trait `BelongsToTenant`.
- RBAC com Spatie Permissions, autenticação Sanctum, primary keys UUID.
- PostgreSQL 17 com pgvector para embeddings de IA.

### 3. Gateway de Integrações (NestJS 11)
- **AI / OpenAI**: chat completions, embeddings, classificação de mensagens, circuit breaker com retry exponencial.
- **Billing / Asaas**: gateway de pagamentos brasileiro, webhooks de pagamento.
- **Real-time**: WebSocket (Socket.io) para eventos em tempo real, broadcast de mensagens.
- **Redis Streams**: comunicação assíncrona confiável entre Gateway e API (acknowledgment, filas).

### 4. Aplicações Frontend
- **Web/Mobile**: Angular 19 + Capacitor + Ionic.
- **Desktop**: Electron 33 + Angular 20, multiplataforma (macOS, Windows, Linux), modo offline com renderer estático embarcado.
- **Landing**: site de marketing.

### 5. Infraestrutura e DevOps
- Infra as Code com Ansible, nginx como reverse proxy.
- Observabilidade configurada.
- Build desktop automatizado via electron-builder, com auto-update via GitHub Releases.

## Módulos e Stack

| Módulo | Stack |
|--------|-------|
| API | Laravel 12 / PHP 8.3 / PostgreSQL 17 / pgvector / Redis 7 |
| Gateway | NestJS 11 / TypeScript 5.7 / BullMQ / Redis Streams / WebSocket |
| App | Angular 19 / Capacitor / Ionic |
| Electron | Electron 33 + Angular 20 |
