import { Injectable, OnModuleInit } from '@nestjs/common';
import {
  Registry,
  collectDefaultMetrics,
  Counter,
  Histogram,
  Gauge,
} from 'prom-client';

/**
 * MetricsService
 *
 * Centralized Prometheus metrics collection for the gateway.
 * Tracks HTTP requests, WebSocket connections, Redis streams,
 * chat events, webhook latency, and autopilot operations.
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
   * Initializes all Prometheus counters, histograms, and gauges.
   * Registers them with an isolated Registry instance.
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
   * Collects default Node.js/V8 metrics (heap, event loop, etc.)
   * into the local registry on module initialization.
   */
  onModuleInit() {
    collectDefaultMetrics({ register: this.registry });
  }

  /**
   * Serializes all registered metrics to the Prometheus text format.
   *
   * @returns Prometheus-compatible metrics string.
   */
  async getMetrics(): Promise<string> {
    return this.registry.metrics();
  }

  // Helper methods

  /**
   * Records an HTTP request count and duration.
   *
   * @param method  - HTTP verb (GET, POST, etc.)
   * @param path    - Request URL path (UUIDs/numeric IDs normalized).
   * @param status  - HTTP status code.
   * @param durationMs - Request duration in milliseconds.
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
   * Increments or decrements the active WebSocket connections gauge.
   *
   * @param delta - +1 for a new connection, -1 for a disconnection.
   */
  recordWsConnection(delta: 1 | -1): void {
    this.wsConnectionsActive.inc(delta);
  }

  /**
   * Records a WebSocket message event.
   *
   * @param event     - Name/type of the message event.
   * @param direction - Whether the message is inbound or outbound.
   */
  recordWsMessage(event: string, direction: 'in' | 'out'): void {
    this.wsMessagesTotal.inc({ event, direction });
  }

  /**
   * Records a Redis stream message read or write operation.
   *
   * @param stream - Name of the Redis stream.
   * @param action - Whether the message was read from or written to the stream.
   */
  recordRedisStreamMessage(stream: string, action: 'read' | 'write'): void {
    this.redisStreamMessagesTotal.inc({ stream, action });
  }

  /**
   * Records a chat event processed by the gateway.
   *
   * @param eventType - Type of chat event (e.g. message.created).
   */
  recordChatEvent(eventType: string): void {
    this.chatEventsTotal.inc({ event_type: eventType });
  }

  /**
   * Records a webhook acknowledgment: counts and latency by provider, tenant, and outcome.
   *
   * @param provider   - Webhook provider name.
   * @param tenant     - Tenant identifier.
   * @param outcome    - Result of the webhook handling (e.g. success, error).
   * @param durationMs - Time taken to acknowledge, in milliseconds.
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
   * Records the duration of an autopilot run.
   *
   * @param agentId        - Unique identifier of the agent.
   * @param model          - LLM model used.
   * @param status         - Run outcome (e.g. success, error, timeout).
   * @param durationSeconds - Total duration in seconds.
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
   * Records an autopilot tool call invocation.
   *
   * @param agentId  - Unique identifier of the agent.
   * @param toolName - Name of the tool invoked.
   * @param status   - Whether the call succeeded or errored.
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
   * Records the number of tokens consumed by an autopilot run.
   *
   * @param agentId   - Unique identifier of the agent.
   * @param model     - LLM model used.
   * @param tokenType - Category of token (input, output, or cached).
   * @param count     - Number of tokens.
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
   * Records the dollar cost incurred by an autopilot run.
   *
   * @param agentId     - Unique identifier of the agent.
   * @param model       - LLM model used.
   * @param costDollars - Cost in USD.
   */
  recordAutopilotCost(
    agentId: string,
    model: string,
    costDollars: number,
  ): void {
    this.autopilotCostDollars.inc({ agent_id: agentId, model }, costDollars);
  }

  /**
   * Records a classifier routing decision.
   *
   * @param decision - Classifier outcome (RESPOND, SKIP, DEBOUNCE, HUMAN_ONLY).
   */
  recordClassifierDecision(
    decision: 'RESPOND' | 'SKIP' | 'DEBOUNCE' | 'HUMAN_ONLY',
  ): void {
    this.autopilotClassifierDecisionsTotal.inc({ decision });
  }

  /**
   * Records a single streaming chunk sent to the client.
   *
   * @param agentId - Unique identifier of the agent.
   */
  recordStreamChunk(agentId: string): void {
    this.autopilotStreamChunksTotal.inc({ agent_id: agentId });
  }

  /**
   * Records an agent-to-agent delegation event.
   *
   * @param sourceAgentId - ID of the agent that delegated the task.
   * @param targetAgentId - ID of the agent that received the delegation.
   * @param status        - Whether the delegation succeeded or errored.
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
   * Records a cache lookup result (hit or miss).
   *
   * @param cacheType - Type of cache queried (prompt, context, or summary).
   * @param hit       - Whether the lookup returned a cached value.
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
   * Records the source from which a snapshot slice was resolved.
   * Used to validate the QW1 publisher-side hydration is working.
   *
   * @param slice  - Which slice was resolved (prompt, context or tools).
   * @param source - Where the data came from: snapshot (best), redis (good) or api (fallback).
   */
  recordSnapshotResolution(
    slice: 'prompt' | 'context' | 'tools',
    source: 'snapshot' | 'redis' | 'api',
  ): void {
    this.autopilotSnapshotResolutionsTotal.inc({ slice, source });
  }

  /**
   * Records the number of tool-call iterations executed in a single autopilot run.
   *
   * @param agentId - Unique identifier of the agent.
   * @param count   - Number of iterations performed.
   */
  recordAutopilotIterations(agentId: string, count: number): void {
    this.autopilotIterationsPerRun.observe({ agent_id: agentId }, count);
  }

  /**
   * Records an autopilot run that exited early.
   *
   * @param agentId - Unique identifier of the agent.
   * @param reason  - Reason for the early exit.
   */
  recordAutopilotEarlyExit(agentId: string, reason: string): void {
    this.autopilotEarlyExitsTotal.inc({ agent_id: agentId, reason });
  }

  /**
   * Records a response that was truncated due to max_tokens.
   *
   * @param agentId - Unique identifier of the agent.
   */
  recordAutopilotTruncatedResponse(agentId: string): void {
    this.autopilotTruncatedResponsesTotal.inc({ agent_id: agentId });
  }

  /**
   * Records the total token count for an entire autopilot run (all iterations combined).
   *
   * @param agentId - Unique identifier of the agent.
   * @param model   - LLM model used.
   * @param tokens  - Total token count.
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
   * Normalizes a URL path by replacing UUIDs and numeric IDs with {id}.
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
   * Normalizes a label value to lowercase and trims whitespace; returns 'unknown' if empty.
   */
  private normalizeMetricLabel(value: string): string {
    const normalized = value.trim().toLowerCase();
    return normalized.length > 0 ? normalized : 'unknown';
  }
}
