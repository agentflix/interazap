import { ExecutionContext } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import {
  IdempotentWebhookGuard,
  IDEMPOTENT_KEY,
  IDEMPOTENT_PREFIX_KEY,
  IDEMPOTENT_TTL_KEY,
} from './idempotent-webhook.guard';
import { IdempotencyService } from '../services/idempotency';

interface MockRequest {
  headers: Record<string, string>;
  body: Record<string, unknown>;
  idempotencyKey?: string;
  idempotencyTtl?: number;
}

interface MockResponse {
  setHeader: jest.Mock;
  status: jest.Mock;
  json: jest.Mock;
}

describe('IdempotentWebhookGuard', () => {
  let guard: IdempotentWebhookGuard;
  let reflector: jest.Mocked<Reflector>;
  let idempotencyService: jest.Mocked<IdempotencyService>;
  let mockRequest: MockRequest;
  let mockResponse: MockResponse;
  let mockContext: jest.Mocked<ExecutionContext>;

  beforeEach(() => {
    reflector = {
      get: jest.fn(),
    } as unknown as jest.Mocked<Reflector>;

    idempotencyService = {
      check: jest.fn(),
      markProcessed: jest.fn(),
    } as unknown as jest.Mocked<IdempotencyService>;

    mockRequest = {
      headers: {},
      body: {},
    };

    mockResponse = {
      setHeader: jest.fn(),
      status: jest.fn().mockReturnThis(),
      json: jest.fn().mockReturnThis(),
    };

    mockContext = {
      getHandler: jest.fn().mockReturnValue({}),
      switchToHttp: jest.fn().mockReturnValue({
        getRequest: (): MockRequest => mockRequest,
        getResponse: (): MockResponse => mockResponse,
      }),
    } as unknown as jest.Mocked<ExecutionContext>;

    guard = new IdempotentWebhookGuard(reflector, idempotencyService);
  });

  describe('canActivate', () => {
    it('should return true if route is not marked as idempotent', async () => {
      reflector.get.mockReturnValue(undefined);

      const result = await guard.canActivate(mockContext);

      expect(result).toBe(true);
      expect(idempotencyService.check).not.toHaveBeenCalled();
    });

    it('should return true if no idempotency key found', async () => {
      reflector.get.mockImplementation((key) => {
        if (key === IDEMPOTENT_KEY) return true;
        return undefined;
      });

      const result = await guard.canActivate(mockContext);

      expect(result).toBe(true);
    });

    it('should return true for new webhook', async () => {
      mockRequest.headers['x-webhook-id'] = 'webhook-123';

      reflector.get.mockImplementation((key) => {
        if (key === IDEMPOTENT_KEY) return true;
        if (key === IDEMPOTENT_PREFIX_KEY) return 'test';
        if (key === IDEMPOTENT_TTL_KEY) return 3600;
        return undefined;
      });

      idempotencyService.check.mockResolvedValue({
        isDuplicate: false,
        key: 'test:webhook-123',
      });

      const result = await guard.canActivate(mockContext);

      expect(result).toBe(true);
      expect(mockResponse.setHeader).toHaveBeenCalledWith(
        'X-Idempotency-Processed',
        'true',
      );
    });

    it('should return false for duplicate webhook', async () => {
      mockRequest.headers['x-webhook-id'] = 'webhook-123';

      reflector.get.mockImplementation((key) => {
        if (key === IDEMPOTENT_KEY) return true;
        if (key === IDEMPOTENT_PREFIX_KEY) return 'test';
        return undefined;
      });

      idempotencyService.check.mockResolvedValue({
        isDuplicate: true,
        cachedResult: { status: 'processed' },
        key: 'test:webhook-123',
      });

      const result = await guard.canActivate(mockContext);

      expect(result).toBe(false);
      expect(mockResponse.setHeader).toHaveBeenCalledWith(
        'X-Idempotency-Cached',
        'true',
      );
      expect(mockResponse.json).toHaveBeenCalledWith({ status: 'processed' });
    });

    it('should extract key from x-idempotency-key header', async () => {
      mockRequest.headers['x-idempotency-key'] = 'custom-key';

      reflector.get.mockImplementation((key) => {
        if (key === IDEMPOTENT_KEY) return true;
        return undefined;
      });

      idempotencyService.check.mockResolvedValue({
        isDuplicate: false,
        key: 'webhook:custom-key',
      });

      await guard.canActivate(mockContext);

      expect(idempotencyService.check).toHaveBeenCalledWith(
        'webhook:custom-key',
        { prefix: 'webhook' },
      );
    });

    it('should extract key from body.id', async () => {
      mockRequest.body = { id: 'body-event-id' };

      reflector.get.mockImplementation((key) => {
        if (key === IDEMPOTENT_KEY) return true;
        return undefined;
      });

      idempotencyService.check.mockResolvedValue({
        isDuplicate: false,
        key: 'webhook:body-event-id',
      });

      await guard.canActivate(mockContext);

      expect(idempotencyService.check).toHaveBeenCalledWith(
        'webhook:body-event-id',
        { prefix: 'webhook' },
      );
    });

    it('should extract key from body.event.id', async () => {
      mockRequest.body = { event: { id: 'nested-event-id' } };

      reflector.get.mockImplementation((key) => {
        if (key === IDEMPOTENT_KEY) return true;
        return undefined;
      });

      idempotencyService.check.mockResolvedValue({
        isDuplicate: false,
        key: 'webhook:nested-event-id',
      });

      await guard.canActivate(mockContext);

      expect(idempotencyService.check).toHaveBeenCalledWith(
        'webhook:nested-event-id',
        { prefix: 'webhook' },
      );
    });

    it('should store idempotency key in request for later use', async () => {
      mockRequest.headers['x-webhook-id'] = 'webhook-123';

      reflector.get.mockImplementation((key) => {
        if (key === IDEMPOTENT_KEY) return true;
        if (key === IDEMPOTENT_TTL_KEY) return 7200;
        return undefined;
      });

      idempotencyService.check.mockResolvedValue({
        isDuplicate: false,
        key: 'webhook:webhook-123',
      });

      await guard.canActivate(mockContext);

      expect(mockRequest.idempotencyKey).toBe('webhook:webhook-123');
      expect(mockRequest.idempotencyTtl).toBe(7200);
    });
  });

  describe('header priority', () => {
    it('should prefer x-idempotency-key over other headers', async () => {
      mockRequest.headers['x-idempotency-key'] = 'primary-key';
      mockRequest.headers['x-webhook-id'] = 'secondary-key';

      reflector.get.mockImplementation((key) => {
        if (key === IDEMPOTENT_KEY) return true;
        return undefined;
      });

      idempotencyService.check.mockResolvedValue({
        isDuplicate: false,
        key: 'webhook:primary-key',
      });

      await guard.canActivate(mockContext);

      expect(idempotencyService.check).toHaveBeenCalledWith(
        'webhook:primary-key',
        { prefix: 'webhook' },
      );
    });

    it('should try multiple webhook headers', async () => {
      mockRequest.headers['x-event-id'] = 'event-123';

      reflector.get.mockImplementation((key) => {
        if (key === IDEMPOTENT_KEY) return true;
        return undefined;
      });

      idempotencyService.check.mockResolvedValue({
        isDuplicate: false,
        key: 'webhook:event-123',
      });

      await guard.canActivate(mockContext);

      expect(idempotencyService.check).toHaveBeenCalledWith(
        'webhook:event-123',
        { prefix: 'webhook' },
      );
    });
  });
});
