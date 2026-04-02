# PRD-GATEWAY-001 — Gateway de Realtime e Broadcasting

> **Modulo:** Gateway
> **Status:** rascunho
> **Autor:** PM / DOC
> **Data:** 2026-03-28
> **Versao:** 1.0

---

## 1. CONTEXTO

### 1.1 O que e o Gateway

O Gateway do InteraZap e um servidor WebSocket standalone construido com NestJS 11 e Socket.io, responsavel por manter conexoes persistententes com os clientes frontend (aplicacao Angular) e redistribuir eventos em tempo real originados no backend Laravel e em servicos internos. O Gateway atua como uma camada de bridge entre a API Laravel e os clientes conectados, viabilizando comunicacao bidirecional em tempo real sem que o frontend precise pollingar endpoints REST.

A arquitetura de realtime e parte critic a do produto InteraZap, que opera como plataforma SaaS multi-tenant para comunicacao inteligente com clientes via WhatsApp, integrando CRM, Chat, Billing e IA. A experiencia do usuario depende fundamentalmente da entrega em tempo real de:

- Mensagens de chat recebidas e enviadas
- Atualizacoes de status de mensagens (enviada, entregue, lida)
- Eventos de execucao de agentes de IA (streaming, conclusao)
- Atualizacoes de sentimento de tickets de atendimento
- Novas notificacoes
- Atividade de conexao e integracao

Sem o Gateway, toda interacao em tempo real seria implementada via polling HTTP, o que geraria latencia elevada, sobrecarga desnecessaria na API e experiencia de usuario degradada, especialmente em fluxos de atendimento de chat onde a velocidade de resposta e critico.

### 1.2 Arquitetura Geral

O Gateway opera como um servico independente (separate deployment unit) que se comunica com o ecossistema InteraZap atraves de tres canais principais:

```
+----------------+       +-------------------+       +----------------------+
|  Laravel API   | ----> |  Redis PubSub     | ----> |  NestJS Gateway      |
|  (Publisher)   |       |  (ws.events)      |       |  (EventFanoutService)|
+----------------+       +-------------------+       +----------+-----------+
        |                                                           |
        | HTTP POST                                                  | Socket.io
        v                                                           v
+----------------+                                         +-------------------+
|  Internal      |                                         |  Frontend Angular |
|  Broadcast     | --------------------------------------> |  (RealtimeService)|
|  Controller    |                                         +-------------------+
+----------------+

PostgreSQL (tenant ownership validation)
```

#### 1.2.1 Fluxo de Dados

1. **Publicacao (Laravel):** Quando uma событие ocorre no backend Laravel (nova mensagem, status atualizado, run de IA completado), o Laravel publica um payload JSON no canal Redis `ws.events` ou faz uma chamada HTTP POST para um endpoint interno do Gateway.

2. **Consumo (EventFanoutService):** O servico `EventFanoutService` mantem uma conexao Redis dedicada (pubsub client separada da client de comandos) inscrita no canal `ws.events`. Quando uma mensagem chega, ela e parseada, validada e roteada para o handler apropriado.

3. **Distribuicao (EventsGateway):** O Gateway Socket.io recebe os eventos e os emite para as rooms correspondentes. A isolacao por tenant e garantida em todas as etapas: o Gateway valida que todo evento publicado para uma tenant room pertence ao tenant correto antes de fazer o emit.

4. **Recebimento (Frontend):** O servico `ChatRealtimeService` do frontend Angular (no pacote `@interazap/chat`) conecta-se ao Gateway via Socket.io, autentica-se com o token JWT e se inscreve nas rooms relevantes (tenant room, ticket rooms). Os eventos chegam como callbacks registrados.

#### 1.2.2 Escalabilidade

O Gateway e stateless em relacao ao estado de negocio (o estado e gerenciado no frontend e no backend Laravel). Cada instancia do Gateway pode servir milhares de conexoes WebSocket simultaneas. Para escala horizontal, basta adicionar mais instancias do Gateway por tras de um load balancer que suporte WebSocket (com sticky sessions por socket ID).

O Redis PubSub garante que quando ha multiplas instancias do Gateway, cada uma recebe todos os eventos publicados no canal `ws.events`. Isso permite escalar horizontalmente sem necessidade de sincronizacao entre instancias.

### 1.3 Decisoes Arquiteturais Chave

#### 1.3.1 WebSocket em Servico Dedicado

Optou-se por um servico de Gateway dedicado em vez de integrar WebSocket diretamente no backend Laravel. As razoes incluem:

- **Isolamento de protocolo:** WebSocket tem ciclo de vida completamente diferente de HTTP request-response; um servidor dedicado facilita o gerenciamento de conexoes persistentates, heartbeats e tempo limites.
- **Escala independente:** O Gateway pode escalar horizontalmente de forma independente da API Laravel. Em momentos de pico de conexoes WebSocket (ex: varios agentes usando o sistema simultaneamente), o Gateway absorve a carga sem impactar a API REST.
- **Performance:** Socket.io em Node.js e naturalmente mais eficiente para conexoes persistentates do que PHP em contextos de longa duracao.
- **Flexibilidade de protocolo:** O Gateway pode agregar eventos de multiplas origens (Redis PubSub, streams, webhooks HTTP) e fazer fan-out para rooms especificas.

#### 1.3.2 Redis PubSub como Barramento de Eventos

O Laravel publica eventos para o Redis em vez de fazer chamadas HTTP diretas para o Gateway. Isso proporciona:

- **Desacoplamento:** O Laravel nao precisa conhecer as instancias do Gateway. Publica no Redis e pronto.
- **Resiliencia:** Se o Gateway estiver temporariamente indisponivel, os eventos publicados no Redis sao perdidos apenas se nao houver subscriber (at-most-once semantics). Para garantias stronger, pode-se usar Redis Streams com consumer groups.
- **Multi-instancia:** Toda instancia do Gateway subscribe no mesmo canal e recebe os eventos, permitindo escala horizontal.

#### 1.3.3 Autenticacao JWT com Fallback Sanctum

A autenticacao WebSocket segue uma estrategia de dois niveis:

1. **JWT nativo:** O frontend ja possui um token JWT emitido pelo Laravel durante o login. Este token e enviado no handshake do Socket.io. O Gateway valida o JWT localmente usando a chave secreta compartilhada.
2. **Fallback Sanctum:** Se a validacao JWT falhar (ex: token muito antigo que nao foi refreshado), o Gateway faz uma chamada HTTP para o endpoint `/api/auth/me` do Laravel para validar o token Sanctum.

O cache LRU em memoria (ate 5000 entradas, TTL 5-10 minutos) evita que toda conexao WebSocket dispare uma chamada HTTP para o Laravel.

#### 1.3.4 Tres Conexoes Redis Dedicadas

O `RedisService` mantem tres conexoes Redis completamente separadas:

- **Command client:** Para operacoes get/set/xadd (comandos regulares).
- **PubSub client:** Para subscribe/unsubscribe em canais. Uma conexao em modo subscribe nao pode executar outros comandos ate um unsubscribe.
- **Blocking client:** Para operacoes BLPOP/BRPOP/XREADGROUP em modo blocking. Uma conexao em blocking call nao pode executar outros comandos.

Essa separacao e critico para evitar deadlocks e garantir que o PubSub nao bloqueie comandos regulares.

### 1.4 Historico e Evolucao

O modulo Gateway foi concebido inicialmente como um servico de chat streaming para receber webhooks de provedores de WhatsApp. Com a evolucao do InteraZap, o Gateway expandiu-se para cobrir:

- **Chat em tempo real:** Mensagens, status, atividade.
- **IA em tempo real:** Streaming de runs de agentes de IA.
- **Notificacoes push:** Notificacoes em tempo real para usuarios.
- **Broadcast interno:** Canal para Laravel comunicar-se com clientes conectados.

A arquitetura atual permite que novos dominios se integrem ao sistema de realtime sem modificacoes no codigo do Gateway, bastando publicar eventos no canal Redis `ws.events` com a estrutura padrao.

---

## 2. OBJETIVO

### 2.1 Visao Geral do Modulo

O modulo Gateway e responsavel por prover infraestrutura de comunicacao em tempo real entre o ecossistema InteraZap e os clientes conectados. O modulo divide-se em duas responsabilidades principais:

1. **Servidor WebSocket (EventsGateway):** Manter conexoes persistentates com clientes frontend, autentica-las, gerenciar rooms e distribuir eventos.
2. **Barramento de Eventos (EventFanoutService + InternalBroadcastController):** Consumir eventos do Redis PubSub e de endpoints internos HTTP, rotea-los e distribui-los para as rooms corretas.

### 2.2 Objetivos de Negocio

- **Experiencia de usuario em tempo real:** Entregar mensagens de chat, atualizacoes de status e eventos de IA com latencia minima (sub-second).
- **Isolamento multi-tenant:** Garantir que nenhum tenant possa receber eventos de outro tenant, em nenhum ponto do fluxo.
- **Alta disponibilidade:** O Gateway deve suportar multiplas instancias com distribuicao de carga via Redis PubSub.
- **Seguranca:** Toda conexao deve ser autenticada; todo evento deve ser validado contra o tenant do remetente.
- **Observabilidade:** Log estruturado em todas as operacoes, facilitando debugging e auditoria.

### 2.3 Objetivos Tecnicos

- Latencia de broadcast inferior a 100ms entre a publicacao no Redis e a entrega ao cliente Socket.io conectado.
- Suporte a no minimo 5.000 conexoes WebSocket simultaneas por instancia.
- Autenticacao de conexoes em menos de 50ms usando cache LRU.
- Taxa de disponibilidade de 99,9% para conexoes WebSocket estabelecidas.
- Compliance com LGPD: nenhum dado de tenant cruzado pode ser transmitido.

### 2.4 Escopo Funcional

#### Dentro do Escopo

- Gerenciamento de conexoes WebSocket (connect, disconnect, heartbeat).
- Autenticacao de conexoes via JWT e fallback Sanctum.
- Ingresso e saida de rooms (tenant, ticket, run).
- Distribuicao de eventos para rooms especificas.
- Validacao de ownership de rooms contra PostgreSQL.
- Consumo de eventos do canal Redis `ws.events`.
- Endpoints internos HTTP para broadcast direto.
- Rate limiting em conexoes WebSocket (WsThrottlerGuard).
- Log estruturado para todas as operacoes.
- Graceful shutdown com cleanup de conexoes.

#### Fora do Escopo

- Armazenamento de mensagens ou estado de negocio (e responsabilidade do Laravel).
- Autenticacao de usuarios (e responsabilidade do modulo Auth).
- Gerenciamento de tickets ou contatos (e responsabilidade do modulo CRM/Chat).
- Processamento de webhooks de provedores de chat (e responsabilidade do ChatModule no gateway ou do Laravel).
- Transcodificacao ou processmento de midia em tempo real.
- Conexoes de terceiros (integrais com outros sistemas via API key ja sao cobertas pelo modulo Webhooks).

### 2.5 KPIs de Sucesso

| KPI                          | Meta                 | Metodo de Medida                       |
| ---------------------------- | -------------------- | -------------------------------------- |
| Latencia de broadcast        | < 100ms p95          | Log de timestamps entre publish e emit |
| Conexoes simultaneas         | 5.000+ por instancia | Metricas Prometheus (socket_count)     |
| Taxa de autenticacao falhada | < 0.1%               | Metricas (auth_failure_total)          |
| Tempo de reconexao           | < 2s                 | Telemetria no frontend                 |
| Eventos perdidos             | 0                    | Validacao de idempotencia em streams   |
| Incidentes cross-tenant      | 0                    | Testes de integracao e logs            |

---

## 3. REGRAS DE NEGOCIO

### 3.1 Autenticacao e Autorizacao

| ID        | Regra                                                                                      | Prioridade | Descricao                                                                                                                                                                                       |
| --------- | ------------------------------------------------------------------------------------------ | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RN-GW-001 | Toda conexao WebSocket deve ser autenticada antes de receber eventos                       | Critica    | Conexoes sem token valido devem ser desconectadas imediatamente. O token e extraido do handshake (objeto `auth.token`, query param `token`, ou header `Authorization`).                         |
| RN-GW-002 | O Gateway valida JWT primeiro antes de recorrer ao fallback Sanctum                        | Alta       | A estrategia de autenticacao segue: tentar JWT local (HS256) -> se falhar, chamar `/api/auth/me` via HTTP. Isso minimiza chamadas para o Laravel.                                               |
| RN-GW-003 | Tokens Sanctum verificados via introspeccao sao cacheados em LRU com TTL de 5 a 10 minutos | Alta       | O cache e em memoria (Map). Entradas expiradas sao removidas por pruning. O tamanho maximo e configuravel (padrao: 5.000).                                                                      |
| RN-GW-004 | Todo payload JWT deve conter as claims obrigatorias `sub` e `tenant_id`                    | Critica    | A ausencia de qualquer dessas claims resulta em rejeicao do token e disconnect do cliente.                                                                                                      |
| RN-GW-005 | Clientes autenticados facem auto-ingresso na tenant room ao conectar                       | Alta       | Ao confirmar autenticacao, o Gateway executa `client.join(tenant:{id})` automaticamente. Isso garante que o cliente recebe eventos da empresa ao conectar.                                      |
| RN-GW-006 | Eventos so sao emitidos para rooms que o tenant do evento possui                           | Critica    | Toda emit para room e validada: se a room e `tenant:{X}`, X deve ser igual ao tenant_id do cliente autenticado. Rooms `ticket:{Y}` e `run:{Z}` passam por validacao de ownership no PostgreSQL. |
| RN-GW-007 | Requests para endpoints internos (`/internal/broadcast/*`) exigem `X-API-Key` valida       | Alta       | O header `x-api-key` deve coincidir com a variavel de ambiente `INTERNAL_API_KEY`. Endpoints internos nao devem ser expostos publicamente.                                                      |
| RN-GW-008 | Rate limiting se aplica a conexoes WebSocket (WsThrottlerGuard)                            | Media      | Por padrao: 60 mensagens por minuto por cliente WebSocket (ws throttle). Excessos resultam em desconexao gradual.                                                                               |
| RN-GW-009 | Tenants diferentes nao podem acessar rooms um do outro em nenhuma circunstancia            | Critica    | O `WsRoomAccessService` valida ownership de ticket e run rooms. O `EventFanoutService` rejeita envelopes sem tenant room ou com cross-tenant rooms.                                             |

### 3.2 Gerenciamento de Rooms

| ID        | Regra                                                           | Prioridade | Descricao                                                                                                                                  |
| --------- | --------------------------------------------------------------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| RN-GW-010 | Rooms seguem o padrao de nomenclatura `{prefix}:{uuid}`         | Alta       | Tres prefixes validos: `tenant`, `ticket`, `run`. Exemplo: `tenant:3d2f1a0b-...`. Qualquer room com formato desconhecido e rejeitada.      |
| RN-GW-011 | Clientes podem solicitar ingresso em rooms via mensagem `join`  | Alta       | O cliente envia `{ rooms: ['ticket:...', 'run:...'] }`. O Gateway valida ownership de cada room antes de executar `client.join`.           |
| RN-GW-012 | Pedidos de join emitidos antes da autenticacao sao enfileirados | Media      | O `WsSessionService` guarda ate 50 rooms por cliente pendente. Apos autenticacao, o flush processa a fila e limpa.                         |
| RN-GW-013 | Clientes podem sair de rooms via mensagem `leave`               | Baixa      | O cliente envia `{ rooms: [...] }` com o evento `leave`. O Gateway executa `client.leave` para cada room listada.                          |
| RN-GW-014 | Rooms de tenant sao validadas por comparacao direta de ID       | Alta       | `tenant:{X}` permite acesso se X e igual ao `tenant_id` do payload JWT. Nao ha query ao banco para rooms de tenant.                        |
| RN-GW-015 | Rooms de ticket requerem validacao de ownership no PostgreSQL   | Alta       | Query em `chat_tickets` verificando que `tenant_id` do ticket e igual ao tenant_id do cliente. Se o ticket nao existir, o acesso e negado. |
| RN-GW-016 | Rooms de run requerem validacao de ownership no PostgreSQL      | Alta       | Query em `ai_autopilot_runs` verificando que `tenant_id` do run e igual ao tenant_id do cliente. Se o run nao existir, o acesso e negado.  |

### 3.3 Broadcast e Distribuicao de Eventos

| ID        | Regra                                                                                              | Prioridade | Descricao                                                                                                                               |
| --------- | -------------------------------------------------------------------------------------------------- | ---------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| RN-GW-017 | Eventos publicados no canal Redis `ws.events` devem conter `event`, `tenant_id` e `data`           | Alta       | O payload minimo e: `{ event: string, tenant_id: string, data: object }`. Eventos sem `tenant_id` sao rejeitados.                       |
| RN-GW-018 | Eventos envelope (com campo `rooms`) exigem que a tenant room do proprietario esteja presente      | Critica    | O envelope deve conter `rooms: [..., 'tenant:{owner}']`. Eventos sem tenant room ou com tenant room de outro tenant sao rejeitados.     |
| RN-GW-019 | Eventos cross-tenant sao rejeitados em qualquer circunstancia                                      | Critica    | Se um evento tentar fazer emit para `tenant:{X}` onde X e diferente do tenant_id do evento, o log gera warning e o evento e descartado. |
| RN-GW-020 | Eventos de chat (`chat.inbound_message_received`) sao mapeados para `chat.message.new` no frontend | Media      | O `EventFanoutService` transforma o payload do webhook em formato esperado pelo frontend antes de emitir para a tenant room.            |
| RN-GW-021 | Eventos de IA (`ai.run.*`) sao emitidos tanto para a tenant room quanto para a run room            | Media      | Se `data.run_id` estiver presente, o evento e emitido para `tenant:{tenant_id}` e `run:{run_id}`.                                       |
| RN-GW-022 | Eventos sem rooms definidas sao roteados por tipo de dominio                                       | Media      | Eventos `ticket.sentiment_updated` -> tenant room. Eventos `notification.new` -> tenant room. Eventos `ai.run.*` -> tenant + run room.  |
| RN-GW-023 | O InternalBroadcastController permite broadcast direto via HTTP POST                               | Alta       | Endpoints protegidos por `InternalApiKeyGuard`. Suporta broadcast generico, status de mensagem, nova mensagem e eventos de IA.          |
| RN-GW-024 | Operacoes de broadcast retornam `{ success: boolean }`                                             | Baixa      | Para permitir ao Laravel confirmar entrega no nivel do controller. Falhas sao logadas e retornam `success: false`.                      |

### 3.4 Infraestrutura e Performance

| ID        | Regra                                                                               | Prioridade | Descricao                                                                                                                                                       |
| --------- | ----------------------------------------------------------------------------------- | ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RN-GW-025 | O Gateway mantem tres conexoes Redis dedicadas e separadas                          | Alta       | Conexao command (comandos get/set/xadd), conexao pubsub (subscribe/unsubscribe), conexao blocking (BLPOP/BRPOP). Conexoes nunca sao compartilhadas entre modos. |
| RN-GW-026 | A conexao pubsub e a unica que pode executar subscribe/unsubscribe                  | Critica    | Uma conexao Redis em modo subscribe nao pode executar outros comandos ate unsubscribe. O `RedisService` garante que apenas o `pubsubClient` ejecuta subscribe.  |
| RN-GW-027 | O pool PostgreSQL tem tamanho maximo configuravel (padrao: 25 conexoes)             | Media      | Configuravel via `PG_POOL_MAX` (range: 20-30). Timeout de conexao via `PG_CONNECTION_TIMEOUT_MS` (padrao: 3000ms).                                              |
| RN-GW-028 | Queries de ownership valicam parametro com query parametrizada (anti-SQL injection) | Critica    | Todas as queries em `WsRoomAccessService` usam parametros `$1`, `$2` do PostgreSQL. Nenhuma interpolacao de string e permitida.                                 |
| RN-GW-029 | Idempotencia em operacoes de stream e garantida via SETNX                           | Media      | O `RedisService.ensureIdempotent()` usa `SET key 1 EX ttl NX` para evitar processamento duplicado de eventos.                                                   |
| RN-GW-030 | O Gateway loga estruturadamente todas as operacoes (eventos, erros, metricas)       | Alta       | Usa `GatewayFileLogger` para logs em arquivo e `Logger` (NestJS) para logs no console. Campos: clientId, tenantId, event, room, action, payloadSize, duration.  |
| RN-GW-031 | O heartbeat (ping/pong) e configurado: pingInterval 15000ms, pingTimeout 10000ms    | Alta       | Se o cliente nao responder ao ping em 10s, o Gateway encerra a conexao. Isso detecta conexoes zumbis.                                                           |
| RN-GW-032 | Graceful shutdown: conexoes sao finalizadas de forma limpa ao encerrar o processo   | Media      | `onModuleDestroy` desconecta subscribers Redis, encerra pool PostgreSQL, e limpa cache de tokens.                                                               |

### 3.5 Seguranca e Compliance

| ID        | Regra                                                                                                   | Prioridade | Descricao                                                                                                                               |
| --------- | ------------------------------------------------------------------------------------------------------- | ---------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| RN-GW-033 | Tokens, senhas e API keys nunca sao logados                                                             | Critica    | O log estruturado filtra campos sensiveis. Qualquer log que contenha `token`, `password`, `apiKey` e automaticamente mascarado.         |
| RN-GW-034 | A variavel `INTERNAL_API_KEY` deve ser configurada em producao                                          | Alta       | Se nao estiver configurada, o `InternalApiKeyGuard` lana erro na inicializacao. Em dev, um valor padrao pode ser usado.                 |
| RN-GW-035 | Conexoes WebSocket expostas em CORS devem ter origem permitida configuravel                             | Alta       | O `@WebSocketGateway` configura `cors: { origin: true, credentials: true }` em dev. Em prod, a origem deve ser configurada via env var. |
| RN-GW-036 | Rate limiting em endpoints internos e global (HttpThrottler)                                            | Media      | 100 requests por minuto por IP para endpoints HTTP. Clientes WebSocket tem throttle propio (ws throttle).                               |
| RN-GW-037 | Auditoria de acessos: todas as tentativas de join em rooms nao autorizadas sao logadas                  | Alta       | O `WsRoomAccessService` loga tentativas falhas de acesso com clientId, room solicitada, tenantId do cliente.                            |
| RN-GW-038 | Logs de acesso e erro devem incluir ID de correlacao (correlation ID) propagado entre todos os servicos | Alta       | O correlation ID e gerado no handshake e anexado a todo log subsequente da conexao.                                                     |
| RN-GW-039 | O Gateway NAO armazena dados de negocio — apenas estado de conexao e rooms                              | Critica    | Dados de negocio (mensagens, tickets, negociacoes) permanecem no Laravel. O Gateway e stateless em relacao a negocio.                   |
| RN-GW-040 | O Gateway NAO faz cache de dados de negocio — apenas cache de tokens JWT validados                      | Alta       | Cache de tokens tem TTL de 5-10 minutos e e em memoria. Nada mais e cacheado.                                                           |
| RN-GW-041 | Conexoes WebSocket expiradas por inatividade (ping timeout) sao removidas automaticamente               | Alta       | O servidor Socket.io encerra conexoes que excedem pingTimeout de 10000ms sem resposta ao ping.                                          |

### 3.6 Escalabilidade e Operacao

| ID        | Regra                                                                                                     | Prioridade | Descricao                                                                                                             |
| --------- | --------------------------------------------------------------------------------------------------------- | ---------- | --------------------------------------------------------------------------------------------------------------------- |
| RN-GW-050 | O Gateway e stateless em relacao ao estado de negocio                                                     | Alta       | Cada instancia pode servir qualquer cliente. O estado de conexao e gerenciado pelo Socket.io e Redis.                 |
| RN-GW-051 | Escala horizontal: multiplas instancias do Gateway detras de load balancer com sticky sessions            | Alta       | Sticky sessions garantem que o Socket.io handshake inicial e redirects subsequentes vao para a mesma instancia.       |
| RN-GW-052 | Redis PubSub garante que todas as instancias do Gateway recebem todos os eventos                          | Alta       | PUBLISH para `ws.events` e consumido por todas as instancias simultaneamente.                                         |
| RN-GW-053 | Cada instancia do Gateway mantem seu proprio subscriber Redis                                             | Alta       | Uma conexao `subscribe` por instancia. O Redis entrega copias para cada subscriber.                                   |
| RN-GW-054 | Para mais de 10 instancias, considerar Redis Cluster ou Streams com consumer groups                       | Media      | PubSub puro pode拥塞 com muitas instancias. Redis Streams permite distribuicao de carga.                              |
| RN-GW-055 | PostgreSQL pool: max 25 conexoes por instancia, timeout 3000ms                                            | Media      | Configuravel via `PG_POOL_MAX` (20-30) e `PG_CONNECTION_TIMEOUT_MS`.                                                  |
| RN-GW-056 | O pool PostgreSQL e compartilhado entre todos os services que precisam de DB                              | Media      | Uma unica conexao pool via `DatabaseService` injetado nos services que precisam de queries de ownership.              |
| RN-GW-057 | Cache de tokens LRU e por instancia, NAO compartilhado entre instancias                                   | Media      | Cada instancia tem seu proprio cache de tokens. Tokens podem ser revalidados em outra instancia se cliente reconnect. |
| RN-GW-058 | Metricas de carga: `socket_count`, `auth_failures`, `event_broadcast_duration` exportadas para Prometheus | Alta       | Endpoint `/metrics` expõe metricas no formato Prometheus.                                                             |
| RN-GW-059 | Health check: `GET /health` retorna status da conexao Redis e PostgreSQL                                  | Alta       | Se Redis ou PostgreSQL estiverem down, health check retorna 503.                                                      |
| RN-GW-060 | Conexoes lentas: queries PostgreSQL que excedem 1000ms disparam warning log                               | Media      | Permite identificar problemas de performance antes que virem erros.                                                   |

### 3.7 Observabilidade e Monitoramento

| ID        | Regra                                                                                                                                                                 | Prioridade | Descricao                                                                                                                 |
| --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------- | ------------------------------------------------------------------------------------------------------------------------- |
| RN-GW-070 | Logs estruturados em formato JSON para todas as operacoes                                                                                                             | Alta       | Campos fixos: timestamp, level, message, correlationId, clientId, tenantId, event, room, action, payloadSize, durationMs. |
| RN-GW-071 | Log de conexao: `client_connected`, `client_authenticated`, `client_disconnected` com duracao da sessao                                                               | Alta       | Duraçao calculada como diferenca entre disconnect e connect.                                                              |
| RN-GW-072 | Log de eventos: `event_published`, `event_broadcast`, `event_dropped` com tamanho do payload                                                                          | Media      | Tamanho em bytes permite identificar payloads excessivamente grandes.                                                     |
| RN-GW-073 | Log de erros: `connection_error`, `auth_failure`, `room_join_denied`, `broadcast_error`                                                                               | Critica    | Todo erro deve ter contexto suficiente para debugging sem expor dados sensiveis.                                          |
| RN-GW-074 | Metricas Prometheus: `gateway_socket_connections_total` (counter), `gateway_socket_disconnections_total` (counter), `gateway_event_broadcast_duration_ms` (histogram) | Alta       | Disponivel em `/metrics`.                                                                                                 |
| RN-GW-075 | Metricas Prometheus: `gateway_auth_failures_total` (counter, labels: reason), `gateway_room_join_denied_total` (counter, labels: room_type)                           | Alta       | Counters permitem calcular taxas e tendencias.                                                                            |
| RN-GW-076 | Alertas: auth_failure_rate > 1% em 5 minutos dispara alerta                                                                                                           | Alta       | Configurado no Alertmanager para notificar equipe de operacoes.                                                           |
| RN-GW-077 | Alertas: broadcast_latency_p95 > 500ms dispara alerta de degradacao                                                                                                   | Alta       | Latencia elevada indica problemas de Redis ou rede.                                                                       |
| RN-GW-078 | Traces: correlation ID propagado do Laravel para o Gateway para debugging distribuido                                                                                 | Media      | O Laravel injeta `X-Correlation-ID` header no handshake ou no payload Redis.                                              |
| RN-GW-079 | O Gateway NAO persiste logs de eventos de negocio (mensagens, conteudo)                                                                                               | Critica    | Logs de observabilidade contém apenas metadados (event name, room, tenant_id), nunca o conteudo de mensagens.             |
| RN-GW-080 | O payload maximo de um evento WebSocket e 1MB. Eventos maiores sao rejeitados com erro                                                                                | Alta       | Protege contra ataques de DoS com payloads grandes.                                                                       |

---

## 4. FLUXOS

### 4.1 Fluxo: Conexao WebSocket e Autenticacao

```mermaid
sequenceDiagram
    participant FE as Frontend Angular
    participant GW as EventsGateway
    participant AUTH as WsAuthenticationService
    participant SESSION as WsSessionService
    participant LARAVEL as Laravel API

    FE->>GW: Socket.io connect (handshake with JWT token)
    GW->>AUTH: extractToken(client)
    AUTH->>AUTH: Try auth.token (handshake)
    alt token found in auth.token
        AUTH->>AUTH: Return token
    else not in auth.token
        AUTH->>AUTH: Try Authorization header
        AUTH->>AUTH: Return token or null
    end

    AUTH->>AUTH: verifyToken(token)
    alt JWT secret configured
        AUTH->>AUTH: jwt.verify HS256
        alt JWT valid and has sub + tenant_id
            AUTH-->>GW: Return JwtPayload
        else JWT invalid
            AUTH->>AUTH: Fall through to Sanctum
        end
    end

    alt JWT invalid or secret not configured
        AUTH->>LARAVEL: GET /api/auth/me (Bearer token)
        alt token in LRU cache (not expired)
            AUTH-->>GW: Return cached payload
        else cache miss
            LARAVEL-->>AUTH: { data: { user: { id, tenant_id, email } } }
            AUTH->>AUTH: Cache entry (TTL 5-10min)
            AUTH-->>GW: Return JwtPayload
        end
    end

    GW->>GW: client.data.user = payload
    GW->>GW: client.data.tenantId = tenant_id
    GW->>GW: client.join(tenant:{tenant_id})
    GW->>SESSION: flushPendingJoinRequests(client, tenantId, server)
    alt has pending rooms
        loop each pending room
            SESSION->>SESSION: validate ownership via WsRoomAccessService
            alt authorized
                SESSION->>GW: client.join(room)
            else unauthorized
                SESSION->>SESSION: Log warning (unauthorized queued join)
            end
        end
    end
    GW->>FE: Connection established (Socket.io ACK)
```

### 4.2 Fluxo: Publicacao de Evento via Redis PubSub (Laravel -> Gateway)

```mermaid
sequenceDiagram
    participant LARAVEL as Laravel API
    participant REDIS as Redis PubSub
    participant FANOUT as EventFanoutService
    participant GW as EventsGateway
    participant ROOM as Socket.io Rooms
    participant FE as Frontend Angular

    LARAVEL->>REDIS: PUBLISH ws.events (JSON payload)
    REDIS->>FANOUT: on('message', rawMessage)
    FANOUT->>FANOUT: JSON.parse(rawMessage)
    FANOUT->>FANOUT: Extract event, data, tenant_id, rooms

    alt has rooms array (envelope pattern)
        FANOUT->>FANOUT: processEnvelopeEvent()
        FANOUT->>FANOUT: Validate tenant room present
        alt tenant room missing or cross-tenant
            FANOUT->>FANOUT: Reject event (log warning)
        else valid
            loop each room in rooms
                FANOUT->>GW: emitToRoom(room, event, data)
                GW->>ROOM: server.to(room).emit(event, data)
            end
        end
    else event starts with 'ai.run.'
        FANOUT->>FANOUT: processAiRunEvent()
        FANOUT->>GW: emitToRoom(tenant:{tenant_id}, event, data)
        alt has run_id
            FANOUT->>GW: emitToRoom(run:{run_id}, event, data)
        end
    else event === 'chat.inbound_message_received'
        FANOUT->>FANOUT: processChatMessage()
        FANOUT->>FANOUT: Map to chat.message.new format
        FANOUT->>GW: emitToRoom(tenant:{tenant_id}, chat.message.new, payload)
    else event === 'ticket.sentiment_updated'
        FANOUT->>FANOUT: processTicketSentimentEvent()
        FANOUT->>GW: emitToRoom(tenant:{tenant_id}, event, data)
    else event === 'notification.new'
        FANOUT->>FANOUT: processNotificationEvent()
        FANOUT->>GW: emitToRoom(tenant:{tenant_id}, event, data)
    end

    ROOM->>FE: Socket.io event (chat.message.new, ai.run.completed, etc.)
    FE->>FE: ChatRealtimeService handler updates UI
```

### 4.3 Fluxo: Ingresso em Room com Validacao de Ownership

```mermaid
sequenceDiagram
    participant FE as Frontend Angular
    participant GW as EventsGateway
    participant ACCESS as WsRoomAccessService
    participant DB as PostgreSQL

    FE->>GW: Socket.io event: join { rooms: ['ticket:uuid', 'run:uuid2'] }
    GW->>GW: Validate authenticated (has user.payload)

    alt not authenticated yet
        GW->>SESSION: enqueuePendingJoinRequests(clientId, rooms)
        Note over GW,SESSION: Rooms queued; will flush after auth
    else authenticated
        loop each room
            alt room starts with 'tenant:'
                GW->>ACCESS: canJoinRoom(room, tenantId)
                ACCESS->>ACCESS: Extract tenant ID from room
                alt roomTenantId === userTenantId
                    ACCESS-->>GW: true
                else
                    ACCESS-->>GW: false
                end
            else room starts with 'ticket:'
                GW->>ACCESS: canJoinRoom(ticket:{id}, tenantId)
                ACCESS->>DB: SELECT tenant_id FROM chat_tickets WHERE id = $1
                alt ticket exists and tenant_id matches
                    ACCESS-->>GW: true
                else ticket not found or wrong tenant
                    ACCESS-->>GW: false
                end
            else room starts with 'run:'
                GW->>ACCESS: canJoinRoom(run:{id}, tenantId)
                ACCESS->>DB: SELECT tenant_id FROM ai_autopilot_runs WHERE id = $1
                alt run exists and tenant_id matches
                    ACCESS-->>GW: true
                else run not found or wrong tenant
                    ACCESS-->>GW: false
                end
            else unknown prefix
                ACCESS-->>GW: false (log warning)
            end

            alt authorized (true)
                GW->>GW: client.join(room)
                Note over GW: Log: client joined room
            else unauthorized (false)
                GW->>GW: Skip room (do not join)
                Note over GW: Log: unauthorized room join attempt
            end
        end
    end
```

### 4.4 Fluxo: Broadcast via HTTP (Laravel -> Gateway -> Clientes)

```mermaid
sequenceDiagram
    participant LARAVEL as Laravel API
    participant IBC as InternalBroadcastController
    participant GW as EventsGateway
    participant ROOM as Socket.io Rooms

    LARAVEL->>IBC: POST /internal/broadcast/event (X-API-Key: ...)
    IBC->>IBC: InternalApiKeyGuard validates X-API-Key

    alt invalid API key
        IBC-->>LARAVEL: 401 Unauthorized
    else valid API key
        IBC->>IBC: ValidationPipe (whitelist, transform)
        IBC->>IBC: BroadcastEventDto validated

        alt has room param
            IBC->>GW: emitToRoom(room, event, data)
        else has data.tenant_id
            IBC->>GW: emitToRoom(tenant:{tenant_id}, event, data)
        else no room context
            IBC->>GW: emit(event, data) [global broadcast]
        end

        GW->>ROOM: Socket.io emit

        alt broadcast for new message
            LARAVEL->>IBC: POST /internal/broadcast/new-message
            IBC->>GW: emitToRoom(tenant:{id}, chat.message.new, dto)
            IBC->>GW: emitToRoom(ticket:{id}, chat.message.new, dto)
        end

        alt broadcast for message status
            LARAVEL->>IBC: POST /internal/broadcast/message-status
            IBC->>GW: emitToRoom(tenant:{id}, chat.message.status, dto)
            IBC->>GW: emitToRoom(ticket:{id}, chat.message.status, dto)
        end

        IBC-->>LARAVEL: { success: true }
    end
```

### 4.5 Fluxo: Desconexao e Cleanup

```mermaid
sequenceDiagram
    participant FE as Frontend Angular
    participant GW as EventsGateway
    participant SESSION as WsSessionService
    participant AUTH as WsAuthenticationService

    FE->>GW: Socket disconnect (client-side close, network loss, or ping timeout)
    GW->>SESSION: handleDisconnect(client)
    SESSION->>SESSION: clearPending(client.id)
    GW->>GW: logServerStats()
    GW->>AUTH: (no action - token cache persists)

    Note over GW: Client socket removed from all rooms automatically by Socket.io

    alt graceful shutdown (SIGTERM, SIGINT)
        GW->>AUTH: onModuleDestroy()
        AUTH->>AUTH: clearInterval(cleanupInterval)
        AUTH->>AUTH: sanctumTokenCache.clear()
        GW->>FANOUT: onModuleDestroy()
        FANOUT->>FANOUT: subscriberClient.unsubscribe(ws.events)
        GW->>DB: onModuleDestroy() -> pool.end()
        GW->>REDIS: onModuleDestroy() -> quit all clients
        Note over GW: All connections closed cleanly
    end
```

### 4.6 Diagrama de Arquitetura de Componentes

```mermaid
graph TB
    subgraph "Frontend (Angular)"
        CRS[ChatRealtimeService<br/>Socket.io Client]
        FE[Frontend App]
    end

    subgraph "NestJS Gateway"
        subgraph "Realtime Domain"
            EG[EventsGateway<br/>WebSocket Server<br/>@WebSocketGateway]
            EF[EventFanoutService<br/>Redis Subscriber]
            IBC[InternalBroadcastController<br/>HTTP Endpoints]
        end

        subgraph "Realtime Services"
            WSA[WsAuthenticationService<br/>JWT + Sanctum]
            RSA[WsRoomAccessService<br/>PostgreSQL Validation]
            WSS[WsSessionService<br/>Pending Join Queue]
        end

        subgraph "Realtime Guards"
            WSG[WsAuthGuard<br/>JWT Validation]
            IAG[InternalApiKeyGuard<br/>X-API-Key]
            WTG[WsThrottlerGuard<br/>Rate Limiting]
        end
    end

    subgraph "Infrastructure"
        RED[RedisService<br/>3 Dedicated Connections]
        DBS[DatabaseService<br/>PostgreSQL Pool]
        CFG[ConfigService<br/>Environment Variables]
    end

    subgraph "Laravel Backend"
        API[Laravel API<br/>/api/auth/me]
        PUB[Event Publisher<br/>Redis PUBLISH]
    end

    subgraph "Redis"
        PSC[(PubSub Channel<br/>ws.events)]
    end

    subgraph "PostgreSQL"
        TBL[chat_tickets<br/>ai_autopilot_runs]
    end

    FE -->|Socket.io connect| EG
    CRS -->|Socket.io events| EG
    EG -->|Authenticate| WSA
    WSA -->|Verify JWT| WSA
    WSA -->|Sanctum fallback| API
    WSA -->|Token cache LRU| WSA
    EG -->|Validate room| RSA
    RSA -->|Ownership query| DBS
    DBS -->|SELECT tenant_id| TBL
    EG -->|Pending joins| WSS
    EG -->|Emit events| EG
    PUB -->|PUBLISH| PSC
    PSC -->|subscribe| EF
    EF -->|emitToRoom| EG
    IBC -->|emitToRoom| EG
    IBC -->|HTTP POST| API
    IBC -->|X-API-Key| IAG
    CFG -->|Config| EG
    CFG -->|Config| RED
    CFG -->|Config| DBS
    RED -->|3 connections| PSC
```

### 4.7 Diagrama de Fluxo de Eventos

```mermaid
flowchart LR
    A[Laravel Event] --> B{Has rooms array?}

    B -->|Yes| C[processEnvelopeEvent]
    C --> D[tenant room present?]
    D -->|No| E[Reject - log warning]
    D -->|Yes| F[Cross-tenant room?]
    F -->|Yes| G[Reject - log error]
    F -->|No| H[Emit to all rooms]

    B -->|No| I{Event type}

    I -->|ai.run.*| J[processAiRunEvent]
    J --> K[Emit to tenant:{id}]
    K --> L{Has run_id?}
    L -->|Yes| M[Emit to run:{id}]

    I -->|chat.inbound_message| N[processChatMessage]
    N --> O[Map to chat.message.new]
    O --> P[Emit to tenant:{id}]

    I -->|ticket.sentiment_updated| Q[processTicketSentimentEvent]
    Q --> R[Emit to tenant:{id}]

    I -->|notification.new| S[processNotificationEvent]
    S --> T[Emit to tenant:{id}]

    H --> U[(Socket.io<br/>Room Delivery)]
    M --> U
    P --> U
    R --> U
    T --> U
```

---

## 5. ENTIDADES E MODELOS

### 5.1 JwtPayload

Representa o conteudo decodificado de um token JWT apos validacao bem-sucedida. E o modelo central de identidade para toda conexao WebSocket.

```typescript
interface JwtPayload {
    /** ID unico do usuario autenticado (claim 'sub' do JWT). */
    sub: string;

    /** ID do tenant ao qual o usuario pertence (claim 'tenant_id' do JWT). */
    tenant_id: string;

    /** Email do usuario (opcional, presente apenas em tokens com claim expandida). */
    email?: string;

    /** Timestamp de expiracao do token (claim 'exp' do JWT, adicionado automaticamente pelo jwt.verify). */
    exp?: number;
}
```

**Constraints:**

- `sub` e obrigatorio: string nao-vazia.
- `tenant_id` e obrigatorio: string nao-vazia.
- `email` e opcional: string valida de email se presente.
- A ausencia de `sub` ou `tenant_id` resulta em rejeicao do token.

**Mapeamento:**

- Origem JWT: claim `sub` -> `sub`, claim `tenant_id` -> `tenant_id`.
- Origem Sanctum: resposta de `/api/auth/me` -> `user.id` -> `sub`, `user.tenant_id` -> `tenant_id`.

### 5.2 SocketData

Dados anexados a cada socket conectado no Gateway. Armazenados em `client.data` do Socket.io.

```typescript
interface SocketData {
    /** Payload JWT do usuario autenticado. Presente apenas apos autenticacao bem-sucedida. */
    user?: JwtPayload;

    /** ID do tenant do usuario autenticado. Extraido de `user.tenant_id` para conveniencia. */
    tenantId?: string;
}
```

**Ciclo de vida:**

1. Cliente conecta: `client.data = {}` (vazio).
2. Autenticacao completa: `client.data.user = payload`, `client.data.tenantId = tenant_id`.
3. Cliente disconnect: dados sao descartados (Socket.io limpa automaticamente).

### 5.3 AuthenticatedSocket

Extensao do tipo `Socket` do Socket.io com a propriedade `data` tipada como `SocketData`.

```typescript
interface AuthenticatedSocket extends Socket {
    data: SocketData;
}
```

**Uso:** Apos autenticacao, o socket e castado para `AuthenticatedSocket` para garantir type-safety no acesso a `client.data.user` e `client.data.tenantId`.

### 5.4 GatewayEvent

Modelo unificado para todos os eventos consumidos do canal Redis `ws.events`.

```typescript
interface GatewayEvent {
    /** Nome do evento a ser emitido (ex: 'chat.message.new', 'ai.run.completed'). */
    event: string;

    /** Payload de dados do evento. Estrutura varia conforme o tipo de evento. */
    data: Record<string, unknown>;

    /** ID do tenant proprietario do evento. Obrigatorio para todos os eventos. */
    tenant_id?: string;

    /** Lista de rooms para onde o evento deve ser emitido (padrao envelope). */
    rooms?: string[];

    /** Versao do formato do evento (opcional, para future-proofing). */
    version?: string;
}
```

**Validacao:**

- `event` e obrigatorio: string nao-vazia.
- `data` e obrigatorio: objeto.
- `tenant_id` e obrigatorio para eventos sem `rooms` (roteamento por tipo de dominio).
- `rooms` e obrigatorio para eventos envelope (garantia de tenant room).

### 5.5 TokenCacheEntry

Entrada individual no cache LRU de tokens Sanctum verificados.

```typescript
interface TokenCacheEntry {
    /** Payload JWT resgatado da introspeccao Sanctum. */
    payload: JwtPayload;

    /** Timestamp em ms ate o qual esta entrada e valida. */
    expiresAt: number;
}
```

**LRU Behavior:**

- O cache usa um `Map` insertion-ordered do JavaScript.
- Ao acessar uma entrada valida, ela e removida e reinserida (refresh de recencia).
- Ao inserir nova entrada com chave ja existente, a entrada anterior e removida.
- O pruning remove todas as entradas com `expiresAt <= now` e, se o tamanho exceder `sanctumTokenCacheMaxEntries`, remove as mais antigas (primeiras do Map).

### 5.6 BroadcastEventDto

Payload do endpoint interno `/internal/broadcast/event`.

```typescript
interface BroadcastEventDto {
    /** Nome do evento a ser emitido. */
    event: string;

    /** Nome da room para emit (opcional). Se nao fornecido, usa tenant_id de data. */
    room?: string | null;

    /** Payload de dados. Se `room` nao for fornecido, deve conter `tenant_id`. */
    data: Record<string, unknown>;
}
```

**Semantica de room:**

- `room` fornecido: emite para a room especificada.
- `room` ausente + `data.tenant_id` presente: emite para `tenant:{data.tenant_id}`.
- `room` ausente + `data.tenant_id` ausente: broadcast global (`server.emit`).

### 5.7 MessageStatusDto

Payload do endpoint `/internal/broadcast/message-status` para atualizacao de status de mensagem.

```typescript
interface MessageStatusDto {
    /** ID da mensagem de chat. */
    message_id: string;

    /** ID do ticket ao qual a mensagem pertence. */
    ticket_id: string;

    /** ID do tenant proprietario. */
    tenant_id: string;

    /** Novo status da mensagem (ex: 'sent', 'delivered', 'read', 'failed'). */
    status: string;

    /** Mensagem de erro em caso de falha (opcional). */
    error_message?: string | null;

    /** Timestamp de envio (opcional, ISO 8601). */
    sent_at?: string | null;

    /** Timestamp de entrega (opcional, ISO 8601). */
    delivered_at?: string | null;

    /** Timestamp de leitura (opcional, ISO 8601). */
    read_at?: string | null;
}
```

**Dual emit:** O controller emite para `tenant:{tenant_id}` e `ticket:{ticket_id}`, garantindo que todos os clientes relevantes (operadores na dashboard e clientes no ticket especifico) recebam a atualizacao.

### 5.8 NewMessageDto

Payload do endpoint `/internal/broadcast/new-message` para broadcast de nova mensagem.

```typescript
interface NewMessageDto {
    /** ID do ticket ao qual a mensagem pertence. */
    ticket_id: string;

    /** ID do tenant proprietario. */
    tenant_id: string;

    /** Dados normalizados da mensagem. */
    message: Record<string, unknown>;
}
```

**Normalizacao:** O campo `message` deve conter a mensagem ja normalizada pelo Laravel antes do envio. O Gateway nao transforma este payload alem de adicionar os IDs de contexto.

### 5.9 AiRunEventDto

Payload do endpoint `/internal/broadcast/ai-run-event` para eventos de execucao de IA.

```typescript
interface AiRunEventDto {
    /** Nome do evento (ex: 'ai.run.streaming', 'ai.run.completed'). */
    event: string;

    /** Dados do evento. Deve conter `tenant_id` e opcionalmente `run_id`. */
    data: Record<string, unknown>;
}
```

**Dual room emit:** Se `data.tenant_id` estiver presente, o controller emite para `tenant:{tenant_id}`. Se `data.run_id` estiver presente, emite tambem para `run:{run_id}`.

### 5.10 RoomPrefix (Enum)

Constantes de prefixo para nomenclatura de rooms.

```typescript
const RoomPrefix = {
    TENANT: 'tenant',
    TICKET: 'ticket',
    RUN: 'run',
} as const;
```

**Rooms geradas:**

- `tenantRoom(tenantId)` -> `tenant:{tenantId}`
- `ticketRoom(ticketId)` -> `ticket:{ticketId}`
- `runRoom(runId)` -> `run:{runId}`

### 5.11 CHAT_EVENTS (Enum)

Constantes de nomes de eventos de chat.

```typescript
const CHAT_EVENTS = {
    ACTIVITY: 'chat.activity',
    MESSAGE_NEW: 'chat.message.new',
    MESSAGE_STATUS: 'chat.message.status',
    MESSAGE_EDIT: 'chat.message.edit',
    CONNECTION: 'chat.connection',
    CONTACT: 'chat.contact',
    INTEGRATION_CONNECTION: 'integration.connection',
} as const;
```

### 5.12 AI_EVENTS (Enum)

Constantes de nomes de eventos de IA.

```typescript
const AI_EVENTS = {
    RUN_STREAMING: 'ai.run.streaming',
    RUN_COMPLETED: 'ai.run.completed',
} as const;
```

### 5.13 ChatNewMessageEvent (Interface — Frontend Contract)

Contrato esperado pelo `ChatRealtimeService` do frontend para eventos de nova mensagem.

```typescript
interface ChatNewMessageEvent {
    /** ID do ticket. Pode ser null para webhooks brutos ainda nao processados. */
    ticket_id: string | null;

    /** Dados normalizados da mensagem. */
    message: Record<string, unknown>;
}
```

**Nota de implementacao:** O `EventFanoutService.processChatMessage()` mapeia o payload do webhook para este formato antes de emitir.

### 5.14 Entidades PostgreSQL (Tabelas de Validacao)

O Gateway consulta as seguintes tabelas para validacao de ownership:

#### chat_tickets

| Coluna    | Tipo | Uso no Gateway                                       |
| --------- | ---- | ---------------------------------------------------- |
| id        | uuid | Chave primaria, usada para `ticket:{id}`             |
| tenant_id | uuid | Comparado com `tenant_id` do JWT para validar acesso |

```sql
SELECT tenant_id FROM chat_tickets WHERE id = $1 LIMIT 1;
```

#### ai_autopilot_runs

| Coluna    | Tipo | Uso no Gateway                                       |
| --------- | ---- | ---------------------------------------------------- |
| id        | uuid | Chave primaria, usada para `run:{id}`                |
| tenant_id | uuid | Comparado com `tenant_id` do JWT para validar acesso |

```sql
SELECT tenant_id FROM ai_autopilot_runs WHERE id = $1 LIMIT 1;
```

---

## 6. ENDPOINTS

### 6.1 Endpoints WebSocket (Socket.io)

#### WS-GW-001 — Conexao WebSocket

**Caminho:** `ws[s]://host:port/ws`
**Metodo:** Upgrade HTTP -> WebSocket (handshake Socket.io)

**Handshake Query/Headers:**

| Campo           | Origem            | Descricao                            |
| --------------- | ----------------- | ------------------------------------ |
| `auth.token`    | handshake.auth    | Token JWT ou Sanctum (recomendado)   |
| `token`         | handshake.query   | Token como query param (alternativa) |
| `Authorization` | handshake.headers | Bearer token no header (fallback)    |

**Exemplo de conexao:**

```javascript
const socket = io('https://gateway.interazap.com.br/ws', {
    auth: { token: 'eyJ...' },
    transports: ['websocket'],
});
```

**Respostas:**

| Codigo     | Condicao                           | Acao                                         |
| ---------- | ---------------------------------- | -------------------------------------------- |
| Conecta    | Token valido, JWT verificado       | Auto-join `tenant:{id}`, flush pending joins |
| Disconnect | Token invalido, ping timeout (10s) | Socket desconectado                          |
| Disconnect | Autenticacao falhou                | Socket desconectado, log de erro             |

**Fluxo de autenticacao detalhado:**

1. `WsAuthenticationService.extractToken()` tenta: `handshake.auth.token` -> `handshake.headers.authorization` -> `null`.
2. `WsAuthenticationService.verifyToken()`: tenta `jwt.verify()` com HS256 -> se falhar, chama `verifySanctumToken()` via HTTP.
3. `verifySanctumToken()`: verifica cache LRU -> se miss, HTTP GET `/api/auth/me` -> cache resultado -> retorna `JwtPayload`.
4. Erro em qualquer etapa: `client.disconnect()` com log.

---

#### WS-GW-002 — Evento: join

Envia uma lista de rooms para ingressar.

**Direcao:** Client -> Server
**Nome do evento Socket.io:** `join`

**Payload de entrada:**

```json
{
    "rooms": ["ticket:3d2f1a0b-c8e3-4f2a-b9d6-1e7c3a5f8b2a", "run:a1b2c3d4-..."]
}
```

**Payload de saida:** nenhum (fire-and-forget)

**Regras de validacao:**

- Cada room e validada pelo `WsRoomAccessService.canJoinRoom()`.
- Rooms nao autorizadas sao silenciosamente ignoradas (log de warning).
- Se o cliente ainda nao esta autenticado, as rooms sao enfileiradas no `WsSessionService`.

**Comportamento de room:**

| Prefixo       | Validacao                                                         |
| ------------- | ----------------------------------------------------------------- |
| `tenant:{id}` | `id === client.data.tenantId` (comparacao direta)                 |
| `ticket:{id}` | `chat_tickets.tenant_id === client.data.tenantId` (query DB)      |
| `run:{id}`    | `ai_autopilot_runs.tenant_id === client.data.tenantId` (query DB) |
| Outro         | Rejeitado com log                                                 |

---

#### WS-GW-003 — Evento: leave

Envia uma lista de rooms para sair.

**Direcao:** Client -> Server
**Nome do evento Socket.io:** `leave`

**Payload de entrada:**

```json
{
    "rooms": ["ticket:3d2f1a0b-...", "run:a1b2c3d4-..."]
}
```

**Payload de saida:** nenhum (fire-and-forget)

**Comportamento:** O Gateway executa `client.leave(room)` para cada room listada. Rooms inexistentes ou ja abandonadas sao ignoradas sem erro.

---

### 6.2 Endpoints HTTP Internos

Todos os endpoints internos requerem o header `X-API-Key` com o valor da variavel de ambiente `INTERNAL_API_KEY`.

#### GW-HTTP-001 — POST /internal/broadcast/event

Broadcast generico de evento para room(s) especifica(s).

**Autenticacao:** `InternalApiKeyGuard` (X-API-Key)
**Validacao:** `ValidationPipe` com `whitelist: true, transform: true`

**Request:**

```http
POST /internal/broadcast/event HTTP/1.1
Host: gateway.interazap.com.br
Content-Type: application/json
X-API-Key: sk_internal_...

{
  "event": "custom.event",
  "room": "tenant:3d2f1a0b-...",
  "data": {
    "message": "Hello world",
    "timestamp": "2026-03-28T10:00:00Z"
  }
}
```

**Alternativa sem `room`:**

```json
{
    "event": "custom.event",
    "data": {
        "message": "Hello world",
        "tenant_id": "3d2f1a0b-..."
    }
}
```

**Response (sucesso):**

```json
{
    "success": true
}
```

**Response (falha):**

```json
{
    "success": false
}
```

**Logica de emit:**

```
if (room)         -> emitToRoom(room, event, data)
else if tenant_id -> emitToRoom(tenant:{id}, event, data)
else              -> server.emit(event, data) [global]
```

---

#### GW-HTTP-002 — POST /internal/broadcast/message-status

Atualizacao de status de mensagem de chat.

**Autenticacao:** `InternalApiKeyGuard`
**Validacao:** DTO com campos obrigatorios: `message_id`, `ticket_id`, `tenant_id`, `status`

**Request:**

```http
POST /internal/broadcast/message-status HTTP/1.1
Host: gateway.interazap.com.br
Content-Type: application/json
X-API-Key: sk_internal_...

{
  "message_id": "msg-uuid-123",
  "ticket_id": "ticket-uuid-456",
  "tenant_id": "tenant-uuid-789",
  "status": "delivered",
  "delivered_at": "2026-03-28T10:00:05Z"
}
```

**Dual Emit:**

1. `emitToRoom('tenant:{tenant_id}', 'chat.message.status', dto)`
2. `emitToRoom('ticket:{ticket_id}', 'chat.message.status', dto)`

**Use case:** Quando o Laravel detecta que uma mensagem foi entregue pelo provedor WhatsApp (webhook de status), publica o status atualizado. O Gateway distribui para todos os operadores no tenant e para quem estiver visualizando o ticket especifico.

---

#### GW-HTTP-003 — POST /internal/broadcast/new-message

Broadcast de nova mensagem de chat.

**Autenticacao:** `InternalApiKeyGuard`
**Validacao:** DTO com campos obrigatorios: `ticket_id`, `tenant_id`, `message`

**Request:**

```http
POST /internal/broadcast/new-message HTTP/1.1
Host: gateway.interazap.com.br
Content-Type: application/json
X-API-Key: sk_internal_...

{
  "ticket_id": "ticket-uuid-456",
  "tenant_id": "tenant-uuid-789",
  "message": {
    "id": "msg-uuid-123",
    "content": "Hello, how can I help?",
    "direction": "inbound",
    "sender": "+5511999999999",
    "timestamp": "2026-03-28T10:00:00Z"
  }
}
```

**Dual Emit:**

1. `emitToRoom('tenant:{tenant_id}', 'chat.message.new', dto)`
2. `emitToRoom('ticket:{ticket_id}', 'chat.message.new', dto)`

**Use case:** Apos o Laravel processar e persistir uma mensagem recebida via webhook, notifica o Gateway para distribuir para clientes conectados. Isso atualiza a UI em tempo real sem polling.

---

#### GW-HTTP-004 — POST /internal/broadcast/ai-run-event

Broadcast de evento de execucao de agente de IA.

**Autenticacao:** `InternalApiKeyGuard`
**Validacao:** DTO com campos: `event` (string obrigatorio), `data` (objeto com `tenant_id` obrigatorio e `run_id` opcional)

**Request:**

```http
POST /internal/broadcast/ai-run-event HTTP/1.1
Host: gateway.interazap.com.br
Content-Type: application/json
X-API-Key: sk_internal_...

{
  "event": "ai.run.completed",
  "data": {
    "tenant_id": "tenant-uuid-789",
    "run_id": "run-uuid-abc",
    "status": "completed",
    "duration_ms": 1523,
    "tokens_used": 342
  }
}
```

**Dual Emit:**

1. `emitToRoom('tenant:{tenant_id}', event, data)`
2. `emitToRoom('run:{run_id}', event, data)`

**Use case:** Quando o `AiRunOrchestrator` do Gateway completa uma execucao de agente, pode publicar diretamente via este endpoint (ou via Redis) para通知 usuarios conectados sobre o progresso ou conclusao.

---

### 6.3 Endpoints de Health e Metricas

#### GW-HTTP-005 — GET /health

Health check do Gateway para load balancers e orquestracao.

**Autenticacao:** Nenhuma (publico)

**Response:**

```json
{
    "status": "ok",
    "uptime": 86400,
    "connections": 1234,
    "timestamp": "2026-03-28T10:00:00Z"
}
```

**Campos:**

| Campo       | Descricao                                                 |
| ----------- | --------------------------------------------------------- |
| status      | `ok` se todas as conexoes de infraestrutura estao healthy |
| uptime      | Segundos desde o inicio do processo                       |
| connections | Total de sockets WebSocket conectados                     |
| timestamp   | ISO 8601 do momento da resposta                           |

---

## 7. EVENTOS

### 7.1 Catalogo de Eventos WebSocket

| Evento                     | Origem               | Direcao           | Descricao                                            |
| -------------------------- | -------------------- | ----------------- | ---------------------------------------------------- |
| `chat.message.new`         | Laravel (Redis/HTTP) | Gateway -> Client | Nova mensagem de chat recebida ou enviada            |
| `chat.message.status`      | Laravel (Redis/HTTP) | Gateway -> Client | Atualizacao de status de mensagem                    |
| `chat.message.edit`        | Laravel (Redis)      | Gateway -> Client | Mensagem editada pelo remetente                      |
| `chat.activity`            | Laravel (Redis)      | Gateway -> Client | Atividade generica de chat (digitacao, online, etc.) |
| `chat.connection`          | Gateway (interno)    | Gateway -> Client | Notificacao de conexao de agente/cliente             |
| `chat.contact`             | Laravel (Redis)      | Gateway -> Client | Atualizacao de contato                               |
| `integration.connection`   | Laravel (Redis)      | Gateway -> Client | Status de conexao de integracao                      |
| `ai.run.streaming`         | Gateway (AI)         | Gateway -> Client | Chunk de streaming de run de IA                      |
| `ai.run.completed`         | Gateway (AI)         | Gateway -> Client | Run de IA completado                                 |
| `ticket.sentiment_updated` | Laravel (Redis)      | Gateway -> Client | Sentimento do ticket atualizado                      |
| `notification.new`         | Laravel (Redis)      | Gateway -> Client | Nova notificacao                                     |

### 7.2 Eventos de Chat

#### chat.message.new

Notifica sobre nova mensagem de chat. Emitido para `tenant:{id}` e `ticket:{id}`.

```typescript
interface ChatMessageNewPayload {
    ticket_id: string;
    message: {
        id: string;
        content: string;
        direction: 'inbound' | 'outbound';
        sender: string;
        recipient?: string;
        timestamp: string;
        metadata?: Record<string, unknown>;
    };
}
```

**Exemplo de payload:**

```json
{
    "ticket_id": "3d2f1a0b-c8e3-4f2a-b9d6-1e7c3a5f8b2a",
    "message": {
        "id": "msg-abc123",
        "content": "Ola, preciso de ajuda",
        "direction": "inbound",
        "sender": "+5511999999999",
        "timestamp": "2026-03-28T10:00:00Z"
    }
}
```

**Handled by:** `ChatRealtimeService` no frontend Angular.

#### chat.message.status

Atualizacao de status de mensagem. Emitido para `tenant:{id}` e `ticket:{id}`.

```typescript
interface ChatMessageStatusPayload {
    message_id: string;
    ticket_id: string;
    tenant_id: string;
    status: 'queued' | 'sent' | 'delivered' | 'read' | 'failed';
    error_message?: string;
    sent_at?: string;
    delivered_at?: string;
    read_at?: string;
}
```

**Exemplo de payload:**

```json
{
    "message_id": "msg-abc123",
    "ticket_id": "3d2f1a0b-...",
    "tenant_id": "tenant-xyz",
    "status": "delivered",
    "delivered_at": "2026-03-28T10:00:05Z"
}
```

#### chat.activity

Evento generico de atividade de chat. Usado para indicadores de digitacao, sessoes ativas, etc.

```typescript
interface ChatActivityPayload {
    tenant_id: string;
    ticket_id?: string;
    type: 'typing' | 'session_started' | 'session_ended' | 'agent_joined' | 'agent_left';
    user_id?: string;
    timestamp: string;
}
```

### 7.3 Eventos de IA

#### ai.run.streaming

Chunk de streaming de uma execucao de agente de IA.

```typescript
interface AiRunStreamingPayload {
    tenant_id: string;
    run_id: string;
    chunk: string;
    partial_response?: string;
    timestamp: string;
}
```

**Emissao dual:** `tenant:{tenant_id}` + `run:{run_id}`.

#### ai.run.completed

Notifica que uma execucao de agente de IA foi concluida.

```typescript
interface AiRunCompletedPayload {
    tenant_id: string;
    run_id: string;
    status: 'completed' | 'failed' | 'cancelled';
    duration_ms: number;
    tokens_used?: number;
    result?: Record<string, unknown>;
    error?: string;
    timestamp: string;
}
```

### 7.4 Eventos de Ticket

#### ticket.sentiment_updated

Atualizacao do sentimento de um ticket de atendimento, calculado por modelos de IA.

```typescript
interface TicketSentimentPayload {
    tenant_id: string;
    ticket_id: string;
    sentiment: 'positive' | 'neutral' | 'negative';
    confidence: number; // 0.0 - 1.0
    previous_sentiment?: string;
    timestamp: string;
}
```

### 7.5 Eventos de Notificacao

#### notification.new

Nova notificacao para o usuario.

```typescript
interface NotificationNewPayload {
    tenant_id: string;
    notification: {
        id: string;
        type: 'info' | 'warning' | 'error' | 'success';
        title: string;
        body: string;
        link?: string;
        read: boolean;
        created_at: string;
    };
}
```

### 7.6 Eventos Internos Redis

#### ws.events (canal PubSub)

Estrutura minima de qualquer mensagem publicada no canal `ws.events`:

```json
{
  "event": "chat.message.new",
  "tenant_id": "tenant-uuid-789",
  "data": { ... },
  "rooms": ["tenant:tenant-uuid-789", "ticket:ticket-uuid-456"],
  "version": "1.0"
}
```

**Ou, para roteamento por tipo de evento (sem rooms):**

```json
{
    "event": "ai.run.completed",
    "tenant_id": "tenant-uuid-789",
    "data": {
        "tenant_id": "tenant-uuid-789",
        "run_id": "run-uuid-abc",
        "status": "completed"
    }
}
```

---

## 8. SEGURANCA

### 8.1 Modelo de Ameacas

| Ameaca                     | Vetor                                        | Mitigacao                                                                                              |
| -------------------------- | -------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| Token spoofing             | Atacante envia token falso no handshake      | Validacao JWT com chave secreta + fallback Sanctum com introspeccao                                    |
| Cross-tenant data access   | Cliente tenta ingressar room de outro tenant | WsRoomAccessService valida ownership via PostgreSQL; EventFanoutService rejeita envelopes cross-tenant |
| Unauthenticated connection | Cliente conecta sem token                    | handleConnection() disconnects imediatamente se token ausente                                          |
| Replay attack              | Token JWT expirado reutilizado               | TTL curto no JWT; fallback Sanctum re-valida; LRU cache expira                                         |
| Internal API abuse         | Terceiro chama endpoints internos            | InternalApiKeyGuard valida X-API-Key; API key configurada em env var                                   |
| Redis injection            | Payload malicioso no canal ws.events         | JSON.parse com validacao de schema; campos esperados verificados com type guards                       |
| SQL injection              | Query de ownership manipulada                | Parameterized queries ($1, $2) em todas as queries                                                     |
| Rate limiting evasion      | Cliente envia muitas mensagens por segundo   | WsThrottlerGuard (60 msg/min por cliente WebSocket)                                                    |
| Denial of Service          | many conexoes simultaneas                    | Throttler global; pool PostgreSQL com max 30 conexoes; connection timeout                              |
| Sensitive data in logs     | Token ou API key aparece em logs             | GatewayFileLogger filtra campos com `token`, `password`, `apiKey`                                      |

### 8.2 Autenticacao em Camadas

```
Camada 1: JWT (local)
  - Verificado diretamente no Gateway com jwt.verify()
  - Algoritmo: HS256 (configuravel)
  - Claims obrigatorias: sub, tenant_id
  - Tempo de validacao: < 1ms (sem chamada de rede)

Camada 2: Sanctum (HTTP fallback)
  - Chamada HTTP para /api/auth/me do Laravel
  - Cache LRU: 5000 entradas, TTL 5-10 min
  - Tempo de validacao: < 1500ms (timeout configuravel)
  - Chamadas simultaneas para mesmo token: apenas 1 HTTP request
```

### 8.3 Isolamento de Tenant

O isolamento de tenant e aplicado em tres pontos do fluxo:

1. **Conexao:** `handleConnection()` extrai `tenant_id` do payload JWT e faz auto-join em `tenant:{id}`. O cliente recebe apenas eventos da sua tenant room.

2. **Ingresso em rooms:** `WsRoomAccessService.canJoinRoom()` valida cada room solicitada:
    - Tenant rooms: comparacao direta de ID.
    - Ticket rooms: query em `chat_tickets`.
    - Run rooms: query em `ai_autopilot_runs`.

3. **Distribuicao de eventos:** `EventFanoutService.processEnvelopeEvent()` rejeita qualquer envelope que nao contenha a tenant room do proprietario ou que tente fazer cross-tenant fan-out.

### 8.4 CORS e Origens Permitidas

Em desenvolvimento: `cors: { origin: true, credentials: true }` (qualquer origem).
Em producao: a origem deve ser configurada explicitamente via variavel de ambiente para evitar que sites maliciosos establecam conexoes WebSocket.

```typescript
// configuracao recomendada em prod
cors: {
  origin: [process.env.ALLOWED_ORIGIN],
  credentials: true,
}
```

### 8.5 Seguranca no Redis

- **Separao de conexoes:** O RedisService usa tres conexoes dedicadas para evitar que comandos blocking (subscribe) bloqueiem comandos regulares (publish, get, set).
- **Conexao pubsub isolada:** O subscriber e completamente separado do command client, evitando deadlocks.
- **Timeout em conexao blocking:** Operacoes XREADGROUP usam timeout configuravel para evitar conexoes permanentemente bloqueadas.
- **Idempotencia:** O metodo `ensureIdempotent()` usa SETNX para evitar que o mesmo evento seja processado mais de uma vez em consumidores de stream.

### 8.6 Rate Limiting

| Contexto                  | Limite       | Duracao     | Acao                             |
| ------------------------- | ------------ | ----------- | -------------------------------- |
| HTTP (endpoints internos) | 100 requests | 60 segundos | HTTP 429 Too Many Requests       |
| WebSocket (mensagens)     | 60 mensagens | 60 segundos | WsException + desconexao gradual |

O throttle de WebSocket usa o `WsThrottlerGuard` registrado como guard global (`APP_GUARD`). O tracker e baseado no ID do cliente (`client.id`) apos autenticacao, ou IP antes da autenticacao.

### 8.7 Auditoria e Logging

O Gateway implementa logging estruturado em todas as operacoes de seguranca:

| Evento                               | Log Level | Campos                     |
| ------------------------------------ | --------- | -------------------------- |
| Conexao sem token                    | WARN      | clientId                   |
| Autenticacao falhada                 | WARN      | clientId, error            |
| Cliente autenticado                  | INFO      | clientId, tenantId, userId |
| Ingresso em room nao autorizada      | ERROR     | clientId, room, tenantId   |
| Envelope cross-tenant rejeitado      | WARN      | event, tenant_id, rooms    |
| API key invalida em endpoint interno | WARN      | ip, path                   |
| Token sem claims obrigatorias        | WARN      | clientId, error            |

---

## 9. DTOs E RESOURCES

### 9.1 DTOs de Broadcast (Gateway -> Laravel Request)

#### BroadcastEventDto

```typescript
import { IsString, IsNotEmpty, IsOptional, IsObject } from 'class-validator';
import { Transform } from 'class-transformer';

export class BroadcastEventDto {
    @IsString()
    @IsNotEmpty()
    event: string;

    @IsOptional()
    @Transform(({ value }) => (value === '' ? null : value))
    room?: string | null;

    @IsObject()
    data: Record<string, unknown>;
}
```

#### MessageStatusDto

```typescript
import { IsString, IsNotEmpty, IsOptional, IsIn } from 'class-validator';

export class MessageStatusDto {
    @IsString()
    @IsNotEmpty()
    message_id: string;

    @IsString()
    @IsNotEmpty()
    ticket_id: string;

    @IsString()
    @IsNotEmpty()
    tenant_id: string;

    @IsString()
    @IsNotEmpty()
    status: string;

    @IsOptional()
    error_message?: string | null;

    @IsOptional()
    sent_at?: string | null;

    @IsOptional()
    delivered_at?: string | null;

    @IsOptional()
    read_at?: string | null;
}
```

#### NewMessageDto

```typescript
import { IsString, IsNotEmpty, IsObject } from 'class-validator';

export class NewMessageDto {
    @IsString()
    @IsNotEmpty()
    ticket_id: string;

    @IsString()
    @IsNotEmpty()
    tenant_id: string;

    @IsObject()
    message: Record<string, unknown>;
}
```

#### AiRunEventDto

```typescript
import { IsString, IsNotEmpty, IsObject } from 'class-validator';

export class AiRunEventDto {
    @IsString()
    @IsNotEmpty()
    event: string;

    @IsObject()
    data: Record<string, unknown>;
}
```

### 9.2 Tipos Internos (Gateway)

#### GatewayEvent

```typescript
export interface GatewayEvent {
    event: string;
    data: Record<string, unknown>;
    tenant_id?: string;
    rooms?: string[];
    version?: string;
}
```

#### JwtPayload

```typescript
export interface JwtPayload {
    sub: string;
    tenant_id: string;
    email?: string;
    exp?: number;
}
```

#### TokenCacheEntry

```typescript
export interface TokenCacheEntry {
    payload: JwtPayload;
    expiresAt: number;
}
```

#### SocketData

```typescript
export interface SocketData {
    user?: JwtPayload;
    tenantId?: string;
}
```

#### AuthenticatedSocket

```typescript
import { Socket } from 'socket.io';

export interface AuthenticatedSocket extends Socket {
    data: SocketData;
}
```

#### AuthenticatedHandshake

```typescript
export interface AuthenticatedHandshake {
    auth?: {
        token?: string;
    };
    query?: Record<string, string | string[]>;
    headers?: Record<string, string | string[]>;
}
```

### 9.3 Resources (Gateway Response Models)

#### BroadcastSuccessResponse

```typescript
export interface BroadcastSuccessResponse {
    success: true;
}
```

#### BroadcastFailureResponse

```typescript
export interface BroadcastFailureResponse {
    success: false;
}
```

#### HealthCheckResponse

```typescript
export interface HealthCheckResponse {
    status: 'ok' | 'degraded' | 'unhealthy';
    uptime: number;
    connections: number;
    timestamp: string;
    checks?: {
        redis: 'up' | 'down';
        database: 'up' | 'down';
    };
}
```

---

## 10. CRITERIOS DE ACEITACAO

### 10.1 Autenticacao e Conexao

| CA-ID     | Criterio                                                                                            | Metodo de Verificacao                                                                                     |
| --------- | --------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| CA-GW-001 | Cliente com token JWT valido (HS256, com sub e tenant_id) conecta e recebe auto-join na tenant room | Teste de conexao Socket.io com token valido; verificar que `client.rooms` inclui `tenant:{id}`            |
| CA-GW-002 | Cliente com token invalido e desconectado imediatamente                                             | Conectar com token mal-formado; verificar disconnect em < 500ms                                           |
| CA-GW-003 | Cliente sem token e desconectado imediatamente                                                      | Conectar sem handshake auth; verificar disconnect em < 500ms                                              |
| CA-GW-004 | Token Sanctum valido e verificado via fallback HTTP e cacheado                                      | Conectar com token pre-expiry; verificar que apenas 1 chamada HTTP e feita; reconnect verificar cache hit |
| CA-GW-005 | Cache LRU expira entradas apos TTL configurado                                                      | Esperar TTL + 1min; verificar que cache foi pruneado                                                      |
| CA-GW-006 | Tentativa de join antes da autenticacao e enfileirada e processada apos autenticacao                | Enviar `join` antes de auth completar; verificar rooms ingressadas apos ACK de conexao                    |
| CA-GW-007 | Payload JWT sem `sub` ou `tenant_id` e rejeitado                                                    | Criar token sem claims obrigatorias; verificar disconnect                                                 |
| CA-GW-008 | Conexao sobrevive a ping/pong heartbeat (pingInterval 15000ms, pingTimeout 10000ms)                 | Conectar; esperar 15s sem enviar mensagens; verificar que conexao permanece ativa                         |
| CA-GW-009 | Conexao e terminada apos 10s sem resposta ao ping                                                   | Simular cliente silencioso; verificar disconnect                                                          |

### 10.2 Gerenciamento de Rooms

| CA-ID     | Criterio                                                                        | Metodo de Verificacao                                                                                              |
| --------- | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| CA-GW-010 | Cliente pode ingressar room `tenant:{proprio_id}`                               | Enviar `join` com tenant room propria; verificar ingressou                                                         |
| CA-GW-011 | Cliente NAO pode ingressar room `tenant:{outro_id}`                             | Enviar `join` com tenant room de outro tenant; verificar que NAO ingressou (log de warning)                        |
| CA-GW-012 | Cliente pode ingressar room `ticket:{id}` se ticket pertence ao seu tenant      | Enviar `join` com ticket valido do proprio tenant; verificar ingressou                                             |
| CA-GW-013 | Cliente NAO pode ingressar room `ticket:{id}` se ticket pertence a outro tenant | Enviar `join` com ticket de outro tenant; verificar que NAO ingressou; verificar query parametrica (sem injection) |
| CA-GW-014 | Cliente pode ingressar room `run:{id}` se run pertence ao seu tenant            | Enviar `join` com run valida do proprio tenant; verificar ingressou                                                |
| CA-GW-015 | Cliente NAO pode ingressar room `run:{id}` se run pertence a outro tenant       | Enviar `join` com run de outro tenant; verificar que NAO ingressou                                                 |
| CA-GW-016 | Cliente pode sair de rooms via `leave`                                          | Ingressar room; enviar `leave`; verificar que saiu                                                                 |
| CA-GW-017 | Rooms desconhecidas sao rejeitadas com log                                      | Enviar `join` com room de prefixo desconhecido; verificar log de warning e room nao ingressada                     |

### 10.3 Broadcast e Distribuicao

| CA-ID     | Criterio                                                                                     | Metodo de Verificacao                                                                                         |
| --------- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| CA-GW-018 | Evento envelope com tenant room valida e emitido para todas as rooms listadas                | Publicar no Redis com `rooms: ['tenant:X', 'ticket:Y']`; verificar que clientes em cada room recebem o evento |
| CA-GW-019 | Evento envelope sem tenant room e rejeitado                                                  | Publicar no Redis sem tenant room; verificar que NAO foi emitido                                              |
| CA-GW-020 | Evento envelope com cross-tenant room e rejeitado                                            | Publicar no Redis com `tenant:X` onde X != tenant_id do payload; verificar que NAO foi emitido                |
| CA-GW-021 | Eventos `ai.run.*` sao emitidos para tenant room e run room                                  | Publicar `ai.run.completed` com run_id; verificar que clientes em ambas rooms recebem                         |
| CA-GW-022 | Eventos `chat.inbound_message_received` sao transformados e emitidos como `chat.message.new` | Publicar webhook no canal Redis; verificar que frontend recebe `chat.message.new`                             |
| CA-GW-023 | Eventos `ticket.sentiment_updated` sao emitidos para tenant room                             | Publicar evento; verificar que clientes na tenant room recebem                                                |
| CA-GW-024 | Endpoint `/internal/broadcast/event` emite para room especificada                            | POST com `room`; verificar que clientes na room recebem                                                       |
| CA-GW-025 | Endpoint `/internal/broadcast/event` com `data.tenant_id` emite para tenant room             | POST sem `room` mas com `data.tenant_id`; verificar emit para tenant room                                     |
| CA-GW-026 | Endpoint `/internal/broadcast/message-status` emite para tenant room e ticket room           | POST; verificar dual emit para ambas rooms                                                                    |
| CA-GW-027 | Endpoint `/internal/broadcast/new-message` emite para tenant room e ticket room              | POST; verificar dual emit para ambas rooms                                                                    |
| CA-GW-028 | Endpoint `/internal/broadcast/ai-run-event` emite para tenant room e run room                | POST com run_id; verificar dual emit para ambas rooms                                                         |
| CA-GW-029 | Latencia de broadcast e inferior a 100ms (p95)                                               | Publicar evento com timestamp; medir tempo ate cliente receber; 100+ samples                                  |
| CA-GW-030 | Operacoes de broadcast sem `X-API-Key` retornam 401                                          | POST sem header; verificar 401 Unauthorized                                                                   |
| CA-GW-031 | Operacoes de broadcast com `X-API-Key` invalida retornam 401                                 | POST com chave errada; verificar 401                                                                          |

### 10.4 Infraestrutura

| CA-ID     | Criterio                                                        | Metodo de Verificacao                                                    |
| --------- | --------------------------------------------------------------- | ------------------------------------------------------------------------ |
| CA-GW-032 | Gateway conecta com tres conexoes Redis separadas               | Verificar logs: `gateway-commands`, `gateway-pubsub`, `gateway-blocking` |
| CA-GW-033 | Cache LRU mantem no maximo o numero configurado de entradas     | Verificar que tamanho do Map <= `WS_SANCTUM_CACHE_MAX_ENTRIES`           |
| CA-GW-034 | Pool PostgreSQL tem tamanho maximo configurado                  | Verificar que `PG_POOL_MAX` e respeitado (20-30)                         |
| CA-GW-035 | Queries de ownership usam parametros (sem string interpolation) | Revisao de codigo de `WsRoomAccessService`; verificar uso de `$1`        |
| CA-GW-036 | Graceful shutdown fecha conexoes Redis e pool PostgreSQL        | Enviar SIGTERM; verificar que `onModuleDestroy` executa cleanup completo |
| CA-GW-037 | Logs estruturados contem campos esperados                       | Verificar log output: clientId, tenantId, event, room, action            |
| CA-GW-038 | Tokens e API keys NAO aparecem em logs                          | grep logs por `auth.token`, `sk_internal_`, `secret`; nao deve encontrar |

### 10.5 Rate Limiting e Resiliencia

| CA-ID     | Criterio                                                                                       | Metodo de Verificacao                                                                               |
| --------- | ---------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| CA-GW-039 | Cliente WebSocket excedendo 60 msg/min e desconectado                                          | Simular 61 mensagens em 60s; verificar WsException ou disconnect                                    |
| CA-GW-040 | Cliente HTTP excedendo 100 req/min recebe 429                                                  | Enviar 101 requests em 60s para endpoint interno; verificar 429                                     |
| CA-GW-041 | Conexao com Redis indisponivel: Gateway continua funcionando para clientes ja conectados       | Desligar Redis; verificar que clientes conectados mantem conexao; novos clientes nao conectam       |
| CA-GW-042 | Conexao com PostgreSQL indisponivel: join de ticket/run rooms falha mas tenant rooms funcionam | Desligar PostgreSQL; verificar que conexoes de tenant room mantidas; ticket/run joins retornam erro |

### 10.6 Testes de Integracao

| CA-ID     | Criterio                                                                           | Metodo de Verificacao                                                                  |
| --------- | ---------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| CA-GW-043 | Fluxo completo: Laravel publica evento Redis -> Gateway consome -> Frontend recebe | End-to-end: publicar no canal ws.events; verificar que cliente Socket.io recebe evento |
| CA-GW-044 | Fluxo completo: Laravel HTTP POST -> Gateway distribui -> Frontend recebe          | End-to-end: POST para /internal/broadcast/new-message; verificar cliente recebe        |
| CA-GW-045 | Multi-tenant isolation: evento de Tenant A NAO chega a Cliente de Tenant B         | Publicar evento para Tenant A; conectar cliente de Tenant B; verificar que NAO recebe  |
| CA-GW-046 | 100+ conexoes simultaneas sem degradação de performance                            | Conectar 100 sockets simultaneos; publicar evento; verificar < 100ms de latencia       |

### 10.7 Checklist de Go-Live

- [ ] Todos os testes unitarios passando (`pnpm test`)
- [ ] Todos os testes de integracao passando
- [ ] Gate de lint passando (`pnpm lint`)
- [ ] Health endpoint retornando `status: ok`
- [ ] Variaveis de ambiente configuradas em prod:
    - `REDIS_URL`
    - `DATABASE_URL`
    - `JWT_SECRET`
    - `INTERNAL_API_KEY`
    - `WS_EVENTS_CHANNEL`
    - `WS_SANCTUM_CACHE_TTL_MS`
    - `WS_SANCTUM_CACHE_MAX_ENTRIES`
    - `API_URL` (para fallback Sanctum)
    - `API_AUTH_TIMEOUT_MS`
    - `ALLOWED_ORIGIN` (CORS)
    - `PG_POOL_MAX`, `PG_CONNECTION_TIMEOUT_MS`
- [ ] Logs de accesso verificados (sem dados sensiveis)
- [ ] Load balancer configurado com WebSocket support e sticky sessions
- [ ] Monitoramento (Prometheus/Grafana) configurado para metricas do Gateway
- [ ] Alertas configurados para: conexoes > 80% do maximo, latencia de broadcast > 200ms, taxa de autenticacao falhada > 1%

---

## 11. VARIAVEIS DE AMBIENTE

### 11.1 Variaveis Obrigatorias

| Variavel           | Tipo   | Default                                                | Descricao                                |
| ------------------ | ------ | ------------------------------------------------------ | ---------------------------------------- |
| `REDIS_URL`        | string | `redis://localhost:6379`                               | URL de conexao Redis                     |
| `DATABASE_URL`     | string | `postgres://interazap:secret@localhost:5432/interazap` | Connection string PostgreSQL             |
| `JWT_SECRET`       | string | -                                                      | Chave secreta para validacao JWT (HS256) |
| `INTERNAL_API_KEY` | string | -                                                      | API key para endpoints internos          |

### 11.2 Variaveis Opcionais

| Variavel                       | Tipo    | Default         | Descricao                                                   |
| ------------------------------ | ------- | --------------- | ----------------------------------------------------------- |
| `WS_EVENTS_CHANNEL`            | string  | `ws.events`     | Canal Redis PubSub para eventos                             |
| `WS_SANCTUM_CACHE_TTL_MS`      | number  | `300000` (5min) | TTL do cache de tokens Sanctum em ms (range: 300000-600000) |
| `WS_SANCTUM_CACHE_MAX_ENTRIES` | number  | `5000`          | Tamanho maximo do cache LRU (range: 2000-50000)             |
| `API_URL`                      | string  | -               | URL base do Laravel para fallback Sanctum                   |
| `API_AUTH_TIMEOUT_MS`          | number  | `1500`          | Timeout para chamada HTTP de validacao Sanctum              |
| `PG_POOL_MAX`                  | number  | `25`            | Tamanho maximo do pool PostgreSQL (range: 20-30)            |
| `PG_CONNECTION_TIMEOUT_MS`     | number  | `3000`          | Timeout de conexao PostgreSQL em ms (range: 500-10000)      |
| `PG_IDLE_TIMEOUT_MS`           | number  | `10000`         | Timeout de conexao idle em ms (range: 1000-30000)           |
| `REALTIME_DEBUG_CHAT_ACTIVITY` | boolean | `false`         | Ativa logs detalhados de atividade de chat                  |
| `ALLOWED_ORIGIN`               | string  | `*`             | Origem permitida para CORS em producao                      |
| `THROTTLER_HTTP_TTL`           | number  | `60`            | TTL do throttle HTTP em segundos                            |
| `THROTTLER_HTTP_LIMIT`         | number  | `100`           | Limite de requests HTTP por TTL                             |
| `THROTTLER_WS_TTL`             | number  | `60`            | TTL do throttle WebSocket em segundos                       |
| `THROTTLER_WS_LIMIT`           | number  | `60`            | Limite de mensagens WebSocket por TTL                       |
| `PORT`                         | number  | `3000`          | Porta TCP do servidor Gateway                               |

---

## 12. GLOSSARIO

| Termo                    | Definicao                                                                                                                                           |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| **WebSocket**            | Protocolo de comunicacao bidirecional sobre TCP (RFC 6455). Permite conexoes persistentates entre cliente e servidor.                               |
| **Socket.io**            | Biblioteca Node.js que abstrai WebSocket com fallbacks (polling), rooms, namespaces e reconnection automatica.                                      |
| **Room**                 | Abstracao do Socket.io que permite agrupar sockets e fazer emit para grupos. Cada room e identificada por uma string unica.                         |
| **PubSub**               | Publisher/Subscriber. Padrao de mensageria onde publishersenviam mensagens para canais, e subscribersrecebem todas as mensagens desses canais.      |
| **Fan-out**              | Padrao onde uma mensagem e distribuida para multiplos destinatarios. O Gateway faz fan-out de eventos do Laravel para todos os clientes relevantes. |
| **JWT**                  | JSON Web Token (RFC 7519). Token auto-contido que transporta claims (sub, tenant_id, etc.) assinadas digitalmente.                                  |
| **Sanctum**              | Pacote de autenticacao do Laravel que suporta tokens API com introspeccao.                                                                          |
| **LRU Cache**            | Least Recently Used Cache. Estrategia de cache onde os itens menos recentemente acessados sao removidos quando o limite de tamanho e atingido.      |
| **Multi-tenant**         | Arquitetura onde uma unica instancia do software serve multiplas empresas (tenants), cada uma com dados isolados.                                   |
| **Ownership validation** | Verificacao de que um recurso pertence ao tenant do usuario que tenta acessa-lo.                                                                    |
| **Idempotencia**         | Propriedade pela qual operacoes aplicadas multiplas vezes produzem o mesmo resultado que se aplicadas uma vez.                                      |
| **Graceful shutdown**    | Encerramento limpo do processo, fechando conexoes existentes e liberando recursos antes de encerrar.                                                |
| **Heartbeat**            | Sinal periodico enviado entre cliente e servidor para detectar conexoes ativas e identificar conexoes zumbis.                                       |
| **Namespace**            | Divisao logica de conexoes Socket.io. O Gateway usa o namespace default `/`.                                                                        |
| **Consumer group**       | Grupo de consumidores no Redis Streams que permite multiplas instancias consumirem a mesma stream com garantias de entrega.                         |
| **SETNX**                | Comando Redis que define uma chave apenas se ela nao existir. Usado para garantir idempotencia.                                                     |

---

_Documento gerado em 2026-03-28. Versao 1.0 — rascunho._
