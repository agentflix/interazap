import { CallHandler, ExecutionContext } from '@nestjs/common';
import { of, throwError, lastValueFrom } from 'rxjs';
import { IdempotentResponseInterceptor } from './idempotent-response.interceptor';
import { IdempotencyService } from '../services/idempotency';

interface MockRequest {
  headers: Record<string, string>;
  idempotencyKey?: string;
  idempotencyTtl?: number;
}

describe('IdempotentResponseInterceptor', () => {
  let interceptor: IdempotentResponseInterceptor;
  let idempotencyService: jest.Mocked<IdempotencyService>;
  let mockRequest: MockRequest;
  let mockContext: jest.Mocked<ExecutionContext>;
  let mockCallHandler: jest.Mocked<CallHandler>;

  beforeEach(() => {
    idempotencyService = {
      markProcessed: jest.fn().mockResolvedValue(undefined),
    } as unknown as jest.Mocked<IdempotencyService>;

    mockRequest = {
      headers: {},
    };

    mockContext = {
      switchToHttp: jest.fn().mockReturnValue({
        getRequest: (): MockRequest => mockRequest,
      }),
    } as unknown as jest.Mocked<ExecutionContext>;

    mockCallHandler = {
      handle: jest.fn(),
    };

    interceptor = new IdempotentResponseInterceptor(idempotencyService);
  });

  describe('intercept', () => {
    it('should pass through if no idempotency key', async () => {
      mockCallHandler.handle.mockReturnValue(of({ success: true }));

      const result = await lastValueFrom(
        interceptor.intercept(mockContext, mockCallHandler),
      );

      expect(result).toEqual({ success: true });
      expect(idempotencyService.markProcessed).not.toHaveBeenCalled();
    });

    it('should mark as processed on success', async () => {
      mockRequest.idempotencyKey = 'test:key-123';
      mockRequest.idempotencyTtl = 3600;
      mockCallHandler.handle.mockReturnValue(of({ success: true }));

      const result = await lastValueFrom(
        interceptor.intercept(mockContext, mockCallHandler),
      );

      expect(result).toEqual({ success: true });

      // Wait for async tap to complete
      await new Promise((resolve) => setTimeout(resolve, 20));

      expect(idempotencyService.markProcessed).toHaveBeenCalledWith(
        'test:key-123',
        { success: true },
        { ttlSeconds: 3600 },
      );
    });

    it('should not mark as processed on error', async () => {
      mockRequest.idempotencyKey = 'test:key-123';
      mockCallHandler.handle.mockReturnValue(
        throwError(() => new Error('Handler error')),
      );

      await expect(
        lastValueFrom(interceptor.intercept(mockContext, mockCallHandler)),
      ).rejects.toThrow('Handler error');

      expect(idempotencyService.markProcessed).not.toHaveBeenCalled();
    });

    it('should use undefined TTL if not provided', async () => {
      mockRequest.idempotencyKey = 'test:key-123';
      // No TTL set
      mockCallHandler.handle.mockReturnValue(of({ data: 'result' }));

      await lastValueFrom(interceptor.intercept(mockContext, mockCallHandler));

      // Wait for async tap to complete
      await new Promise((resolve) => setTimeout(resolve, 20));

      expect(idempotencyService.markProcessed).toHaveBeenCalledWith(
        'test:key-123',
        { data: 'result' },
        { ttlSeconds: undefined },
      );
    });

    it('should handle markProcessed errors gracefully', async () => {
      mockRequest.idempotencyKey = 'test:key-123';
      mockCallHandler.handle.mockReturnValue(of({ success: true }));
      idempotencyService.markProcessed.mockRejectedValue(
        new Error('Redis error'),
      );

      // Should not throw, just log
      const result = await lastValueFrom(
        interceptor.intercept(mockContext, mockCallHandler),
      );

      expect(result).toEqual({ success: true });
    });
  });
});
