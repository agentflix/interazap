import { ConnectionStatusService } from './connection-status.service';
import { DatabaseService } from '../../../infrastructure/database/database.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { ConfigService } from '@nestjs/config';
import { StreamPayload } from './chat-webhook.types';
import { WebhookRealtimeEmitter } from './webhook-realtime-emitter.service';

describe('ConnectionStatusService', () => {
  const buildService = () => {
    const redisClient = {
      del: jest.fn().mockResolvedValue(3),
    };

    const configService = {
      get: jest.fn().mockReturnValue('120'),
    } as unknown as jest.Mocked<ConfigService>;

    const databaseService = {
      query: jest.fn().mockResolvedValue({ rowCount: 1 }),
    } as unknown as jest.Mocked<DatabaseService>;

    const redisService = {
      getClient: jest.fn().mockReturnValue(redisClient),
    } as unknown as jest.Mocked<RedisService>;

    const realtimeEmitter = {
      emitChannelConnection: jest.fn(),
    } as unknown as jest.Mocked<WebhookRealtimeEmitter>;

    return {
      service: new ConnectionStatusService(
        configService,
        databaseService,
        redisService,
        realtimeEmitter,
      ),
      redisClient,
      databaseService,
      configService,
    };
  };

  it('invalidates base, active and stale resolver keys after status update', async () => {
    const { service, redisClient, databaseService } = buildService();

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

    expect(databaseService.query).toHaveBeenCalled();
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
});
