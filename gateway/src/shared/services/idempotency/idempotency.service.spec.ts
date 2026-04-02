import { IdempotencyService } from './idempotency.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';

describe('IdempotencyService', () => {
  let service: IdempotencyService;
  let mockRedis: {
    get: jest.Mock;
    set: jest.Mock;
    delete: jest.Mock;
  };

  beforeEach(() => {
    mockRedis = {
      get: jest.fn(),
      set: jest.fn(),
      delete: jest.fn(),
    };
    service = new IdempotencyService(mockRedis as unknown as RedisService);
  });

  describe('check', () => {
    it('should return isDuplicate false when key not found', async () => {
      mockRedis.get.mockResolvedValue(null);

      const result = await service.check('test-key');

      expect(result.isDuplicate).toBe(false);
      expect(result.cachedResult).toBeUndefined();
    });

    it('should return isDuplicate true when key found', async () => {
      const cachedData = { status: 'processed' };
      mockRedis.get.mockResolvedValue(JSON.stringify(cachedData));

      const result = await service.check<typeof cachedData>('test-key');

      expect(result.isDuplicate).toBe(true);
      expect(result.cachedResult).toEqual(cachedData);
    });

    it('should use custom prefix', async () => {
      mockRedis.get.mockResolvedValue(null);

      await service.check('test-key', { prefix: 'custom' });

      expect(mockRedis.get).toHaveBeenCalledWith('custom:test-key');
    });

    it('should return isDuplicate false on redis error', async () => {
      mockRedis.get.mockRejectedValue(new Error('Redis error'));

      const result = await service.check('test-key');

      expect(result.isDuplicate).toBe(false);
    });
  });

  describe('markProcessed', () => {
    it('should store result with default TTL', async () => {
      const result = { status: 'ok' };

      await service.markProcessed('test-key', result);

      expect(mockRedis.set).toHaveBeenCalledWith(
        'idempotent:test-key',
        JSON.stringify(result),
        86400,
      );
    });

    it('should use custom TTL', async () => {
      const result = { status: 'ok' };

      await service.markProcessed('test-key', result, { ttlSeconds: 3600 });

      expect(mockRedis.set).toHaveBeenCalledWith(
        'idempotent:test-key',
        JSON.stringify(result),
        3600,
      );
    });

    it('should not throw on redis error', async () => {
      mockRedis.set.mockRejectedValue(new Error('Redis error'));

      await expect(
        service.markProcessed('test-key', { status: 'ok' }),
      ).resolves.not.toThrow();
    });
  });

  describe('execute', () => {
    it('should execute function and cache result', async () => {
      mockRedis.get.mockResolvedValue(null);

      const fn = jest.fn().mockResolvedValue({ data: 'result' });
      const result = await service.execute('test-key', fn);

      expect(fn).toHaveBeenCalled();
      expect(result).toEqual({ data: 'result' });
      expect(mockRedis.set).toHaveBeenCalled();
    });

    it('should return cached result without executing function', async () => {
      const cachedData = { data: 'cached' };
      mockRedis.get.mockResolvedValue(JSON.stringify(cachedData));

      const fn = jest.fn().mockResolvedValue({ data: 'new' });
      const result = await service.execute('test-key', fn);

      expect(fn).not.toHaveBeenCalled();
      expect(result).toEqual(cachedData);
    });
  });

  describe('clear', () => {
    it('should delete the key', async () => {
      await service.clear('test-key');

      expect(mockRedis.delete).toHaveBeenCalledWith('idempotent:test-key');
    });

    it('should use custom prefix', async () => {
      await service.clear('test-key', 'custom');

      expect(mockRedis.delete).toHaveBeenCalledWith('custom:test-key');
    });
  });

  describe('static key generators', () => {
    it('should generate webhook key', () => {
      const key = IdempotencyService.webhookKey('uazapi', 'event-123');
      expect(key).toBe('webhook:uazapi:event-123');
    });

    it('should generate message key', () => {
      const key = IdempotencyService.messageKey('ticket-1', 'hash-abc');
      expect(key).toBe('message:ticket-1:hash-abc');
    });

    it('should generate payment key', () => {
      const key = IdempotencyService.paymentKey('tenant-1', 'pay-123');
      expect(key).toBe('payment:tenant-1:pay-123');
    });
  });
});
