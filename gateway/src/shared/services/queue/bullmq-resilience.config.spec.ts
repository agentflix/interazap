import {
  BULLMQ_QUEUE_CONFIGS,
  DEFAULT_JOB_OPTIONS,
  DLQ_SUFFIX,
  getBullMQQueueConfig,
  getDlqName,
  getOriginalQueueName,
  isDlqQueue,
  buildWorkerOptions,
  MAX_TOTAL_DLQ_RETRIES,
  DLQ_ALERT_THRESHOLD,
} from './bullmq-resilience.config';

describe('BullMQResilienceConfig', () => {
  describe('DEFAULT_JOB_OPTIONS', () => {
    it('should have correct default attempts', () => {
      expect(DEFAULT_JOB_OPTIONS.attempts).toBe(5);
    });

    it('should have exponential backoff', () => {
      expect(DEFAULT_JOB_OPTIONS.backoff).toEqual({
        type: 'exponential',
        delay: 1000,
      });
    });

    it('should configure removeOnComplete', () => {
      expect(DEFAULT_JOB_OPTIONS.removeOnComplete).toEqual({
        age: 3600,
        count: 1000,
      });
    });

    it('should not remove on fail', () => {
      expect(DEFAULT_JOB_OPTIONS.removeOnFail).toBe(false);
    });
  });

  describe('BULLMQ_QUEUE_CONFIGS', () => {
    it('should have internal-notifications queue', () => {
      const config = BULLMQ_QUEUE_CONFIGS['internal-notifications'];
      expect(config).toBeDefined();
      expect(config.rateLimiter).toEqual({
        max: 100,
        duration: 60000,
      });
      expect(config.concurrency).toBe(5);
    });

    it('should have ai-batch-processing queue', () => {
      const config = BULLMQ_QUEUE_CONFIGS['ai-batch-processing'];
      expect(config).toBeDefined();
      expect(config.rateLimiter?.max).toBe(10);
      expect(config.timeoutMs).toBe(180000);
    });

    it('should have webhook-retries queue', () => {
      const config = BULLMQ_QUEUE_CONFIGS['webhook-retries'];
      expect(config).toBeDefined();
      expect(config.defaultJobOptions.attempts).toBe(10);
    });

    it('should have data-export queue', () => {
      const config = BULLMQ_QUEUE_CONFIGS['data-export'];
      expect(config).toBeDefined();
      expect(config.concurrency).toBe(1);
      expect(config.timeoutMs).toBe(600000);
    });

    it('should have dlq-reprocessing queue', () => {
      const config = BULLMQ_QUEUE_CONFIGS['dlq-reprocessing'];
      expect(config).toBeDefined();
      expect(config.defaultJobOptions.attempts).toBe(1);
      expect(config.defaultJobOptions.delay).toBe(60000);
    });
  });

  describe('getDlqName', () => {
    it('should append DLQ suffix', () => {
      expect(getDlqName('my-queue')).toBe('my-queue-dlq');
    });

    it('should handle empty string', () => {
      expect(getDlqName('')).toBe('-dlq');
    });
  });

  describe('isDlqQueue', () => {
    it('should return true for DLQ names', () => {
      expect(isDlqQueue('my-queue-dlq')).toBe(true);
    });

    it('should return false for regular queue names', () => {
      expect(isDlqQueue('my-queue')).toBe(false);
    });

    it('should return false for partial match', () => {
      expect(isDlqQueue('my-dlq-queue')).toBe(false);
    });
  });

  describe('getOriginalQueueName', () => {
    it('should remove DLQ suffix', () => {
      expect(getOriginalQueueName('my-queue-dlq')).toBe('my-queue');
    });

    it('should return same name if not a DLQ', () => {
      expect(getOriginalQueueName('my-queue')).toBe('my-queue');
    });
  });

  describe('getBullMQQueueConfig', () => {
    it('should return config for known queue', () => {
      const config = getBullMQQueueConfig('internal-notifications');
      expect(config.name).toBe('internal-notifications');
    });

    it('should return default config for unknown queue', () => {
      const config = getBullMQQueueConfig('unknown-queue');
      expect(config.name).toBe('unknown-queue');
      expect(config.defaultJobOptions).toEqual(DEFAULT_JOB_OPTIONS);
      expect(config.concurrency).toBe(5);
      expect(config.timeoutMs).toBe(60000);
    });
  });

  describe('buildWorkerOptions', () => {
    it('should build worker options with rate limiter', () => {
      const config = BULLMQ_QUEUE_CONFIGS['internal-notifications'];
      const options = buildWorkerOptions(config);

      expect(options.concurrency).toBe(5);
      expect(options.limiter).toEqual({
        max: 100,
        duration: 60000,
      });
    });

    it('should build worker options without rate limiter', () => {
      const config = BULLMQ_QUEUE_CONFIGS['data-export'];
      const options = buildWorkerOptions(config);

      expect(options.concurrency).toBe(1);
      expect(options.limiter).toBeUndefined();
    });
  });

  describe('Constants', () => {
    it('should have correct DLQ suffix', () => {
      expect(DLQ_SUFFIX).toBe('-dlq');
    });

    it('should have correct max total DLQ retries', () => {
      expect(MAX_TOTAL_DLQ_RETRIES).toBe(3);
    });

    it('should have correct DLQ alert threshold', () => {
      expect(DLQ_ALERT_THRESHOLD).toBe(100);
    });
  });
});
