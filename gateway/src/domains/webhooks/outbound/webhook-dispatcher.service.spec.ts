import { WebhookDispatcherService } from './webhook-dispatcher.service';
import { ConfigService } from '@nestjs/config';
import axios from 'axios';

jest.mock('axios');
const mockedAxios = axios as unknown as jest.Mock;

describe('WebhookDispatcherService', () => {
  let service: WebhookDispatcherService;
  let mockConfigService: Partial<ConfigService>;

  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers({ advanceTimers: true });

    mockConfigService = {
      get: jest.fn(),
    };

    service = new WebhookDispatcherService(mockConfigService as ConfigService);
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  describe('dispatch', () => {
    it('should successfully dispatch webhook on first attempt', async () => {
      mockedAxios.mockResolvedValue({
        status: 200,
        data: { received: true },
      });

      const request = {
        id: 'webhook-001',
        tenantId: 'tenant-123',
        url: 'https://example.com/webhook',
        method: 'POST' as const,
        body: { event: 'test' },
      };

      const result = await service.dispatch(request);

      expect(result.success).toBe(true);
      expect(result.webhookId).toBe('webhook-001');
      expect(result.statusCode).toBe(200);
      expect(result.retryCount).toBe(0);
      expect(mockedAxios).toHaveBeenCalledTimes(1);
    });

    it('should retry on 5xx errors', async () => {
      mockedAxios
        .mockResolvedValueOnce({ status: 503, data: 'Service Unavailable' })
        .mockResolvedValueOnce({ status: 200, data: { ok: true } });

      const request = {
        id: 'webhook-retry',
        tenantId: 'tenant-123',
        url: 'https://example.com/webhook',
        method: 'POST' as const,
        body: { event: 'retry-test' },
      };

      const dispatchPromise = service.dispatch(request);

      // Advance through retry delay
      await jest.advanceTimersByTimeAsync(1000);

      const result = await dispatchPromise;

      expect(result.success).toBe(true);
      expect(result.retryCount).toBe(1);
      expect(mockedAxios).toHaveBeenCalledTimes(2);
    });

    it('should fail after max retries', async () => {
      mockedAxios.mockResolvedValue({
        status: 500,
        data: 'Internal Server Error',
      });

      const request = {
        id: 'webhook-fail',
        tenantId: 'tenant-123',
        url: 'https://example.com/webhook',
        method: 'POST' as const,
        body: { event: 'fail-test' },
        maxRetries: 2,
      };

      const dispatchPromise = service.dispatch(request);

      // Advance through retry delays
      await jest.advanceTimersByTimeAsync(1000);
      await jest.advanceTimersByTimeAsync(4000);

      const result = await dispatchPromise;

      expect(result.success).toBe(false);
      expect(result.error).toBe('HTTP 500');
      expect(result.retryCount).toBe(2);
      expect(mockedAxios).toHaveBeenCalledTimes(3);
    });

    it('should include custom headers', async () => {
      mockedAxios.mockResolvedValue({ status: 200, data: {} });

      const request = {
        id: 'webhook-headers',
        tenantId: 'tenant-123',
        url: 'https://example.com/webhook',
        method: 'POST' as const,
        headers: {
          'X-Custom-Header': 'custom-value',
          Authorization: 'Bearer token123',
        },
        body: { event: 'headers-test' },
      };

      await service.dispatch(request);

      expect(mockedAxios).toHaveBeenCalledWith(
        expect.objectContaining({
          headers: expect.objectContaining({
            'X-Custom-Header': 'custom-value',
            Authorization: 'Bearer token123',
            'Content-Type': 'application/json',
          }),
        }),
      );
    });

    it('should add HMAC signature when secret provided', async () => {
      mockedAxios.mockResolvedValue({ status: 200, data: {} });

      const request = {
        id: 'webhook-signed',
        tenantId: 'tenant-123',
        url: 'https://example.com/webhook',
        method: 'POST' as const,
        body: { event: 'signed-test' },
        signatureSecret: 'my-secret-key',
      };

      await service.dispatch(request);

      expect(mockedAxios).toHaveBeenCalledWith(
        expect.objectContaining({
          headers: expect.objectContaining({
            'X-Webhook-Signature': expect.stringMatching(/^sha256=[a-f0-9]+$/),
          }),
        }),
      );
    });

    it('should handle network errors', async () => {
      mockedAxios.mockRejectedValue(new Error('ECONNREFUSED'));

      const request = {
        id: 'webhook-network-error',
        tenantId: 'tenant-123',
        url: 'https://unreachable.example.com/webhook',
        method: 'POST' as const,
        body: { event: 'network-error-test' },
        maxRetries: 1,
      };

      const dispatchPromise = service.dispatch(request);
      await jest.advanceTimersByTimeAsync(1000);
      const result = await dispatchPromise;

      expect(result.success).toBe(false);
      expect(result.error).toBe('ECONNREFUSED');
    });

    it('should handle timeout errors', async () => {
      mockedAxios.mockRejectedValue(
        Object.assign(new Error('timeout of 30000ms exceeded'), {
          code: 'ECONNABORTED',
        }),
      );

      const request = {
        id: 'webhook-timeout',
        tenantId: 'tenant-123',
        url: 'https://slow.example.com/webhook',
        method: 'POST' as const,
        body: { event: 'timeout-test' },
        maxRetries: 0,
      };

      const result = await service.dispatch(request);

      expect(result.success).toBe(false);
      expect(result.error).toContain('timeout');
    });

    it('should use custom timeout', async () => {
      mockedAxios.mockResolvedValue({ status: 200, data: {} });

      const request = {
        id: 'webhook-custom-timeout',
        tenantId: 'tenant-123',
        url: 'https://example.com/webhook',
        method: 'POST' as const,
        body: { event: 'custom-timeout' },
        timeoutMs: 60000,
      };

      await service.dispatch(request);

      expect(mockedAxios).toHaveBeenCalledWith(
        expect.objectContaining({
          timeout: 60000,
        }),
      );
    });

    it('should support different HTTP methods', async () => {
      mockedAxios.mockResolvedValue({ status: 200, data: {} });

      const methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as const;

      for (const method of methods) {
        jest.clearAllMocks();
        await service.dispatch({
          id: `webhook-${method}`,
          tenantId: 'tenant-123',
          url: 'https://example.com/webhook',
          method,
          body: { method },
        });

        expect(mockedAxios).toHaveBeenCalledWith(
          expect.objectContaining({ method }),
        );
      }
    });
  });
});
