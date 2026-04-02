import { Test, TestingModule } from '@nestjs/testing';
import { BullMQDlqService, BullMQDlqEntry } from './bullmq-dlq.service';
import { Job, Queue } from 'bullmq';
import {
  DLQ_ALERT_THRESHOLD,
  MAX_TOTAL_DLQ_RETRIES,
} from './bullmq-resilience.config';

describe('BullMQDlqService', () => {
  let service: BullMQDlqService;
  let mockQueue: jest.Mocked<Partial<Queue>>;

  beforeEach(async () => {
    mockQueue = {
      add: jest.fn().mockResolvedValue({ id: 'dlq-job-1' }),
      getJobs: jest.fn().mockResolvedValue([]),
      getJobCounts: jest.fn().mockResolvedValue({
        waiting: 5,
        delayed: 3,
        failed: 2,
        active: 0,
        completed: 0,
      }),
      getJob: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [BullMQDlqService],
    }).compile();

    service = module.get<BullMQDlqService>(BullMQDlqService);
  });

  describe('registerDlqQueue', () => {
    it('should register a DLQ queue', () => {
      service.registerDlqQueue('test-queue', mockQueue as Queue);

      const names = service.getRegisteredDlqNames();
      expect(names).toContain('test-queue-dlq');
    });
  });

  describe('createDlqEntry', () => {
    it('should create DLQ entry from failed job', () => {
      const mockJob = {
        id: 'job-123',
        queueName: 'test-queue',
        data: { foo: 'bar' },
        attemptsMade: 3,
        opts: { attempts: 5 },
      } as unknown as Job;

      const error = new Error('Test error');
      error.stack = 'Error stack trace';

      const entry = service.createDlqEntry(mockJob, error);

      expect(entry.originalJobId).toBe('job-123');
      expect(entry.originalQueue).toBe('test-queue');
      expect(entry.data).toEqual({ foo: 'bar' });
      expect(entry.error).toBe('Test error');
      expect(entry.stackTrace).toBe('Error stack trace');
      expect(entry.attempts).toBe(3);
      expect(entry.dlqRetries).toBe(0);
    });

    it('should handle jobs with existing DLQ retries', () => {
      const mockJob = {
        id: 'job-123',
        queueName: 'test-queue',
        data: { foo: 'bar', _dlqRetries: 2 },
        attemptsMade: 5,
        opts: {},
      } as unknown as Job;

      const entry = service.createDlqEntry(mockJob, new Error('Test'));

      expect(entry.dlqRetries).toBe(2);
      expect(entry.data._dlqRetries).toBeUndefined();
    });
  });

  describe('captureFailedJob', () => {
    beforeEach(() => {
      service.registerDlqQueue('test-queue', mockQueue as Queue);
    });

    it('should capture failed job to DLQ', async () => {
      const mockJob = {
        id: 'job-123',
        queueName: 'test-queue',
        data: { foo: 'bar' },
        attemptsMade: 5,
        opts: {},
      } as unknown as Job;

      const result = await service.captureFailedJob(
        mockJob,
        new Error('Failed'),
      );

      expect(result).toBe(true);
      expect(mockQueue.add).toHaveBeenCalledWith(
        'dlq-entry',
        expect.objectContaining({
          originalJobId: 'job-123',
          originalQueue: 'test-queue',
        }),
        expect.any(Object),
      );
    });

    it('should return false if DLQ not registered', async () => {
      const mockJob = {
        id: 'job-123',
        queueName: 'unknown-queue',
        data: {},
        attemptsMade: 5,
        opts: {},
      } as unknown as Job;

      const result = await service.captureFailedJob(
        mockJob,
        new Error('Failed'),
      );

      expect(result).toBe(false);
    });
  });

  describe('getSize', () => {
    it('should return total DLQ size', async () => {
      service.registerDlqQueue('test-queue', mockQueue as Queue);

      const size = await service.getSize('test-queue');

      expect(size).toBe(10); // 5 + 3 + 2
    });

    it('should return 0 for unregistered queue', async () => {
      const size = await service.getSize('unknown-queue');
      expect(size).toBe(0);
    });
  });

  describe('getStats', () => {
    it('should return DLQ stats', async () => {
      service.registerDlqQueue('test-queue', mockQueue as Queue);

      const stats = await service.getStats('test-queue');

      expect(stats.queueName).toBe('test-queue');
      expect(stats.dlqSize).toBe(10);
      expect(stats.alertThresholdExceeded).toBe(false);
    });

    it('should flag alert when threshold exceeded', async () => {
      mockQueue.getJobCounts = jest.fn().mockResolvedValue({
        waiting: DLQ_ALERT_THRESHOLD,
        delayed: 10,
        failed: 0,
        active: 0,
        completed: 0,
      });

      service.registerDlqQueue('test-queue', mockQueue as Queue);

      const stats = await service.getStats('test-queue');

      expect(stats.alertThresholdExceeded).toBe(true);
    });
  });

  describe('canReprocess', () => {
    it('should return true when under max retries', () => {
      const entry: BullMQDlqEntry = {
        originalJobId: 'job-1',
        originalQueue: 'test',
        data: {},
        error: 'Error',
        attempts: 5,
        dlqRetries: MAX_TOTAL_DLQ_RETRIES - 1,
        failedAt: new Date().toISOString(),
      };

      expect(service.canReprocess(entry)).toBe(true);
    });

    it('should return false when max retries reached', () => {
      const entry: BullMQDlqEntry = {
        originalJobId: 'job-1',
        originalQueue: 'test',
        data: {},
        error: 'Error',
        attempts: 5,
        dlqRetries: MAX_TOTAL_DLQ_RETRIES,
        failedAt: new Date().toISOString(),
      };

      expect(service.canReprocess(entry)).toBe(false);
    });
  });

  describe('prepareForReprocessing', () => {
    it('should prepare entry with updated retry count', () => {
      const entry: BullMQDlqEntry = {
        originalJobId: 'job-1',
        originalQueue: 'test',
        data: { foo: 'bar' },
        error: 'Test error',
        attempts: 5,
        dlqRetries: 1,
        failedAt: '2024-01-01T00:00:00Z',
      };

      const prepared = service.prepareForReprocessing(entry);

      expect(prepared._dlqRetries).toBe(2);
      expect(prepared._lastDlqError).toBe('Test error');
      expect(prepared._lastDlqFailedAt).toBe('2024-01-01T00:00:00Z');
      expect(prepared.foo).toBe('bar');
    });
  });

  describe('deleteEntry', () => {
    it('should delete entry from DLQ', async () => {
      const mockJob = {
        remove: jest.fn().mockResolvedValue(undefined),
      };
      mockQueue.getJob = jest.fn().mockResolvedValue(mockJob);

      service.registerDlqQueue('test-queue', mockQueue as Queue);

      const result = await service.deleteEntry('test-queue', 'job-123');

      expect(result).toBe(true);
      expect(mockJob.remove).toHaveBeenCalled();
    });

    it('should return false if job not found', async () => {
      mockQueue.getJob = jest.fn().mockResolvedValue(null);

      service.registerDlqQueue('test-queue', mockQueue as Queue);

      const result = await service.deleteEntry('test-queue', 'unknown-job');

      expect(result).toBe(false);
    });
  });

  describe('purge', () => {
    it('should purge all entries from DLQ', async () => {
      const mockJobs = [
        { remove: jest.fn().mockResolvedValue(undefined) },
        { remove: jest.fn().mockResolvedValue(undefined) },
        { remove: jest.fn().mockResolvedValue(undefined) },
      ];
      mockQueue.getJobs = jest.fn().mockResolvedValue(mockJobs);

      service.registerDlqQueue('test-queue', mockQueue as Queue);

      const deleted = await service.purge('test-queue');

      expect(deleted).toBe(3);
      mockJobs.forEach((job) => {
        expect(job.remove).toHaveBeenCalled();
      });
    });
  });
});
