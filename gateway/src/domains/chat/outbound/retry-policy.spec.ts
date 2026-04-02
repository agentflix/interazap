import { RetryPolicy } from './retry-policy';
import { ConfigService } from '@nestjs/config';

describe('RetryPolicy', () => {
  let retryPolicy: RetryPolicy;

  beforeEach(() => {
    retryPolicy = new RetryPolicy({
      get: jest.fn((key: string) => {
        if (key === 'RETRY_MAX_RETRIES') {
          return '3';
        }

        if (key === 'RETRY_BASE_DELAY_MS') {
          return '1000';
        }

        if (key === 'RETRY_MAX_DELAY_MS') {
          return '60000';
        }

        if (key === 'RETRY_EXPONENTIAL_BASE') {
          return '4';
        }

        return undefined;
      }),
    } as unknown as ConfigService);
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  describe('execute', () => {
    it('should succeed on first attempt', async () => {
      const operation = jest.fn().mockResolvedValue('success');

      const resultPromise = retryPolicy.execute(operation);
      jest.runAllTimers();
      const result = await resultPromise;

      expect(result.success).toBe(true);
      expect(result.data).toBe('success');
      expect(result.attempts).toBe(1);
      expect(operation).toHaveBeenCalledTimes(1);
    });

    it('should retry on failure and succeed', async () => {
      const operation = jest
        .fn()
        .mockRejectedValueOnce(new Error('Fail 1'))
        .mockRejectedValueOnce(new Error('Fail 2'))
        .mockResolvedValue('success');

      const resultPromise = retryPolicy.execute(operation);

      // Fast-forward through retries
      await jest.advanceTimersByTimeAsync(1000); // 1st retry delay
      await jest.advanceTimersByTimeAsync(4000); // 2nd retry delay

      const result = await resultPromise;

      expect(result.success).toBe(true);
      expect(result.data).toBe('success');
      expect(result.attempts).toBe(3);
      expect(operation).toHaveBeenCalledTimes(3);
    });

    it('should fail after max retries exhausted', async () => {
      const error = new Error('Persistent failure');
      const operation = jest.fn().mockRejectedValue(error);

      const resultPromise = retryPolicy.execute(operation, { maxRetries: 2 });

      // Fast-forward through all retries
      await jest.advanceTimersByTimeAsync(1000);
      await jest.advanceTimersByTimeAsync(4000);

      const result = await resultPromise;

      expect(result.success).toBe(false);
      expect(result.attempts).toBe(3); // 1 initial + 2 retries
      expect(result.lastError).toBe(error);
      expect(operation).toHaveBeenCalledTimes(3);
    });

    it('should use custom retry options', async () => {
      const operation = jest
        .fn()
        .mockRejectedValueOnce(new Error('Fail'))
        .mockResolvedValue('success');

      const resultPromise = retryPolicy.execute(operation, {
        maxRetries: 1,
        baseDelayMs: 500,
        exponentialBase: 2,
      });

      await jest.advanceTimersByTimeAsync(500);
      const result = await resultPromise;

      expect(result.success).toBe(true);
      expect(result.attempts).toBe(2);
    });

    it('should cap delay at maxDelayMs', async () => {
      const operation = jest
        .fn()
        .mockRejectedValueOnce(new Error('Fail 1'))
        .mockRejectedValueOnce(new Error('Fail 2'))
        .mockRejectedValueOnce(new Error('Fail 3'))
        .mockResolvedValue('success');

      const resultPromise = retryPolicy.execute(operation, {
        maxRetries: 3,
        baseDelayMs: 10000,
        maxDelayMs: 15000,
        exponentialBase: 4,
      });

      // First delay: 10000ms (10000 * 4^0)
      await jest.advanceTimersByTimeAsync(10000);
      // Second delay: 15000ms (capped from 40000)
      await jest.advanceTimersByTimeAsync(15000);
      // Third delay: 15000ms (capped)
      await jest.advanceTimersByTimeAsync(15000);

      const result = await resultPromise;

      expect(result.success).toBe(true);
      expect(result.attempts).toBe(4);
    });

    it('should handle synchronous errors', async () => {
      const operation = jest.fn().mockImplementation(() => {
        throw new Error('Sync error');
      });

      const resultPromise = retryPolicy.execute(operation, { maxRetries: 1 });

      await jest.advanceTimersByTimeAsync(1000);
      const result = await resultPromise;

      expect(result.success).toBe(false);
      expect(result.lastError?.message).toBe('Sync error');
    });
  });
});
