/**
 * AiMetricsService Tests
 *
 * Boundary tests for the AI-domain metrics facade.
 * Verifies that all domain services' intent is correctly translated to
 * MetricsService calls, and that cost estimation is accurate.
 */

import { AiMetricsService } from '../services/ai-metrics.service';
import { MetricsService } from '../../../metrics/metrics.service';

const mockMetrics = (): jest.Mocked<
  Pick<
    MetricsService,
    | 'recordAutopilotRun'
    | 'recordAutopilotTokens'
    | 'recordAutopilotCost'
    | 'recordAutopilotToolCall'
    | 'recordDelegation'
    | 'recordStreamChunk'
    | 'recordCacheHit'
    | 'recordAutopilotIterations'
    | 'recordAutopilotEarlyExit'
    | 'recordAutopilotTruncatedResponse'
    | 'recordAutopilotRunTokens'
  >
> => ({
  recordAutopilotRun: jest.fn(),
  recordAutopilotTokens: jest.fn(),
  recordAutopilotCost: jest.fn(),
  recordAutopilotToolCall: jest.fn(),
  recordDelegation: jest.fn(),
  recordStreamChunk: jest.fn(),
  recordCacheHit: jest.fn(),
  recordAutopilotIterations: jest.fn(),
  recordAutopilotEarlyExit: jest.fn(),
  recordAutopilotTruncatedResponse: jest.fn(),
  recordAutopilotRunTokens: jest.fn(),
});

describe('AiMetricsService', () => {
  let service: AiMetricsService;
  let metrics: ReturnType<typeof mockMetrics>;

  beforeEach(() => {
    metrics = mockMetrics();
    service = new AiMetricsService(metrics as unknown as MetricsService);
  });

  // ── Cost estimation ──────────────────────────────────────────────────────

  describe('estimateCostDollars', () => {
    it('should calculate cost for gpt-4o', () => {
      // 1000 input × (5/1_000_000) + 500 output × (15/1_000_000)
      // = 0.005 + 0.0075 = 0.0125
      expect(service.estimateCostDollars('gpt-4o', 1000, 500)).toBeCloseTo(
        0.0125,
        6,
      );
    });

    it('should calculate cost for gpt-4o-mini', () => {
      // 1000 × (0.15/1_000_000) + 500 × (0.6/1_000_000)
      // = 0.00015 + 0.0003 = 0.00045
      expect(service.estimateCostDollars('gpt-4o-mini', 1000, 500)).toBeCloseTo(
        0.00045,
        6,
      );
    });

    it('should fall back to gpt-4o-mini pricing for unknown models', () => {
      expect(service.estimateCostDollars('claude-3', 1000, 500)).toBeCloseTo(
        0.00045,
        6,
      );
    });

    it('should return 0 for zero tokens', () => {
      expect(service.estimateCostDollars('gpt-4o', 0, 0)).toBe(0);
    });
  });

  // ── Run completed ────────────────────────────────────────────────────────

  describe('recordRunCompleted', () => {
    it('should delegate to metricsService.recordAutopilotRun', () => {
      service.recordRunCompleted('agent-1', 'gpt-4o', 'success', 3.5);

      expect(metrics.recordAutopilotRun).toHaveBeenCalledTimes(1);
      expect(metrics.recordAutopilotRun).toHaveBeenCalledWith(
        'agent-1',
        'gpt-4o',
        'success',
        3.5,
      );
    });
  });

  // ── Token usage ──────────────────────────────────────────────────────────

  describe('recordTokenUsage', () => {
    it('should record input and output tokens in separate calls', () => {
      service.recordTokenUsage('agent-1', 'gpt-4o', 200, 80);

      expect(metrics.recordAutopilotTokens).toHaveBeenCalledTimes(2);
      expect(metrics.recordAutopilotTokens).toHaveBeenNthCalledWith(
        1,
        'agent-1',
        'gpt-4o',
        'input',
        200,
      );
      expect(metrics.recordAutopilotTokens).toHaveBeenNthCalledWith(
        2,
        'agent-1',
        'gpt-4o',
        'output',
        80,
      );
    });
  });

  // ── Run cost ─────────────────────────────────────────────────────────────

  describe('recordRunCost', () => {
    it('should estimate cost and record it via metricsService', () => {
      service.recordRunCost('agent-1', 'gpt-4o', 1000, 500);

      expect(metrics.recordAutopilotCost).toHaveBeenCalledTimes(1);
      const [calledAgent, calledModel, calledCost] = (
        metrics.recordAutopilotCost as jest.Mock
      ).mock.calls[0] as [string, string, number];
      expect(calledAgent).toBe('agent-1');
      expect(calledModel).toBe('gpt-4o');
      expect(calledCost).toBeCloseTo(0.0125, 6);
    });
  });

  // ── Tool call ─────────────────────────────────────────────────────────────

  describe('recordToolCall', () => {
    it('should record a successful tool call', () => {
      service.recordToolCall('agent-1', 'send_message', 'success');

      expect(metrics.recordAutopilotToolCall).toHaveBeenCalledWith(
        'agent-1',
        'send_message',
        'success',
      );
    });

    it('should record a failed tool call', () => {
      service.recordToolCall('agent-1', 'execute_sql', 'error');

      expect(metrics.recordAutopilotToolCall).toHaveBeenCalledWith(
        'agent-1',
        'execute_sql',
        'error',
      );
    });
  });

  // ── Delegation ────────────────────────────────────────────────────────────

  describe('recordDelegation', () => {
    it('should record a successful delegation', () => {
      service.recordDelegation('agent-1', 'agent-2', 'success');

      expect(metrics.recordDelegation).toHaveBeenCalledWith(
        'agent-1',
        'agent-2',
        'success',
      );
    });

    it('should record a failed delegation', () => {
      service.recordDelegation('agent-1', 'agent-3', 'error');

      expect(metrics.recordDelegation).toHaveBeenCalledWith(
        'agent-1',
        'agent-3',
        'error',
      );
    });
  });

  // ── Stream chunk ─────────────────────────────────────────────────────────

  describe('recordStreamChunk', () => {
    it('should delegate to metricsService.recordStreamChunk', () => {
      service.recordStreamChunk('agent-x');

      expect(metrics.recordStreamChunk).toHaveBeenCalledWith('agent-x');
    });
  });

  // ── Cache hits ────────────────────────────────────────────────────────────

  describe('recordPromptCacheHit', () => {
    it('should record prompt cache hit', () => {
      service.recordPromptCacheHit(true);
      expect(metrics.recordCacheHit).toHaveBeenCalledWith('prompt', true);
    });

    it('should record prompt cache miss', () => {
      service.recordPromptCacheHit(false);
      expect(metrics.recordCacheHit).toHaveBeenCalledWith('prompt', false);
    });
  });

  describe('recordContextCacheHit', () => {
    it('should record context cache hit', () => {
      service.recordContextCacheHit(true);
      expect(metrics.recordCacheHit).toHaveBeenCalledWith('context', true);
    });

    it('should record context cache miss', () => {
      service.recordContextCacheHit(false);
      expect(metrics.recordCacheHit).toHaveBeenCalledWith('context', false);
    });
  });

  // ── Phase 3: loop metrics ─────────────────────────────────────────────────

  describe('recordIterationsPerRun', () => {
    it('should delegate to metricsService.recordAutopilotIterations', () => {
      service.recordIterationsPerRun('agent-1', 3);

      expect(metrics.recordAutopilotIterations).toHaveBeenCalledTimes(1);
      expect(metrics.recordAutopilotIterations).toHaveBeenCalledWith(
        'agent-1',
        3,
      );
    });

    it('should forward zero iterations (no-loop run)', () => {
      service.recordIterationsPerRun('agent-x', 0);

      expect(metrics.recordAutopilotIterations).toHaveBeenCalledWith(
        'agent-x',
        0,
      );
    });
  });

  describe('recordTotalTokensPerRun', () => {
    it('should delegate to metricsService.recordAutopilotRunTokens', () => {
      service.recordTotalTokensPerRun('agent-1', 'gpt-4o', 1500);

      expect(metrics.recordAutopilotRunTokens).toHaveBeenCalledTimes(1);
      expect(metrics.recordAutopilotRunTokens).toHaveBeenCalledWith(
        'agent-1',
        'gpt-4o',
        1500,
      );
    });
  });

  describe('recordEarlyExit', () => {
    it('should record early exit with reason token_budget_exceeded', () => {
      service.recordEarlyExit('agent-1', 'token_budget_exceeded');

      expect(metrics.recordAutopilotEarlyExit).toHaveBeenCalledTimes(1);
      expect(metrics.recordAutopilotEarlyExit).toHaveBeenCalledWith(
        'agent-1',
        'token_budget_exceeded',
      );
    });

    it('should record early exit with reason send_message_completed', () => {
      service.recordEarlyExit('agent-2', 'send_message_completed');

      expect(metrics.recordAutopilotEarlyExit).toHaveBeenCalledWith(
        'agent-2',
        'send_message_completed',
      );
    });

    it('should record early exit with reason max_iterations_reached', () => {
      service.recordEarlyExit('agent-3', 'max_iterations_reached');

      expect(metrics.recordAutopilotEarlyExit).toHaveBeenCalledWith(
        'agent-3',
        'max_iterations_reached',
      );
    });
  });

  describe('recordTruncatedResponse', () => {
    it('should delegate to metricsService.recordAutopilotTruncatedResponse', () => {
      service.recordTruncatedResponse('agent-1');

      expect(metrics.recordAutopilotTruncatedResponse).toHaveBeenCalledTimes(1);
      expect(metrics.recordAutopilotTruncatedResponse).toHaveBeenCalledWith(
        'agent-1',
      );
    });
  });
});
