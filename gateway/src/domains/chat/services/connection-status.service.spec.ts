import { ConnectionStatusService } from './connection-status.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { ConfigService } from '@nestjs/config';
import { StreamPayload } from './chat-webhook.types';
import { WebhookRealtimeEmitter } from './webhook-realtime-emitter.service';
import { BullMQQueueFactory } from '../../../shared/services/queue/bullmq-queue-factory.service';

describe('ConnectionStatusService', () => {
  const buildService = () => {
    const redisClient = {
      del: jest.fn().mockResolvedValue(3),
    };

    const configService = {
      get: jest.fn().mockReturnValue('120'),
    } as unknown as jest.Mocked<ConfigService>;

    const redisService = {
      getClient: jest.fn().mockReturnValue(redisClient),
    } as unknown as jest.Mocked<RedisService>;

    const realtimeEmitter = {
      emitChannelConnection: jest.fn(),
    } as unknown as jest.Mocked<WebhookRealtimeEmitter>;

    const mockQueue = { add: jest.fn().mockResolvedValue(undefined) };
    const queueFactory = {
      createQueue: jest.fn().mockReturnValue(mockQueue),
    } as unknown as jest.Mocked<BullMQQueueFactory>;

    return {
      service: new ConnectionStatusService(
        configService,
        redisService,
        realtimeEmitter,
        queueFactory,
      ),
      redisClient,
      mockQueue,
      queueFactory,
    };
  };

  it('enqueues connection status job fire-and-forget', async () => {
    const { service, mockQueue } = buildService();

    const payload = {
      instance_webhook_token: 'token-123',
      tenant_id: 'tenant-1',
    } as StreamPayload;

    await service.updateInstanceConnectionStatus(
      'instance-1',
      'connected',
      payload,
      {},
    );

    expect(mockQueue.add).toHaveBeenCalledWith(
      'update-connection-status',
      expect.objectContaining({
        instanceId: 'instance-1',
        status: 'connected',
      }),
    );
  });

  it('invalidates active and stale resolver keys after status update', async () => {
    const { service, redisClient } = buildService();

    const payload = {
      instance_webhook_token: 'token-123',
      tenant_id: 'tenant-1',
    } as StreamPayload;

    await service.updateInstanceConnectionStatus(
      'instance-1',
      'connected',
      payload,
      {},
    );

    expect(redisClient.del).toHaveBeenCalledWith(
      'chat.instance_by_webhook_token:token-123',
      'chat.instance_by_webhook_token:token-123:active',
      'chat.instance_by_webhook_token:token-123:stale',
    );
  });

  it('skips cache invalidation when payload has no webhook token', async () => {
    const { service, redisClient } = buildService();

    await service.updateInstanceConnectionStatus(
      'instance-1',
      'connected',
      { tenant_id: 'tenant-1' } as StreamPayload,
      {},
    );

    expect(redisClient.del).not.toHaveBeenCalled();
  });

  it('skips update when status normalizes to null', async () => {
    const { service, mockQueue, redisClient } = buildService();

    await service.updateInstanceConnectionStatus(
      'instance-1',
      'unknown',
      { instance_webhook_token: 'tok', tenant_id: 'tenant-1' } as StreamPayload,
      {},
    );

    expect(mockQueue.add).not.toHaveBeenCalled();
    expect(redisClient.del).not.toHaveBeenCalled();
  });
});
