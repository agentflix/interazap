import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { BullMQQueueFactory } from './bullmq-queue-factory.service';
import { BullMQDlqService } from './bullmq-dlq.service';
import { DlqReprocessingWorker } from './dlq-reprocessing.worker';
import { QueueRateLimiterService } from './queue-rate-limiter.service';

// Mock bullmq
jest.mock('bullmq', () => {
  const mockQueue = {
    name: 'test-queue',
    add: jest.fn().mockResolvedValue({ id: 'job-1' }),
    getJobCounts: jest.fn().mockResolvedValue({
      waiting: 5,
      active: 2,
      completed: 100,
      failed: 3,
      delayed: 1,
    }),
    isPaused: jest.fn().mockResolvedValue(false),
    pause: jest.fn().mockResolvedValue(undefined),
    resume: jest.fn().mockResolvedValue(undefined),
    drain: jest.fn().mockResolvedValue(undefined),
    clean: jest.fn().mockResolvedValue(['job-1', 'job-2']),
    close: jest.fn().mockResolvedValue(undefined),
  };

  const mockWorker = {
    on: jest.fn(),
    close: jest.fn().mockResolvedValue(undefined),
  };

  return {
    Queue: jest.fn().mockImplementation((name: string) => ({
      ...mockQueue,
      name,
    })),
    Worker: jest.fn().mockImplementation(() => mockWorker),
    QueueEvents: jest.fn().mockImplementation(() => ({
      close: jest.fn().mockResolvedValue(undefined),
    })),
  };
});

describe('BullMQQueueFactory', () => {
  let factory: BullMQQueueFactory;
  let dlqService: jest.Mocked<BullMQDlqService>;
  let dlqReprocessingWorker: jest.Mocked<DlqReprocessingWorker>;
  let rateLimiter: jest.Mocked<QueueRateLimiterService>;
  let configService: jest.Mocked<ConfigService>;

  beforeEach(async () => {
    dlqService = {
      registerDlqQueue: jest.fn(),
      captureFailedJob: jest.fn().mockResolvedValue(true),
    } as unknown as jest.Mocked<BullMQDlqService>;

    dlqReprocessingWorker = {
      registerQueue: jest.fn().mockResolvedValue(undefined),
      shutdown: jest.fn().mockResolvedValue(undefined),
    } as unknown as jest.Mocked<DlqReprocessingWorker>;

    rateLimiter = {
      consume: jest.fn().mockResolvedValue({ allowed: true }),
    } as unknown as jest.Mocked<QueueRateLimiterService>;

    configService = {
      get: jest.fn().mockReturnValue('redis://localhost:6379'),
    } as unknown as jest.Mocked<ConfigService>;

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        BullMQQueueFactory,
        { provide: ConfigService, useValue: configService },
        { provide: BullMQDlqService, useValue: dlqService },
        { provide: DlqReprocessingWorker, useValue: dlqReprocessingWorker },
        { provide: QueueRateLimiterService, useValue: rateLimiter },
      ],
    }).compile();

    factory = module.get<BullMQQueueFactory>(BullMQQueueFactory);
  });

  afterEach(async () => {
    await factory.onModuleDestroy();
  });

  describe('onModuleInit', () => {
    it('should initialize without errors', () => {
      expect(() => factory.onModuleInit()).not.toThrow();
    });
  });

  describe('createQueue', () => {
    it('should create a new queue', () => {
      const queue = factory.createQueue('test-queue');

      expect(queue).toBeDefined();
      expect(queue.name).toBe('test-queue');
    });

    it('should return existing queue if already created', () => {
      const queue1 = factory.createQueue('test-queue');
      const queue2 = factory.createQueue('test-queue');

      expect(queue1).toBe(queue2);
    });

    it('should create DLQ for queue', () => {
      factory.createQueue('test-queue');

      expect(dlqService.registerDlqQueue).toHaveBeenCalledWith(
        'test-queue',
        expect.any(Object),
      );
    });

    it('should register with DLQ reprocessing worker', () => {
      factory.createQueue('test-queue');

      expect(dlqReprocessingWorker.registerQueue).toHaveBeenCalled();
    });
  });

  describe('createWorker', () => {
    it('should create a new worker', () => {
      const processor = jest.fn().mockResolvedValue({ result: 'ok' });
      const worker = factory.createWorker('test-queue', processor);

      expect(worker).toBeDefined();
    });

    it('should return existing worker if already created', () => {
      const processor = jest.fn();
      const worker1 = factory.createWorker('test-queue', processor);
      const worker2 = factory.createWorker('test-queue', processor);

      expect(worker1).toBe(worker2);
    });
  });

  describe('getQueue', () => {
    it('should return queue if exists', () => {
      factory.createQueue('test-queue');
      const queue = factory.getQueue('test-queue');

      expect(queue).toBeDefined();
    });

    it('should return undefined if queue does not exist', () => {
      const queue = factory.getQueue('nonexistent');

      expect(queue).toBeUndefined();
    });
  });

  describe('getQueueStats', () => {
    it('should return queue statistics', async () => {
      factory.createQueue('test-queue');
      const stats = await factory.getQueueStats('test-queue');

      expect(stats).toEqual({
        name: 'test-queue',
        waiting: 5,
        active: 2,
        completed: 100,
        failed: 3,
        delayed: 1,
        paused: false,
      });
    });

    it('should return null for non-existent queue', async () => {
      const stats = await factory.getQueueStats('nonexistent');

      expect(stats).toBeNull();
    });
  });

  describe('getAllQueueStats', () => {
    it('should return stats for all queues', async () => {
      factory.createQueue('queue-1');
      factory.createQueue('queue-2');

      const stats = await factory.getAllQueueStats();

      expect(stats.length).toBe(2);
    });

    it('should exclude DLQ queues', async () => {
      factory.createQueue('queue-1');

      const stats = await factory.getAllQueueStats();

      const dlqStats = stats.filter((s) => s.name.endsWith('-dlq'));
      expect(dlqStats.length).toBe(0);
    });
  });

  describe('getQueueNames', () => {
    it('should return all queue names', () => {
      factory.createQueue('queue-1');
      factory.createQueue('queue-2');

      const names = factory.getQueueNames();

      expect(names).toContain('queue-1');
      expect(names).toContain('queue-2');
    });

    it('should exclude DLQ names', () => {
      factory.createQueue('queue-1');

      const names = factory.getQueueNames();

      const dlqNames = names.filter((n) => n.endsWith('-dlq'));
      expect(dlqNames.length).toBe(0);
    });
  });

  describe('pauseQueue', () => {
    it('should pause queue', async () => {
      const queue = factory.createQueue('test-queue');
      const result = await factory.pauseQueue('test-queue');

      expect(result).toBe(true);
      expect(queue.pause).toHaveBeenCalled();
    });

    it('should return false for non-existent queue', async () => {
      const result = await factory.pauseQueue('nonexistent');

      expect(result).toBe(false);
    });
  });

  describe('resumeQueue', () => {
    it('should resume queue', async () => {
      const queue = factory.createQueue('test-queue');
      const result = await factory.resumeQueue('test-queue');

      expect(result).toBe(true);
      expect(queue.resume).toHaveBeenCalled();
    });

    it('should return false for non-existent queue', async () => {
      const result = await factory.resumeQueue('nonexistent');

      expect(result).toBe(false);
    });
  });

  describe('drainQueue', () => {
    it('should drain queue', async () => {
      const queue = factory.createQueue('test-queue');
      await factory.drainQueue('test-queue');

      expect(queue.drain).toHaveBeenCalledWith(true);
    });

    it('should not throw for non-existent queue', async () => {
      await expect(factory.drainQueue('nonexistent')).resolves.not.toThrow();
    });
  });

  describe('cleanQueue', () => {
    it('should clean queue', async () => {
      const queue = factory.createQueue('test-queue');
      const cleaned = await factory.cleanQueue('test-queue');

      expect(cleaned).toEqual(['job-1', 'job-2']);
      expect(queue.clean).toHaveBeenCalled();
    });

    it('should return empty array for non-existent queue', async () => {
      const cleaned = await factory.cleanQueue('nonexistent');

      expect(cleaned).toEqual([]);
    });
  });

  describe('shutdown', () => {
    it('should shutdown all resources', async () => {
      factory.createQueue('test-queue');
      factory.createWorker('test-queue', jest.fn());

      await factory.shutdown();

      expect(dlqReprocessingWorker.shutdown).toHaveBeenCalled();
    });
  });
});
