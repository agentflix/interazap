import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { RedisService } from './redis.service';
import Redis from 'ioredis';

jest.mock('ioredis');

describe('RedisService', () => {
  let service: RedisService;
  let mockCommandClient: jest.Mocked<Redis>;
  let mockPubSubClient: jest.Mocked<Redis>;
  let mockBlockingClient: jest.Mocked<Redis>;

  beforeEach(async () => {
    mockCommandClient = {
      on: jest.fn().mockReturnThis(),
      publish: jest.fn(),
      xadd: jest.fn(),
      set: jest.fn(),
      get: jest.fn(),
      del: jest.fn(),
      quit: jest.fn(),
    } as unknown as jest.Mocked<Redis>;

    mockPubSubClient = {
      on: jest.fn().mockReturnThis(),
      subscribe: jest.fn(),
      unsubscribe: jest.fn(),
      quit: jest.fn(),
    } as unknown as jest.Mocked<Redis>;

    mockBlockingClient = {
      on: jest.fn().mockReturnThis(),
      blpop: jest.fn(),
      quit: jest.fn(),
    } as unknown as jest.Mocked<Redis>;

    let callCount = 0;
    (Redis as jest.MockedClass<typeof Redis>).mockImplementation(() => {
      callCount++;
      // First call → command client, second → pubsub client, third → blocking client
      if (callCount === 1) return mockCommandClient;
      if (callCount === 2) return mockPubSubClient;
      return mockBlockingClient;
    });

    const mockConfigService = {
      get: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        RedisService,
        {
          provide: ConfigService,
          useValue: mockConfigService,
        },
      ],
    }).compile();

    service = module.get<RedisService>(RedisService);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('constructor', () => {
    it('should create three Redis clients (command, pubsub, blocking)', () => {
      expect(Redis).toHaveBeenCalledTimes(3);
      expect(Redis).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          maxRetriesPerRequest: null,
          enableReadyCheck: true,
          connectionName: 'gateway-commands',
        }),
      );
      expect(Redis).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          maxRetriesPerRequest: null,
          enableReadyCheck: true,
          connectionName: 'gateway-pubsub',
        }),
      );
      expect(Redis).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          maxRetriesPerRequest: null,
          enableReadyCheck: true,
          connectionName: 'gateway-blocking',
        }),
      );
    });

    it('should register error and connect event handlers on all three clients', () => {
      expect(mockCommandClient.on).toHaveBeenCalledWith(
        'error',
        expect.any(Function),
      );
      expect(mockCommandClient.on).toHaveBeenCalledWith(
        'connect',
        expect.any(Function),
      );
      expect(mockPubSubClient.on).toHaveBeenCalledWith(
        'error',
        expect.any(Function),
      );
      expect(mockPubSubClient.on).toHaveBeenCalledWith(
        'connect',
        expect.any(Function),
      );
      expect(mockBlockingClient.on).toHaveBeenCalledWith(
        'error',
        expect.any(Function),
      );
      expect(mockBlockingClient.on).toHaveBeenCalledWith(
        'connect',
        expect.any(Function),
      );
    });
  });

  describe('onModuleDestroy', () => {
    it('should quit all three Redis clients on module destroy', async () => {
      await service.onModuleDestroy();
      expect(mockCommandClient.quit).toHaveBeenCalled();
      expect(mockPubSubClient.quit).toHaveBeenCalled();
      expect(mockBlockingClient.quit).toHaveBeenCalled();
    });
  });

  describe('getClient', () => {
    it('should return command Redis client', () => {
      const client = service.getClient();
      expect(client).toBe(mockCommandClient);
    });
  });

  describe('getPubSubClient', () => {
    it('should return pubsub Redis client', () => {
      const client = service.getPubSubClient();
      expect(client).toBe(mockPubSubClient);
    });
  });

  describe('getBlockingClient', () => {
    it('should return dedicated blocking Redis client', () => {
      const client = service.getBlockingClient();
      expect(client).toBe(mockBlockingClient);
    });

    it('should return a different client than getClient()', () => {
      expect(service.getBlockingClient()).not.toBe(service.getClient());
    });
  });

  describe('publish', () => {
    it('should publish message to channel using command client', async () => {
      mockCommandClient.publish.mockResolvedValue(1);
      const payload = { event: 'test', data: { id: 123 } };

      const result = await service.publish('test-channel', payload);

      expect(result).toBe(1);
      expect(mockCommandClient.publish).toHaveBeenCalledWith(
        'test-channel',
        JSON.stringify(payload),
      );
    });

    it('should handle string payloads', async () => {
      mockCommandClient.publish.mockResolvedValue(1);

      await service.publish('channel', 'simple string');

      expect(mockCommandClient.publish).toHaveBeenCalledWith(
        'channel',
        JSON.stringify('simple string'),
      );
    });
  });

  describe('publishStream', () => {
    it('should publish to Redis stream with key-value pairs', async () => {
      mockCommandClient.xadd.mockResolvedValue('1234567890-0');

      const payload = {
        event: 'test',
        tenant_id: 'tenant-123',
        count: 42,
      };

      const result = await service.publishStream('test-stream', payload);

      expect(result).toBe('1234567890-0');
      expect(mockCommandClient.xadd).toHaveBeenCalledWith(
        'test-stream',
        'MAXLEN',
        '~',
        '10000',
        '*',
        'event',
        'test',
        'tenant_id',
        'tenant-123',
        'count',
        '42', // numbers are stringified
      );
    });

    it('should handle string values in stream', async () => {
      mockCommandClient.xadd.mockResolvedValue('1234567890-0');

      await service.publishStream('stream', { key: 'string value' });

      expect(mockCommandClient.xadd).toHaveBeenCalledWith(
        'stream',
        'MAXLEN',
        '~',
        '10000',
        '*',
        'key',
        'string value',
      );
    });

    it('should handle undefined values as empty strings', async () => {
      mockCommandClient.xadd.mockResolvedValue('1234567890-0');

      await service.publishStream('stream', { key: undefined });

      expect(mockCommandClient.xadd).toHaveBeenCalledWith(
        'stream',
        'MAXLEN',
        '~',
        '10000',
        '*',
        'key',
        '',
      );
    });

    it('should serialize object values', async () => {
      mockCommandClient.xadd.mockResolvedValue('1234567890-0');

      await service.publishStream('stream', {
        nested: { deep: { value: 'test' } },
      });

      expect(mockCommandClient.xadd).toHaveBeenCalledWith(
        'stream',
        'MAXLEN',
        '~',
        '10000',
        '*',
        'nested',
        JSON.stringify({ deep: { value: 'test' } }),
      );
    });
  });

  describe('ensureIdempotent', () => {
    it('should return true when key is set (first time)', async () => {
      mockCommandClient.set.mockResolvedValue('OK');

      const result = await service.ensureIdempotent('idempotency-key-123');

      expect(result).toBe(true);
      expect(mockCommandClient.set).toHaveBeenCalledWith(
        'idempotency-key-123',
        '1',
        'EX',
        300,
        'NX',
      );
    });

    it('should return false when key already exists (duplicate)', async () => {
      mockCommandClient.set.mockResolvedValue(null);

      const result = await service.ensureIdempotent('idempotency-key-123');

      expect(result).toBe(false);
    });

    it('should use custom TTL', async () => {
      mockCommandClient.set.mockResolvedValue('OK');

      await service.ensureIdempotent('key', 600);

      expect(mockCommandClient.set).toHaveBeenCalledWith(
        'key',
        '1',
        'EX',
        600,
        'NX',
      );
    });
  });

  describe('get', () => {
    it('should get value from Redis', async () => {
      mockCommandClient.get.mockResolvedValue('stored-value');

      const result = await service.get('my-key');

      expect(result).toBe('stored-value');
      expect(mockCommandClient.get).toHaveBeenCalledWith('my-key');
    });

    it('should return null for non-existent keys', async () => {
      mockCommandClient.get.mockResolvedValue(null);

      const result = await service.get('non-existent');

      expect(result).toBeNull();
    });
  });

  describe('set', () => {
    it('should set value without TTL', async () => {
      mockCommandClient.set.mockResolvedValue('OK');

      await service.set('key', 'value');

      expect(mockCommandClient.set).toHaveBeenCalledWith('key', 'value');
    });

    it('should set value with TTL', async () => {
      mockCommandClient.set.mockResolvedValue('OK');

      await service.set('key', 'value', 3600);

      expect(mockCommandClient.set).toHaveBeenCalledWith(
        'key',
        'value',
        'EX',
        3600,
      );
    });

    it('should ignore zero or negative TTL', async () => {
      mockCommandClient.set.mockResolvedValue('OK');

      await service.set('key', 'value', 0);

      expect(mockCommandClient.set).toHaveBeenCalledWith('key', 'value');
    });

    it('should handle undefined TTL', async () => {
      mockCommandClient.set.mockResolvedValue('OK');

      await service.set('key', 'value', undefined);

      expect(mockCommandClient.set).toHaveBeenCalledWith('key', 'value');
    });
  });

  describe('delete', () => {
    it('should delete key from Redis', async () => {
      mockCommandClient.del.mockResolvedValue(1);

      const result = await service.delete('key-to-delete');

      expect(result).toBe(1);
      expect(mockCommandClient.del).toHaveBeenCalledWith('key-to-delete');
    });

    it('should return 0 when key does not exist', async () => {
      mockCommandClient.del.mockResolvedValue(0);

      const result = await service.delete('non-existent');

      expect(result).toBe(0);
    });
  });
});
