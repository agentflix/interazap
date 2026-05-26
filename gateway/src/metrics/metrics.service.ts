import { Injectable, OnModuleInit } from '@nestjs/common';
import {
  Registry,
  collectDefaultMetrics,
  Counter,
  Histogram,
  Gauge,
} from 'prom-client';

/**
 * Serviço centralizado de coleta de métricas Prometheus para o gateway.
 *
 * Contexto: módulo metrics. Registra e expõe contadores, histogramas e gauges
 * para requisições HTTP, conexões WebSocket, Redis streams, eventos de chat,
 * latência de webhook e operações do autopilot. Utiliza um Registry isolado
 * para evitar conflitos com o registry global do prom-client.
 */
@Injectable()
export class MetricsService implements OnModuleInit {
  private readonly registry = new Registry();

  // HTTP metrics
  public readonly httpRequestsTotal: Counter<'method' | 'path' | 'status'>;
  public readonly httpRequestDuration: Histogram<'method' | 'path'>;

  // WebSocket metrics
  public readonly wsConnectionsActive: Gauge<string>;
  public readonly wsMessagesTotal: Counter<'event' | 'direction'>;

  // Redis metrics
  public readonly redisStreamMessagesTotal: Counter<'stream' | 'action'>;

  // Business metrics
  public readonly chatEventsTotal: Counter<'event_type'>;
  public readonly webhookAckLatency: Histogram<
    'provider' | 'tenant' | 'outcome'
  >;
  public readonly webhookAcksTotal: Counter<'provider' | 'tenant' | 'outcome'>;

  // Autopilot metrics (Phase 4)
  public readonly autopilotRunDuration: Histogram<
    'agent_id' | 'model' | 'status'
  >;
  public readonly autopilotToolCallsTotal: Counter<
    'agent_id' | 'tool_name' | 'status'
  >;
  public readonly autopilotTokensTotal: Counter<
    'agent_id' | 'model' | 'token_type'
  >;
  public readonly autopilotCostDollars: Counter<'agent_id' | 'model'>;
  public readonly autopilotClassifierDecisionsTotal: Counter<'decision'>;
  public readonly autopilotStreamChunksTotal: Counter<'agent_id'>;
  public readonly autopilotDelegationTotal: Counter<
    'source_agent_id' | 'target_agent_id' | 'status'
  >;
  public readonly autopilotCacheHitsTotal: Counter<'cache_type' | 'hit'>;
  public readonly autopilotSnapshotResolutionsTotal: Counter<
    'slice' | 'source'
  >;

  // Autopilot loop metrics (Phase 3)
  public readonly autopilotIterationsPerRun: Histogram<'agent_id'>;
  public readonly autopilotEarlyExitsTotal: Counter<'agent_id' | 'reason'>;
  public readonly autopilotTruncatedResponsesTotal: Counter<'agent_id'>;
  public readonly autopilotRunTokensHistogram: Histogram<'agent_id' | 'model'>;

  /**
   * Inicializa todos os contadores, histogramas e gauges Prometheus,
   * registrando-os no Registry isolado da instância.
   */
  constructor() {
    // HTTP metrics
    this.httpRequestsTotal = new Counter({
      name: 'http_requests_total',
      help: 'Total HTTP requests',
      labelNames: ['method', 'path', 'status'],
      registers: [this.registry],
    });

    this.httpRequestDuration = new Histogram({
      name: 'http_request_duration_seconds',
      help: 'HTTP request duration in seconds',
      labelNames: ['method', 'path'],
      buckets: [0.01, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],
      registers: [this.registry],
    });

    // WebSocket metrics
    this.wsConnectionsActive = new Gauge({
      name: 'websocket_connections_active',
      help: 'Number of active WebSocket connections',
      registers: [this.registry],
    });

    this.wsMessagesTotal = new Counter({
      name: 'websocket_messages_total',
      help: 'Total WebSocket messages',
      labelNames: ['event', 'direction'],
      registers: [this.registry],
    });

    // Redis metrics
    this.redisStreamMessagesTotal = new Counter({
      name: 'redis_stream_messages_total',
      help: 'Total Redis stream messages processed',
      labelNames: ['stream', 'action'],
      registers: [this.registry],
    });

    // Business metrics
    this.chatEventsTotal = new Counter({
      name: 'gateway_chat_events_total',
      help: 'Total chat events processed by gateway',
      labelNames: ['event_type'],
      registers: [this.registry],
    });

    this.webhookAckLatency = new Histogram({
      name: 'gateway_webhook_ack_latency_seconds',
      help: 'Webhook ACK latency in seconds grouped by provider, tenant and outcome',
      labelNames: ['provider', 'tenant', 'outcome'],
      buckets: [0.005, 0.01, 0.025, 0.05, 0.1, 0.15, 0.25, 0.5, 1],
      registers: [this.registry],
    });

    this.webhookAcksTotal = new Counter({
      name: 'gateway_webhook_acks_total',
      help: 'Total webhook ACK responses grouped by provider, tenant and outcome',
      labelNames: ['provider', 'tenant', 'outcome'],
      registers: [this.registry],
    });

    // Autopilot metrics
    this.autopilotRunDuration = new Histogram({
      name: 'autopilot_run_duration_seconds',
      help: 'Autopilot run duration in seconds',
      labelNames: ['agent_id', 'model', 'status'],
      buckets: [0.5, 1, 2, 5, 10, 20, 30, 60],
      registers: [this.registry],
    });

    this.autopilotToolCallsTotal = new Counter({
      name: 'autopilot_tool_calls_total',
      help: 'Total autopilot tool calls',
      labelNames: ['agent_id', 'tool_name', 'status'],
      registers: [this.registry],
    });

    this.autopilotTokensTotal = new Counter({
      name: 'autopilot_tokens_total',
      help: 'Total tokens used by autopilot',
      labelNames: ['agent_id', 'model', 'token_type'],
      registers: [this.registry],
    });

    this.autopilotCostDollars = new Counter({
      name: 'autopilot_cost_dollars',
      help: 'Total cost in dollars for autopilot operations',
      labelNames: ['agent_id', 'model'],
      registers: [this.registry],
    });

    this.autopilotClassifierDecisionsTotal = new Counter({
      name: 'autopilot_classifier_decisions_total',
      help: 'Total classifier decisions',
      labelNames: ['decision'],
      registers: [this.registry],
    });

    this.autopilotStreamChunksTotal = new Counter({
      name: 'autopilot_stream_chunks_total',
      help: 'Total streaming chunks sent',
      labelNames: ['agent_id'],
      registers: [this.registry],
    });

    this.autopilotDelegationTotal = new Counter({
      name: 'autopilot_delegation_total',
      help: 'Total agent delegations',
      labelNames: ['source_agent_id', 'target_agent_id', 'status'],
      registers: [this.registry],
    });

    this.autopilotCacheHitsTotal = new Counter({
      name: 'autopilot_cache_hits_total',
      help: 'Total cache hits/misses',
      labelNames: ['cache_type', 'hit'],
      registers: [this.registry],
    });

    this.autopilotSnapshotResolutionsTotal = new Counter({
      name: 'autopilot_snapshot_resolutions_total',
      help: 'How a slice (prompt/context/tools) was resolved: snapshot (publisher hydrated), redis (gateway cache) or api (HTTP fallback)',
      labelNames: ['slice', 'source'],
      registers: [this.registry],
    });

    // Loop metrics (Phase 3)
    this.autopilotIterationsPerRun = new Histogram({
      name: 'autopilot_iterations_per_run',
      help: 'Number of tool-call loop iterations per autopilot run',
      labelNames: ['agent_id'],
      buckets: [0, 1, 2, 3, 5, 8, 10, 15, 20],
      registers: [this.registry],
    });

    this.autopilotEarlyExitsTotal = new Counter({
      name: 'autopilot_early_exits_total',
      help: 'Total autopilot runs that ended with an early exit',
      labelNames: ['agent_id', 'reason'],
      registers: [this.registry],
    });

    this.autopilotTruncatedResponsesTotal = new Counter({
      name: 'autopilot_truncated_responses_total',
      help: 'Total completions truncated by max_tokens (finish_reason=length)',
      labelNames: ['agent_id'],
      registers: [this.registry],
    });

    this.autopilotRunTokensHistogram = new Histogram({
      name: 'autopilot_run_tokens_per_run',
      help: 'Total tokens consumed across all iterations of an autopilot run',
      labelNames: ['agent_id', 'model'],
      buckets: [100, 500, 1000, 2000, 3000, 5000, 8000, 12000, 20000],
      registers: [this.registry],
    });
  }

  /**
   * Coleta as métricas padrão do Node.js/V8 (heap, event loop, etc.)
   * no registry local ao inicializar o módulo.
   */
  onModuleInit() {
    collectDefaultMetrics({ register: this.registry });
  }

  /**
   * Expõe o Registry Prometheus interno para extensão por métricas específicas de domínio.
   * @returns Instância do Registry local
   */
  getRegistry(): Registry {
    return this.registry;
  }

  /**
   * Serializa todas as métricas registradas no formato texto do Prometheus.
   * @returns String compatível com o protocolo de scraping do Prometheus
   */
  async getMetrics(): Promise<string> {
    return this.registry.metrics();
  }

  // Helper methods

  /**
   * Registra contagem e duração de uma requisição HTTP.
   * @param method Verbo HTTP (GET, POST, etc.)
   * @param path Caminho da URL (UUIDs e IDs numéricos normalizados para `{id}`)
   * @param status Código HTTP da resposta
   * @param durationMs Duração da requisição em milissegundos
   */
  recordHttpRequest(
    method: string,
    path: string,
    status: number,
    durationMs: number,
  ): void {
    const normalizedPath = this.normalizePath(path);
    this.httpRequestsTotal.inc({
      method,
      path: normalizedPath,
      status: String(status),
    });
    this.httpRequestDuration.observe(
      { method, path: normalizedPath },
      durationMs / 1000,
    );
  }

  /**
   * Incrementa ou decrementa o gauge de conexões WebSocket ativas.
   * @param delta +1 para nova conexão, -1 para desconexão
   */
  recordWsConnection(delta: 1 | -1): void {
    this.wsConnectionsActive.inc(delta);
  }

  /**
   * Registra um evento de mensagem WebSocket.
   * @param event Nome/tipo do evento de mensagem
   * @param direction Direção da mensagem: entrada (`in`) ou saída (`out`)
   */
  recordWsMessage(event: string, direction: 'in' | 'out'): void {
    this.wsMessagesTotal.inc({ event, direction });
  }

  /**
   * Registra uma operação de leitura ou escrita em um Redis Stream.
   * @param stream Nome do Redis Stream
   * @param action Tipo de operação: `read` (leitura) ou `write` (escrita)
   */
  recordRedisStreamMessage(stream: string, action: 'read' | 'write'): void {
    this.redisStreamMessagesTotal.inc({ stream, action });
  }

  /**
   * Registra um evento de chat processado pelo gateway.
   * @param eventType Tipo do evento de chat (ex: `message.created`)
   */
  recordChatEvent(eventType: string): void {
    this.chatEventsTotal.inc({ event_type: eventType });
  }

  /**
   * Registra uma confirmação de webhook: contagem e latência por provider, tenant e outcome.
   * @param provider Nome do provider do webhook
   * @param tenant Identificador do tenant
   * @param outcome Resultado do processamento (ex: `success`, `error`)
   * @param durationMs Tempo de processamento em milissegundos
   */
  recordWebhookAck(
    provider: string,
    tenant: string,
    outcome: string,
    durationMs: number,
  ): void {
    const labels = {
      provider: this.normalizeMetricLabel(provider),
      tenant: this.normalizeMetricLabel(tenant),
      outcome: this.normalizeMetricLabel(outcome),
    };

    this.webhookAcksTotal.inc(labels);
    this.webhookAckLatency.observe(labels, durationMs / 1000);
  }

  // Autopilot metric helpers

  /**
   * Registra a duração de uma execução do autopilot.
   * @param agentId Identificador único do agente
   * @param model Modelo LLM utilizado
   * @param status Resultado da execução (ex: `success`, `error`, `timeout`)
   * @param durationSeconds Duração total em segundos
   */
  recordAutopilotRun(
    agentId: string,
    model: string,
    status: string,
    durationSeconds: number,
  ): void {
    this.autopilotRunDuration.observe(
      { agent_id: agentId, model, status },
      durationSeconds,
    );
  }

  /**
   * Registra uma invocação de ferramenta pelo autopilot.
   * @param agentId Identificador único do agente
   * @param toolName Nome da ferramenta invocada
   * @param status Resultado da chamada: `success` ou `error`
   */
  recordAutopilotToolCall(
    agentId: string,
    toolName: string,
    status: 'success' | 'error',
  ): void {
    this.autopilotToolCallsTotal.inc({
      agent_id: agentId,
      tool_name: toolName,
      status,
    });
  }

  /**
   * Registra o número de tokens consumidos pelo autopilot.
   * @param agentId Identificador único do agente
   * @param model Modelo LLM utilizado
   * @param tokenType Categoria do token: `input`, `output` ou `cached`
   * @param count Número de tokens consumidos
   */
  recordAutopilotTokens(
    agentId: string,
    model: string,
    tokenType: 'input' | 'output' | 'cached',
    count: number,
  ): void {
    this.autopilotTokensTotal.inc(
      { agent_id: agentId, model, token_type: tokenType },
      count,
    );
  }

  /**
   * Registra o custo em dólares de uma execução do autopilot.
   * @param agentId Identificador único do agente
   * @param model Modelo LLM utilizado
   * @param costDollars Custo em USD
   */
  recordAutopilotCost(
    agentId: string,
    model: string,
    costDollars: number,
  ): void {
    this.autopilotCostDollars.inc({ agent_id: agentId, model }, costDollars);
  }

  /**
   * Registra uma decisão de roteamento do classifier de autopilot.
   * @param decision Resultado do classifier: `RESPOND`, `SKIP`, `DEBOUNCE` ou `HUMAN_ONLY`
   */
  recordClassifierDecision(
    decision: 'RESPOND' | 'SKIP' | 'DEBOUNCE' | 'HUMAN_ONLY',
  ): void {
    this.autopilotClassifierDecisionsTotal.inc({ decision });
  }

  /**
   * Registra um chunk de streaming enviado ao cliente.
   * @param agentId Identificador único do agente
   */
  recordStreamChunk(agentId: string): void {
    this.autopilotStreamChunksTotal.inc({ agent_id: agentId });
  }

  /**
   * Registra um evento de delegação entre agentes.
   * @param sourceAgentId ID do agente que delegou a tarefa
   * @param targetAgentId ID do agente que recebeu a delegação
   * @param status Resultado da delegação: `success` ou `error`
   */
  recordDelegation(
    sourceAgentId: string,
    targetAgentId: string,
    status: 'success' | 'error',
  ): void {
    this.autopilotDelegationTotal.inc({
      source_agent_id: sourceAgentId,
      target_agent_id: targetAgentId,
      status,
    });
  }

  /**
   * Registra o resultado de uma consulta ao cache do autopilot.
   * @param cacheType Tipo de cache consultado: `prompt`, `context` ou `summary`
   * @param hit `true` se encontrou valor em cache, `false` em caso de miss
   */
  recordCacheHit(
    cacheType: 'prompt' | 'context' | 'summary',
    hit: boolean,
  ): void {
    this.autopilotCacheHitsTotal.inc({
      cache_type: cacheType,
      hit: hit ? 'true' : 'false',
    });
  }

  /**
   * Registra a origem a partir da qual um slice de snapshot foi resolvido.
   * Usado para validar que a hidratação pelo publisher (QW1) está funcionando.
   * @param slice Slice resolvido: `prompt`, `context` ou `tools`
   * @param source Origem dos dados: `snapshot` (ideal), `redis` (cache) ou `api` (fallback HTTP)
   */
  recordSnapshotResolution(
    slice: 'prompt' | 'context' | 'tools',
    source: 'snapshot' | 'redis' | 'api',
  ): void {
    this.autopilotSnapshotResolutionsTotal.inc({ slice, source });
  }

  /**
   * Registra o número de iterações de chamada de ferramenta em uma execução do autopilot.
   * @param agentId Identificador único do agente
   * @param count Número de iterações realizadas
   */
  recordAutopilotIterations(agentId: string, count: number): void {
    this.autopilotIterationsPerRun.observe({ agent_id: agentId }, count);
  }

  /**
   * Registra uma execução do autopilot que encerrou antecipadamente.
   * @param agentId Identificador único do agente
   * @param reason Motivo do encerramento antecipado
   */
  recordAutopilotEarlyExit(agentId: string, reason: string): void {
    this.autopilotEarlyExitsTotal.inc({ agent_id: agentId, reason });
  }

  /**
   * Registra uma resposta truncada pelo limite de `max_tokens` (finish_reason=length).
   * @param agentId Identificador único do agente
   */
  recordAutopilotTruncatedResponse(agentId: string): void {
    this.autopilotTruncatedResponsesTotal.inc({ agent_id: agentId });
  }

  /**
   * Registra o total de tokens consumidos em toda uma execução do autopilot (todas as iterações).
   * @param agentId Identificador único do agente
   * @param model Modelo LLM utilizado
   * @param tokens Total de tokens consumidos
   */
  recordAutopilotRunTokens(
    agentId: string,
    model: string,
    tokens: number,
  ): void {
    this.autopilotRunTokensHistogram.observe(
      { agent_id: agentId, model },
      tokens,
    );
  }

  /**
   * Normaliza o caminho da URL substituindo UUIDs e IDs numéricos por `{id}`.
   * @param path Caminho da URL original
   * @returns Caminho normalizado para uso como label Prometheus
   */
  private normalizePath(path: string): string {
    // Replace UUIDs
    let normalized = path.replace(
      /[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/gi,
      '{id}',
    );
    // Replace numeric IDs
    normalized = normalized.replace(/\/\d+/g, '/{id}');
    return normalized || '/';
  }

  /**
   * Normaliza um valor de label Prometheus para minúsculas sem espaços.
   * Retorna `'unknown'` se o valor resultante for vazio.
   * @param value Valor bruto do label
   * @returns Valor normalizado para uso como label Prometheus
   */
  private normalizeMetricLabel(value: string): string {
    const normalized = value.trim().toLowerCase();
    return normalized.length > 0 ? normalized : 'unknown';
  }
}
