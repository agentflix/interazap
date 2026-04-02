import { Test, TestingModule } from '@nestjs/testing';
import { QueueDashboardController } from './queue-dashboard.controller';
import { RedisService } from '../infrastructure/redis/redis.service';
import {
  StreamDlqService,
  BullMQDlqService,
  BullMQQueueFactory,
  QueueRateLimiterService,
  DlqReprocessingWorker,
  STREAM_DLQ_ALERT_THRESHOLD,
} from '../shared/services/queue';
import { InternalApiKeyGuard } from '../domains/realtime/guards/internal-api-key.guard';

describe('QueueDashboardController', () => {
  let controller: QueueDashboardController;
  let redisService: jest.Mocked<RedisService>;
  let streamDlqService: jest.Mocked<StreamDlqService>;
  let bullmqDlqService: jest.Mocked<BullMQDlqService>;
  let queueFactory: jest.Mocked<BullMQQueueFactory>;
  let rateLimiter: jest.Mocked<QueueRateLimiterService>;
  let dlqReprocessingWorker: jest.Mocked<DlqReprocessingWorker>;
  let mockRedisClient: { xlen: jest.Mock };

  beforeEach(async () => {
    mockRedisClient = {
      xlen: jest.fn().mockResolvedValue(10),
    };

    redisService = {
      getClient: jest.fn().mockReturnValue(mockRedisClient),
    } as unknown as jest.Mocked<RedisService>;

    streamDlqService = {
      getSize: jest.fn().mockResolvedValue(5),
      getPending: jest.fn().mockResolvedValue([]),
      retry: jest.fn().mockResolvedValue(true),
      getAllStats: jest.fn().mockResolvedValue({
        'chat.outbound_message': 2,
        'ai.chat': 1,
      }),
    } as unknown as jest.Mocked<StreamDlqService>;

    bullmqDlqService = {
      getStats: jest.fn().mockResolvedValue({
        queueName: 'test-queue',
        dlqSize: 3,
        alertThresholdExceeded: false,
      }),
      getAllStats: jest.fn().mockResolvedValue([
        {
          queueName: 'internal-notifications',
          dlqSize: 2,
          alertThresholdExceeded: false,
        },
      ]),
      getEntries: jest.fn().mockResolvedValue([]),
      purge: jest.fn().mockResolvedValue(5),
    } as unknown as jest.Mocked<BullMQDlqService>;

    queueFactory = {
      getAllQueueStats: jest.fn().mockResolvedValue([
        {
          name: 'internal-notifications',
          waiting: 5,
          active: 1,
          completed: 100,
          failed: 2,
          delayed: 0,
          paused: false,
        },
      ]),
      getQueueStats: jest.fn().mockResolvedValue({
        name: 'internal-notifications',
        waiting: 5,
        active: 1,
        completed: 100,
        failed: 2,
        delayed: 0,
        paused: false,
      }),
      pauseQueue: jest.fn().mockResolvedValue(true),
      resumeQueue: jest.fn().mockResolvedValue(true),
      cleanQueue: jest.fn().mockResolvedValue(['job-1', 'job-2']),
    } as unknown as jest.Mocked<BullMQQueueFactory>;

    rateLimiter = {
      getStats: jest.fn().mockResolvedValue({
        queueName: 'internal-notifications',
        current: 5,
        limit: 100,
        windowSeconds: 60,
        utilizationPercent: 5,
      }),
      getAllStats: jest.fn().mockResolvedValue([]),
    } as unknown as jest.Mocked<QueueRateLimiterService>;

    dlqReprocessingWorker = {
      reprocessEntry: jest.fn().mockResolvedValue({
        success: true,
        action: 'requeued',
        message: 'Requeued successfully',
      }),
      getWorkerStats: jest.fn().mockReturnValue([
        {
          queueName: 'internal-notifications',
          isRunning: true,
          isPaused: false,
        },
      ]),
      pauseWorker: jest.fn().mockResolvedValue(true),
      resumeWorker: jest.fn().mockReturnValue(true),
    } as unknown as jest.Mocked<DlqReprocessingWorker>;

    const module: TestingModule = await Test.createTestingModule({
      controllers: [QueueDashboardController],
      providers: [
        { provide: RedisService, useValue: redisService },
        { provide: StreamDlqService, useValue: streamDlqService },
        { provide: BullMQDlqService, useValue: bullmqDlqService },
        { provide: BullMQQueueFactory, useValue: queueFactory },
        { provide: QueueRateLimiterService, useValue: rateLimiter },
        { provide: DlqReprocessingWorker, useValue: dlqReprocessingWorker },
      ],
    })
      .overrideGuard(InternalApiKeyGuard)
      .useValue({ canActivate: jest.fn().mockReturnValue(true) })
      .compile();

    controller = module.get<QueueDashboardController>(QueueDashboardController);
  });

  describe('getOverview', () => {
    it('should return overview of all queues', async () => {
      const result = await controller.getOverview();

      expect(result.summary).toBeDefined();
      expect(result.summary.redisStreams).toBeDefined();
      expect(result.summary.bullmq).toBeDefined();
      expect(result.redisStreams).toBeDefined();
      expect(result.bullmq).toBeDefined();
      expect(result.timestamp).toBeDefined();
    });

    it('should include correct totals', async () => {
      const result = await controller.getOverview();

      expect(result.summary.redisStreams.count).toBeGreaterThan(0);
      expect(result.summary.bullmq.count).toBe(1);
    });
  });

  describe('getStreamQueue', () => {
    it('should return Redis Streams queue details', async () => {
      const result = await controller.getStreamQueue('chat.outbound_message');

      expect(result.name).toBe('chat.outbound_message');
      expect(result.type).toBe('redis-streams');
      expect(result.size).toBe(10);
      expect(result.dlqSize).toBe(5);
    });
  });

  describe('getBullMQQueue', () => {
    it('should return BullMQ queue details', async () => {
      const result = await controller.getBullMQQueue('internal-notifications');

      expect(result.name).toBe('internal-notifications');
      expect(result.type).toBe('bullmq');
      expect(result.stats).toBeDefined();
      expect(result.dlq).toBeDefined();
    });

    it('should include rate limiter stats when configured', async () => {
      const result = await controller.getBullMQQueue('internal-notifications');

      expect(result.rateLimiter).toBeDefined();
      expect(rateLimiter.getStats).toHaveBeenCalled();
    });
  });

  describe('getStreamDlqEntries', () => {
    it('should return DLQ entries for Redis Streams', async () => {
      const result = await controller.getStreamDlqEntries(
        'chat.outbound_message',
      );

      expect(result.stream).toBe('chat.outbound_message');
      expect(result.type).toBe('redis-streams');
      expect(result.entries).toEqual([]);
    });

    it('should respect limit parameter', async () => {
      await controller.getStreamDlqEntries('chat.outbound_message', '50');

      expect(streamDlqService.getPending).toHaveBeenCalledWith(
        'chat.outbound_message',
        '.dlq',
        50,
      );
    });
  });

  describe('getBullMQDlqEntries', () => {
    it('should return DLQ entries for BullMQ', async () => {
      const result = await controller.getBullMQDlqEntries(
        'internal-notifications',
      );

      expect(result.queue).toBe('internal-notifications');
      expect(result.type).toBe('bullmq');
      expect(result.entries).toEqual([]);
    });
  });

  describe('retryStreamDlqEntry', () => {
    it('should retry Redis Streams DLQ entry', async () => {
      const result = await controller.retryStreamDlqEntry(
        'chat.outbound_message',
        'message-123',
      );

      expect(result.success).toBe(true);
      expect(result.type).toBe('redis-streams');
      expect(streamDlqService.retry).toHaveBeenCalledWith(
        'chat.outbound_message',
        'message-123',
      );
    });
  });

  describe('retryBullMQDlqEntry', () => {
    it('should retry BullMQ DLQ entry', async () => {
      const result = await controller.retryBullMQDlqEntry(
        'internal-notifications',
        'job-123',
      );

      expect(result.success).toBe(true);
      expect(result.type).toBe('bullmq');
      expect(result.action).toBe('requeued');
    });
  });

  describe('purgeBullMQDlq', () => {
    it('should purge BullMQ DLQ', async () => {
      const result = await controller.purgeBullMQDlq('internal-notifications');

      expect(result.success).toBe(true);
      expect(result.deleted).toBe(5);
      expect(bullmqDlqService.purge).toHaveBeenCalledWith(
        'internal-notifications',
      );
    });
  });

  describe('pauseBullMQQueue', () => {
    it('should pause BullMQ queue', async () => {
      const result = await controller.pauseBullMQQueue(
        'internal-notifications',
      );

      expect(result.success).toBe(true);
      expect(queueFactory.pauseQueue).toHaveBeenCalledWith(
        'internal-notifications',
      );
    });
  });

  describe('resumeBullMQQueue', () => {
    it('should resume BullMQ queue', async () => {
      const result = await controller.resumeBullMQQueue(
        'internal-notifications',
      );

      expect(result.success).toBe(true);
      expect(queueFactory.resumeQueue).toHaveBeenCalledWith(
        'internal-notifications',
      );
    });
  });

  describe('cleanBullMQQueue', () => {
    it('should clean BullMQ queue', async () => {
      const result = await controller.cleanBullMQQueue(
        'internal-notifications',
        {},
      );

      expect(result.success).toBe(true);
      expect(result.cleaned).toBe(2);
    });

    it('should use custom grace and status', async () => {
      await controller.cleanBullMQQueue('internal-notifications', {
        grace: 7200000,
        status: 'failed',
      });

      expect(queueFactory.cleanQueue).toHaveBeenCalledWith(
        'internal-notifications',
        7200000,
        'failed',
      );
    });
  });

  describe('getDlqStats', () => {
    it('should return DLQ stats for all queues', async () => {
      const result = await controller.getDlqStats();

      expect(result.redisStreams).toBeDefined();
      expect(result.bullmq).toBeDefined();
      expect(result.total).toBeGreaterThanOrEqual(0);
      expect(result.timestamp).toBeDefined();
      expect(result.streamAlerts).toBeDefined();
    });

    it('should include alerts', async () => {
      bullmqDlqService.getAllStats.mockResolvedValue([
        { queueName: 'test', dlqSize: 150, alertThresholdExceeded: true },
      ]);

      streamDlqService.getAllStats.mockResolvedValue({
        'chat.outbound_message': STREAM_DLQ_ALERT_THRESHOLD + 1,
      });

      const result = await controller.getDlqStats();

      expect(result.alerts.length).toBe(1);
      expect(result.streamAlerts.length).toBe(1);
    });
  });

  describe('getRateLimiterStats', () => {
    it('should return rate limiter stats', async () => {
      const result = await controller.getRateLimiterStats();

      expect(result.queues).toBeDefined();
      expect(result.timestamp).toBeDefined();
    });
  });

  describe('getDlqWorkerStats', () => {
    it('should return DLQ worker stats', () => {
      const result = controller.getDlqWorkerStats();

      expect(result.workers).toBeDefined();
      expect(result.workers.length).toBe(1);
      expect(result.timestamp).toBeDefined();
    });
  });

  describe('pauseDlqWorker', () => {
    it('should pause DLQ worker', async () => {
      const result = await controller.pauseDlqWorker('internal-notifications');

      expect(result.success).toBe(true);
      expect(dlqReprocessingWorker.pauseWorker).toHaveBeenCalledWith(
        'internal-notifications',
      );
    });
  });

  describe('resumeDlqWorker', () => {
    it('should resume DLQ worker', () => {
      const result = controller.resumeDlqWorker('internal-notifications');

      expect(result.success).toBe(true);
      expect(dlqReprocessingWorker.resumeWorker).toHaveBeenCalledWith(
        'internal-notifications',
      );
    });
  });
});
