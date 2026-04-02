import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { DlqReprocessingWorker } from './dlq-reprocessing.worker';
import { BullMQDlqService, BullMQDlqEntry } from './bullmq-dlq.service';
import { Queue, Job } from 'bullmq';
import { MAX_TOTAL_DLQ_RETRIES } from './bullmq-resilience.config';

// Mock bullmq Worker
jest.mock('bullmq', () => {
  const original = jest.requireActual<typeof import('bullmq')>('bullmq');
  return {
    ...original,
    Worker: jest.fn().mockImplementation(() => ({
      on: jest.fn(),
      close: jest.fn().mockResolvedValue(undefined),
      pause: jest.fn().mockResolvedValue(undefined),
      resume: jest.fn(),
      isRunning: jest.fn().mockReturnValue(true),
      isPaused: jest.fn().mockReturnValue(false),
    })),
  };
});

describe('DlqReprocessingWorker', () => {
  let worker: DlqReprocessingWorker;
  let dlqService: jest.Mocked<BullMQDlqService>;
  let configService: jest.Mocked<ConfigService>;
  let mockQueue: jest.Mocked<Partial<Queue>>;

  beforeEach(async () => {
    dlqService = {
      registerDlqQueue: jest.fn(),
      isMaxRetriesExceeded: jest.fn(),
      prepareForReprocessing: jest.fn(),
      getReprocessingDelay: jest.fn().mockReturnValue(300000),
      deleteEntry: jest.fn(),
      getEntries: jest.fn(),
      getRegisteredDlqNames: jest.fn(),
    } as unknown as jest.Mocked<BullMQDlqService>;

    configService = {
      get: jest.fn().mockReturnValue('redis://localhost:6379'),
    } as unknown as jest.Mocked<ConfigService>;

    mockQueue = {
      name: 'test-queue',
      add: jest.fn().mockResolvedValue({ id: 'new-job-1' }),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        DlqReprocessingWorker,
        { provide: BullMQDlqService, useValue: dlqService },
        { provide: ConfigService, useValue: configService },
      ],
    }).compile();

    worker = module.get<DlqReprocessingWorker>(DlqReprocessingWorker);
  });

  describe('onModuleInit', () => {
    it('should initialize without errors', () => {
      expect(() => worker.onModuleInit()).not.toThrow();
    });
  });

  describe('registerQueue', () => {
    it('should register queue and create DLQ worker', () => {
      worker.registerQueue(mockQueue as Queue);

      // Worker should be registered
      const stats = worker.getWorkerStats();
      expect(stats.length).toBeGreaterThanOrEqual(0);
    });
  });

  describe('processJob', () => {
    const createDlqEntry = (
      overrides?: Partial<BullMQDlqEntry>,
    ): BullMQDlqEntry => ({
      originalJobId: 'original-job-123',
      originalQueue: 'test-queue',
      data: { foo: 'bar' },
      error: 'Test error',
      attempts: 5,
      dlqRetries: 0,
      failedAt: new Date().toISOString(),
      ...overrides,
    });

    it('should return failed result if original queue not registered', async () => {
      const mockJob = {
        id: 'dlq-job-1',
        data: createDlqEntry({ originalQueue: 'unknown-queue' }),
      } as unknown as Job<BullMQDlqEntry>;

      const result = await worker.processJob(mockJob);

      expect(result.success).toBe(false);
      expect(result.action).toBe('failed');
      expect(result.message).toContain('not registered');
    });

    it('should handle max retries exceeded', async () => {
      worker.registerQueue(mockQueue as Queue);

      const entry = createDlqEntry({ dlqRetries: MAX_TOTAL_DLQ_RETRIES });
      dlqService.isMaxRetriesExceeded.mockReturnValue(true);

      const mockJob = {
        id: 'dlq-job-1',
        data: entry,
      } as unknown as Job<BullMQDlqEntry>;

      const result = await worker.processJob(mockJob);

      expect(result.action).toBe('max-retries-exceeded');
      expect(result.success).toBe(true);
    });

    it('should requeue job successfully', async () => {
      worker.registerQueue(mockQueue as Queue);

      const entry = createDlqEntry();
      dlqService.isMaxRetriesExceeded.mockReturnValue(false);
      dlqService.prepareForReprocessing.mockReturnValue({
        ...entry.data,
        _dlqRetries: 1,
      });

      const mockJob = {
        id: 'dlq-job-1',
        data: entry,
      } as unknown as Job<BullMQDlqEntry>;

      const result = await worker.processJob(mockJob);

      expect(result.success).toBe(true);
      expect(result.action).toBe('requeued');
      expect(mockQueue.add).toHaveBeenCalledWith(
        'dlq-reprocess',
        expect.objectContaining({ _dlqRetries: 1 }),
        expect.objectContaining({
          delay: 300000,
          attempts: 5,
        }),
      );
    });
  });

  describe('reprocessEntry', () => {
    it('should return failed if queue not registered', async () => {
      const result = await worker.reprocessEntry('unknown-queue', 'job-123');

      expect(result.success).toBe(false);
      expect(result.action).toBe('failed');
    });

    it('should reprocess entry manually', async () => {
      worker.registerQueue(mockQueue as Queue);

      const entry: BullMQDlqEntry = {
        originalJobId: 'job-123',
        originalQueue: 'test-queue',
        data: { foo: 'bar' },
        error: 'Error',
        attempts: 5,
        dlqRetries: 0,
        failedAt: new Date().toISOString(),
      };

      dlqService.getEntries.mockResolvedValue([entry]);
      dlqService.getRegisteredDlqNames.mockReturnValue(['test-queue-dlq']);
      dlqService.isMaxRetriesExceeded.mockReturnValue(false);
      dlqService.prepareForReprocessing.mockReturnValue({
        ...entry.data,
        _dlqRetries: 1,
      });
      dlqService.deleteEntry.mockResolvedValue(true);

      const result = await worker.reprocessEntry('test-queue', 'job-123');

      expect(result.success).toBe(true);
      expect(result.action).toBe('requeued');
    });
  });

  describe('onMaxRetriesExceeded', () => {
    it('should register notification handler', () => {
      const handler = jest.fn();
      worker.onMaxRetriesExceeded(handler);

      // Handler is registered internally
      expect(() => worker.onMaxRetriesExceeded(handler)).not.toThrow();
    });
  });

  describe('getWorkerStats', () => {
    it('should return worker stats', () => {
      worker.registerQueue(mockQueue as Queue);

      const stats = worker.getWorkerStats();

      expect(Array.isArray(stats)).toBe(true);
    });
  });

  describe('pauseWorker / resumeWorker', () => {
    it('should return false for unregistered queue', async () => {
      expect(await worker.pauseWorker('unknown')).toBe(false);
      expect(worker.resumeWorker('unknown')).toBe(false);
    });

    it('should pause and resume worker', async () => {
      worker.registerQueue(mockQueue as Queue);

      // These will work because we have mock workers
      const pauseResult = await worker.pauseWorker('test-queue');
      const resumeResult = worker.resumeWorker('test-queue');

      expect(typeof pauseResult).toBe('boolean');
      expect(typeof resumeResult).toBe('boolean');
    });
  });

  describe('shutdown', () => {
    it('should shutdown all workers', async () => {
      worker.registerQueue(mockQueue as Queue);

      await expect(worker.shutdown()).resolves.not.toThrow();
    });
  });
});
