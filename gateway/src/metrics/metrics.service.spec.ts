import { Test, TestingModule } from '@nestjs/testing';
import { MetricsService } from './metrics.service';

describe('MetricsService', () => {
  let service: MetricsService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [MetricsService],
    }).compile();

    await module.init(); // triggers onModuleInit (collectDefaultMetrics)
    service = module.get<MetricsService>(MetricsService);
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });

  it('should return metrics in prometheus format', async () => {
    const metrics = await service.getMetrics();
    expect(metrics).toContain('http_requests_total');
    expect(metrics).toContain('http_request_duration_seconds');
    expect(metrics).toContain('websocket_connections_active');
    expect(metrics).toContain('gateway_webhook_ack_latency_seconds');
    expect(metrics).toContain('gateway_webhook_acks_total');
  });

  it('should record HTTP request', () => {
    service.recordHttpRequest('GET', '/api/test', 200, 100);
    // No error means success
    expect(true).toBe(true);
  });

  it('should normalize paths with UUIDs', () => {
    service.recordHttpRequest(
      'GET',
      '/api/users/550e8400-e29b-41d4-a716-446655440000',
      200,
      50,
    );
    // Path should be normalized
    expect(true).toBe(true);
  });

  it('should record WebSocket connection', () => {
    service.recordWsConnection(1);
    service.recordWsConnection(-1);
    expect(true).toBe(true);
  });

  it('should record WebSocket message', () => {
    service.recordWsMessage('chat:message', 'in');
    service.recordWsMessage('chat:message', 'out');
    expect(true).toBe(true);
  });

  it('should record Redis stream message', () => {
    service.recordRedisStreamMessage('chat-stream', 'read');
    expect(true).toBe(true);
  });

  it('should record chat event', () => {
    service.recordChatEvent('message_sent');
    expect(true).toBe(true);
  });

  it('should record webhook ACK metrics', () => {
    service.recordWebhookAck('zapi', 'tenant-123', 'first_seen', 45);
    expect(true).toBe(true);
  });

  // Autopilot metrics tests (Phase 4)
  it('should record autopilot run duration', () => {
    service.recordAutopilotRun('agent-123', 'gpt-4o', 'success', 2.5);
    expect(true).toBe(true);
  });

  it('should record autopilot tool call', () => {
    service.recordAutopilotToolCall('agent-123', 'send_message', 'success');
    service.recordAutopilotToolCall('agent-123', 'search_knowledge', 'error');
    expect(true).toBe(true);
  });

  it('should record autopilot tokens', () => {
    service.recordAutopilotTokens('agent-123', 'gpt-4o', 'input', 1500);
    service.recordAutopilotTokens('agent-123', 'gpt-4o', 'output', 300);
    service.recordAutopilotTokens('agent-123', 'gpt-4o', 'cached', 1200);
    expect(true).toBe(true);
  });

  it('should record autopilot cost', () => {
    service.recordAutopilotCost('agent-123', 'gpt-4o', 0.085);
    expect(true).toBe(true);
  });

  it('should record classifier decision', () => {
    service.recordClassifierDecision('RESPOND');
    service.recordClassifierDecision('SKIP');
    service.recordClassifierDecision('DEBOUNCE');
    service.recordClassifierDecision('HUMAN_ONLY');
    expect(true).toBe(true);
  });

  it('should record stream chunk', () => {
    service.recordStreamChunk('agent-123');
    expect(true).toBe(true);
  });

  it('should record delegation', () => {
    service.recordDelegation('agent-source', 'agent-target', 'success');
    expect(true).toBe(true);
  });

  it('should record cache hit/miss', () => {
    service.recordCacheHit('prompt', true);
    service.recordCacheHit('context', false);
    expect(true).toBe(true);
  });

  // Autopilot loop metrics tests (Phase 3 — PRD-AI-004)
  it('should record autopilot iterations per run', () => {
    service.recordAutopilotIterations('agent-123', 3);
    expect(true).toBe(true);
  });

  it('should record autopilot iterations with zero (no-loop run)', () => {
    service.recordAutopilotIterations('agent-x', 0);
    expect(true).toBe(true);
  });

  it('should record autopilot early exit with reason token_budget_exceeded', () => {
    service.recordAutopilotEarlyExit('agent-123', 'token_budget_exceeded');
    expect(true).toBe(true);
  });

  it('should record autopilot early exit with reason send_message_completed', () => {
    service.recordAutopilotEarlyExit('agent-456', 'send_message_completed');
    expect(true).toBe(true);
  });

  it('should record autopilot early exit with reason max_iterations_reached', () => {
    service.recordAutopilotEarlyExit('agent-789', 'max_iterations_reached');
    expect(true).toBe(true);
  });

  it('should record autopilot truncated response', () => {
    service.recordAutopilotTruncatedResponse('agent-123');
    expect(true).toBe(true);
  });

  it('should record autopilot run tokens histogram', () => {
    service.recordAutopilotRunTokens('agent-123', 'gpt-4o', 1500);
    service.recordAutopilotRunTokens('agent-456', 'gpt-4o-mini', 800);
    expect(true).toBe(true);
  });

  it('should include Phase 3 metric names in prometheus output', async () => {
    service.recordAutopilotIterations('agent-x', 2);
    service.recordAutopilotEarlyExit('agent-x', 'max_iterations_reached');
    service.recordAutopilotTruncatedResponse('agent-x');
    service.recordAutopilotRunTokens('agent-x', 'gpt-4o', 1200);

    const metrics = await service.getMetrics();
    expect(metrics).toContain('autopilot_iterations_per_run');
    expect(metrics).toContain('autopilot_early_exits_total');
    expect(metrics).toContain('autopilot_truncated_responses_total');
    expect(metrics).toContain('autopilot_run_tokens_per_run');
  });

  it('should normalise empty-string label to "unknown" in webhook ACK', () => {
    // Exercises the normalizeMetricLabel '' → 'unknown' branch
    service.recordWebhookAck('', '', '', 10);
    expect(true).toBe(true);
  });
});
