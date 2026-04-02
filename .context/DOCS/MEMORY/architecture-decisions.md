# Architecture Decision Records — AgentFlix

> Registro das decisões arquiteturais do projeto. Cada ADR documenta o contexto, a decisão, alternativas consideradas e consequências.

---

## ADR-001 — DDD com Domain Folders

### Contexto
O projeto precisa de uma arquitetura que suporte 11 módulos de domínio independentes, com fronteiras claras entre eles, facilitando manutenção e evolução por múltiplos agentes/desenvolvedores.

### Decisão
Adotar **Domain-Driven Design** com organização por pastas de domínio em `src/Domain/{Domain}/`, onde cada domínio contém: Controllers, Actions, DTOs, Models, Policies, FormRequests, Resources e Routes.

### Justificativa
- Separação clara de responsabilidades por domínio
- Facilita trabalho paralelo entre agentes/desenvolvedores
- Reduz acoplamento entre módulos
- Convenção previsível para localizar qualquer artefato

### Alternativas consideradas
- **MVC padrão do Laravel**: Rejeitado — mistura domínios em pastas genéricas (`app/Http/Controllers/`), escala mal com 11+ módulos
- **Microservices**: Rejeitado — overhead operacional desproporcional para o tamanho atual do time/projeto
- **Modular monolith com Service Providers**: Parcialmente adotado — cada domínio pode ter seu Service Provider, mas a organização principal é por pastas de domínio

### Consequências
- (+) Organização clara e previsível
- (+) Cada domínio é autocontido
- (-) Mais boilerplate por entidade (Controller, DTO, Action, Resource, Policy, FormRequest)
- (-) Requer disciplina para manter as fronteiras entre domínios

---

## ADR-002 — Multi-tenant com Isolamento por Tenant

### Contexto
O AgentFlix é um SaaS que atende múltiplas empresas. Cada empresa (tenant) deve ter seus dados completamente isolados dos demais.

### Decisão
Implementar **isolamento por tenant** usando filtros a nível de query (column-based tenancy) com trait `BelongsToTenant` em todos os models que armazenam dados de tenant. Campo `tenant_id` obrigatório.

### Justificativa
- Equilíbrio entre isolamento e simplicidade operacional
- Um único banco com filtros é mais simples de manter que múltiplos schemas/bancos
- Trait garante que o filtro nunca seja esquecido

### Alternativas consideradas
- **Database-per-tenant**: Rejeitado — overhead de gerenciamento de múltiplos bancos, migrations em paralelo, conexões
- **Schema-per-tenant**: Rejeitado — complexidade de PostgreSQL schemas sem benefício proporcional ao cenário atual
- **Row-level security (RLS) do PostgreSQL**: Considerado para futuro — adicionaria uma camada extra de proteção a nível de banco

### Consequências
- (+) Simplicidade operacional (um banco, um schema)
- (+) Migrations aplicadas uma vez
- (+) Trait `BelongsToTenant` como safety net
- (-) Risco de vazamento de dados se filtro for esquecido (mitigado pela trait)
- (-) Performance pode ser afetada em scale extremo (mitigado por índices em `tenant_id`)

---

## ADR-003 — UUID como Primary Keys

### Contexto
Necessidade de IDs que não exponham informações sobre a quantidade de registros e que sejam seguros para uso em APIs públicas.

### Decisão
Utilizar **UUID v4** como primary key em todas as tabelas. Nunca usar auto-increment.

### Justificativa
- Não expõe sequências ao cliente (segurança)
- Possibilita geração client-side (útil para offline-first ou idempotência)
- Evita conflitos em cenários de merge/sync entre ambientes
- Padrão já consolidado em APIs REST modernas

### Alternativas consideradas
- **Auto-increment**: Rejeitado — expõe contagem de registros, problemas em distributed systems
- **ULID**: Considerado — vantagem de ser ordenável por tempo, mas UUID é mais amplamente suportado
- **Snowflake IDs**: Rejeitado — complexidade desnecessária para o cenário

### Consequências
- (+) IDs seguros e não previsíveis
- (+) Geração client-side possível
- (-) Maior consumo de storage (16 bytes vs 4 bytes)
- (-) Índices ligeiramente mais lentos que auto-increment (mitigado por UUIDv7 se necessário no futuro)

---

## ADR-004 — Gateway Layer (NestJS) como API Relay

### Contexto
O frontend precisa de funcionalidades que o Laravel não provê nativamente com a mesma eficiência: WebSocket management, processamento assíncrono de webhooks com BullMQ, e circuit breaking em chamadas externas.

### Decisão
Adicionar uma **camada de Gateway** em NestJS entre o frontend e o backend Laravel. O gateway atua como relay, transformando e roteando requisições.

### Justificativa
- NestJS tem suporte nativo excelente para WebSocket (Socket.io)
- BullMQ para processamento assíncrono performático
- Circuit breaker pattern nativo para resiliência
- Separação de concerns: Laravel foca em business logic, NestJS em comunicação

### Alternativas consideradas
- **Chamar Laravel diretamente do frontend**: Rejeitado — sem WebSocket nativo, sem BullMQ
- **Laravel Reverb only**: Parcialmente adotado para broadcasting, mas insuficiente para todas as necessidades do gateway
- **API Gateway dedicado (Kong, AWS API Gateway)**: Rejeitado — overhead operacional para o tamanho do projeto

### Consequências
- (+) WebSocket management performático
- (+) Processamento assíncrono robusto (BullMQ)
- (+) Circuit breaker para resiliência
- (-) Latência adicional (hop extra na cadeia)
- (-) Mais uma camada para manter e deployar
- (-) Duplicação parcial de lógica de autenticação

---

## ADR-005 — WebSocket via Laravel Reverb + Socket.io

### Contexto
O módulo de Chat requer comunicação em tempo real bidirecional com latência mínima para mensagens de WhatsApp.

### Decisão
Utilizar **Laravel Reverb** para broadcasting de eventos do backend e **Socket.io** (via NestJS gateway) para a conexão WebSocket com o frontend.

### Justificativa
- Reverb é a solução oficial do Laravel para WebSocket broadcasting
- Socket.io tem reconexão automática, rooms, e namespaces
- Combinação permite broadcasting do Laravel + gerenciamento fino de conexões no gateway

### Alternativas consideradas
- **Pusher**: Rejeitado — custo e dependência de serviço externo
- **Laravel WebSockets (beyondcode)**: Rejeitado — descontinuado em favor do Reverb
- **Server-Sent Events (SSE)**: Rejeitado — unidirecional, insuficiente para chat

### Consequências
- (+) Solução oficial e bem mantida
- (+) Reconexão automática client-side
- (+) Broadcasting nativo do Laravel
- (-) Duas tecnologias de WebSocket (Reverb + Socket.io) aumentam complexidade

---

## ADR-006 — pgvector para Embeddings e RAG

### Contexto
O módulo de IA precisa armazenar e pesquisar embeddings vetoriais para funcionalidades de RAG (Retrieval-Augmented Generation), como busca semântica em conversas e documentos do cliente.

### Decisão
Utilizar a extensão **pgvector** do PostgreSQL para armazenamento e pesquisa de embeddings vetoriais.

### Justificativa
- Reutiliza o PostgreSQL existente (sem novo serviço)
- Suporta busca por similaridade (cosine, L2, inner product)
- Integração nativa com Eloquent via casts/scopes
- Performance adequada para o volume esperado

### Alternativas consideradas
- **Pinecone**: Rejeitado — custo adicional e dependência externa
- **Weaviate / Qdrant**: Rejeitado — overhead operacional de mais um serviço
- **Redis Vector Search**: Considerado — menor maturidade para queries complexas

### Consequências
- (+) Zero overhead operacional (mesmo banco)
- (+) Transações ACID com dados vetoriais
- (+) Sem custo adicional de infraestrutura
- (-) Performance inferior a bancos vetoriais dedicados em volume muito alto
- (-) Requer extensão compilada no PostgreSQL

---

## ADR-007 — BullMQ para Processamento Assíncrono no Gateway

### Contexto
O gateway NestJS precisa processar webhooks de forma assíncrona (WhatsApp, pagamentos) com garantia de entrega, retry e monitoramento.

### Decisão
Utilizar **BullMQ** (baseado em Redis) para filas de trabalho no gateway NestJS.

### Justificativa
- Integração nativa com NestJS (`@nestjs/bullmq`)
- Baseado em Redis (já presente na stack)
- Suporta retry, delay, prioridade, rate limiting
- Dashboard de monitoramento (Bull Board)

### Alternativas consideradas
- **Laravel Queues diretamente**: Rejeitado — webhook processing precisa acontecer no gateway por performance (ACK < 150ms)
- **RabbitMQ**: Rejeitado — overhead operacional sem benefício proporcional
- **AWS SQS**: Rejeitado — dependência de cloud específica

### Consequências
- (+) Performance excelente para webhook processing
- (+) Retry automático com backoff
- (+) Monitoramento via Bull Board
- (-) Dados de fila em Redis (volátil se não persistido)
- (-) Mais uma abstração de queue além do Laravel Queues

---

## ADR-008 — Sanctum + Spatie para Autenticação e Autorização

### Contexto
Necessidade de autenticação de API (tokens) e autorização granular (roles e permissions) com suporte multi-tenant.

### Decisão
Utilizar **Laravel Sanctum** para autenticação via tokens e **Spatie Laravel Permission** para gestão de roles e permissions.

### Justificativa
- Sanctum é a solução oficial do Laravel para SPA/API auth
- Spatie Permission é o pacote mais maduro e testado do ecossistema
- Ambos integram nativamente com Eloquent e middleware do Laravel
- Permissions granulares por módulo/ação são possíveis

### Alternativas consideradas
- **Passport (OAuth2)**: Rejeitado — complexidade desnecessária para SPA-to-API auth
- **JWT (tymondesigns)**: Rejeitado — Sanctum é mais simples e oficialmente suportado
- **Custom RBAC**: Rejeitado — reinventar o que Spatie resolve bem seria desperdício

### Consequências
- (+) Setup simples e documentação abundante
- (+) Token management nativo

---

## ADR-009 — Fluxo dedicado como fonte de verdade para transferência entre atendentes no Chat

### Contexto
O módulo de Chat possui dois fluxos de transferência coexistindo: um endpoint legado em `POST /chat/tickets/{id}/transfer`, hoje consumido pela tela principal, e um endpoint dedicado em `POST /chat/tickets/{ticketId}/transfers`, que já persiste histórico formal com `reason` em `chat_ticket_transfers`. A demanda de registrar o motivo no chat como mensagem oculta para o cliente exige eliminar a ambiguidade sobre qual contrato representa a transferência entre atendentes.

### Decisão
Para transferências entre atendentes, o endpoint dedicado `POST /chat/tickets/{ticketId}/transfers` passa a ser a fonte de verdade para o motivo e o histórico do handoff. O mesmo fluxo deve espelhar o motivo na timeline do ticket como `ChatMessage.type = internal_note` com `source = system`. Mensagens `internal_note` devem ser persistidas e emitidas para clientes internos em realtime, mas não podem ser enviadas ao provedor externo.

### Justificativa
- O endpoint dedicado já modela `reason` e histórico em `chat_ticket_transfers`
- Evita duplicar regra entre `ChatTicketController` e `ChatTicketTransferController`
- Permite manter uma única trilha auditável para o motivo da transferência
- Resolve o requisito sem migração adicional no caso de transferência entre atendentes

### Alternativas consideradas
- **Estender o endpoint legado `/transfer`**: Rejeitado — perpetua dois contratos sobre o mesmo comportamento e ignora o histórico dedicado já existente
- **Persistir apenas em `chat_ticket_transfers` sem refletir na timeline**: Rejeitado — não atende ao requisito operacional de exibir o motivo no chat interno

---

## ADR-010 — Expansão de modelos Gemini via catálogo, não via novos providers

### Contexto
O domínio de IA precisa suportar múltiplas gerações de modelos Google Gemini no mesmo fluxo operacional, incluindo Gemini 2.5 Pro, Gemini 2.5 Flash, Gemini 3.1 e Gemini 3.1 Flash. A arquitetura atual já separa provider (`google`) de modelo (`model_name`), mas a expansão poderia ser implementada de duas formas: multiplicando adapters por família de modelo ou mantendo um único adapter Google com catálogo de modelos configurável.

### Decisão
Adotar um único `GeminiProviderAdapter` para o provider `google`, tratando a inclusão de novas versões de modelo Gemini como expansão de catálogo em dados/configuração (`ai_model_pricings`, frontend options e defaults), sem criar novos providers ou novos adapters por geração.

### Justificativa
- Preserva a fronteira arquitetural correta: provider e modelo são responsabilidades distintas
- Evita duplicação de lógica de autenticação, tradução de payload, error mapping e circuit breaker
- Simplifica rollout de novos modelos Google no futuro
- Mantém `AIProviderFactory` estável, com uma entrada por provider real

### Alternativas consideradas
- **Criar um adapter por geração de modelo Gemini**: Rejeitado — aumentaria acoplamento e duplicação sem benefício técnico
- **Criar providers separados (`google-gemini-2-5`, `google-gemini-3-1`)**: Rejeitado — viola o conceito de provider e polui a factory
- **Resolver tudo via strings soltas no frontend sem catálogo persistido**: Rejeitado — perde controle de pricing, ativação e auditoria

### Consequências
- (+) Novos modelos Gemini entram principalmente por dados, não por estrutura de código
- (+) Menor custo de manutenção no gateway
- (+) Pricing, ativação e display podem variar sem alterar o contrato do provider
- (-) Exige disciplina para validar IDs canônicos de modelo antes de persisti-los em catálogo
- (-) O adapter precisa lidar com diferenças menores entre capabilities por modelo dentro do mesmo provider
- **Criar tabela separada para notas internas de transferência**: Rejeitado — complexidade desnecessária, já que a timeline de mensagens é o local natural para a visualização operacional

### Consequências
- (+) Há uma única fonte de verdade para o motivo da transferência entre atendentes
- (+) O histórico formal e a timeline interna ficam coerentes entre si
- (+) O requisito de ocultação para o cliente fica concentrado na regra de `internal_note`
- (-) O fluxo de transferência por departamento permanece fora deste contrato e exigirá plano específico se também precisar de motivo obrigatório
- (-) O frontend principal precisa migrar do endpoint legado para o dedicado
- (+) Permissions granulares com cache
- (-) Sanctum tokens não possuem claims como JWT (mitigado por middleware de tenant)
- (-) Spatie permissions ficam em tabelas globais (requer cuidado com tenant isolation)
