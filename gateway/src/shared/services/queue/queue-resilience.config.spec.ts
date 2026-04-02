import {
  calculateRetryDelay,
  DEFAULT_RETRY_CONFIG,
  getQueueConfig,
  QUEUE_CONFIGS,
} from './queue-resilience.config';

describe('QueueResilienceConfig', () => {
  describe('getQueueConfig', () => {
    it('should return config for known stream', () => {
      const config = getQueueConfig('chat.outbound_message');

      expect(config.stream).toBe('chat.outbound_message');
      expect(config.group).toBe('gateway-outbound');
      expect(config.retry.maxAttempts).toBe(5);
    });

    it('should return default config for unknown stream', () => {
      const config = getQueueConfig('unknown.stream');

      expect(config.stream).toBe('unknown.stream');
      expect(config.group).toBe('gateway-unknown-stream');
      expect(config.retry).toEqual(DEFAULT_RETRY_CONFIG);
    });
  });

  describe('calculateRetryDelay', () => {
    it('should calculate exponential backoff', () => {
      const config = {
        maxAttempts: 5,
        baseDelayMs: 1000,
        exponentialBase: 2,
        maxDelayMs: 60000,
      };

      // First attempt: ~1000ms (with up to 20% jitter)
      const delay1 = calculateRetryDelay(1, config);
      expect(delay1).toBeGreaterThanOrEqual(800);
      expect(delay1).toBeLessThanOrEqual(1200);

      // Second attempt: ~2000ms (with up to 20% jitter)
      const delay2 = calculateRetryDelay(2, config);
      expect(delay2).toBeGreaterThanOrEqual(1600);
      expect(delay2).toBeLessThanOrEqual(2400);

      // Third attempt: ~4000ms (with up to 20% jitter)
      const delay3 = calculateRetryDelay(3, config);
      expect(delay3).toBeGreaterThanOrEqual(3200);
      expect(delay3).toBeLessThanOrEqual(4800);
    });

    it('should respect max delay', () => {
      const config = {
        maxAttempts: 10,
        baseDelayMs: 1000,
        exponentialBase: 2,
        maxDelayMs: 5000,
      };

      // 10th attempt would be 2^9 * 1000 = 512000, but capped at 5000
      const delay = calculateRetryDelay(10, config);
      expect(delay).toBeLessThanOrEqual(5000);
    });

    it('should add jitter to delays', () => {
      const config = DEFAULT_RETRY_CONFIG;
      const delays = new Set<number>();

      // Generate multiple delays for same attempt
      for (let i = 0; i < 10; i++) {
        delays.add(calculateRetryDelay(1, config));
      }

      // Should have some variation due to jitter
      expect(delays.size).toBeGreaterThan(1);
    });
  });

  describe('QUEUE_CONFIGS', () => {
    it('should have chat.outbound_message config', () => {
      expect(QUEUE_CONFIGS['chat.outbound_message']).toBeDefined();
      expect(QUEUE_CONFIGS['chat.outbound_message'].rateLimit).toBeDefined();
    });

    it('should have ai.chat config', () => {
      expect(QUEUE_CONFIGS['ai.chat']).toBeDefined();
      expect(QUEUE_CONFIGS['ai.chat'].timeoutMs).toBe(180000);
    });

    it('should have webhooks.outbound config', () => {
      expect(QUEUE_CONFIGS['webhooks.outbound']).toBeDefined();
      expect(QUEUE_CONFIGS['webhooks.outbound'].retry.maxAttempts).toBe(10);
    });

    it('all configs should have DLQ enabled', () => {
      for (const config of Object.values(QUEUE_CONFIGS)) {
        expect(config.dlq.enabled).toBe(true);
      }
    });
  });
});
