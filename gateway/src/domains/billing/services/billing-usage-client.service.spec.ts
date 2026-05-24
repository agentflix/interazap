import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import axios from 'axios';
import { BillingUsageClient } from './billing-usage-client.service';
import { BillingUsageMetrics } from '../../../metrics/billing-usage.metrics';

jest.mock('axios');
const mockedAxios = axios as jest.Mocked<typeof axios>;

describe('BillingUsageClient', () => {
  let service: BillingUsageClient;
  let mockMetrics: jest.Mocked<BillingUsageMetrics>;

  beforeEach(async () => {
    mockMetrics = {
      recordAiMessage: jest.fn(),
      recordUsageCheckFailure: jest.fn(),
      recordUsageCheckDuration: jest.fn(),
    } as unknown as jest.Mocked<BillingUsageMetrics>;

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        BillingUsageClient,
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn((key: string) => {
              const config: Record<string, string | number> = {
                'api.url': 'http://localhost:8000',
                'api.s2sToken': 'test-s2s-token',
                'api.authTimeoutMs': 1500,
              };
              return config[key];
            }),
          },
        },
        {
          provide: BillingUsageMetrics,
          useValue: mockMetrics,
        },
      ],
    }).compile();

    service = module.get<BillingUsageClient>(BillingUsageClient);
    jest.clearAllMocks();
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });

  describe('checkAndIncrement', () => {
    it('should return usage check result on success', async () => {
      const mockResponse = {
        data: {
          success: true,
          message: 'Usage checked',
          data: {
            allowed: true,
            current: 5,
            limit: 100,
            mode: 'stop',
            is_overage: false,
          },
        },
      };
      mockedAxios.post.mockResolvedValueOnce(mockResponse);
      mockedAxios.isAxiosError = jest.fn().mockReturnValue(false);

      const result = await service.checkAndIncrement(
        'tenant-1',
        'whatsapp',
        'turn-1',
      );

      expect(result).toEqual(mockResponse.data.data);
      expect(mockMetrics.recordAiMessage).toHaveBeenCalledWith(
        'tenant-1',
        'stop',
        true,
      );
      expect(mockMetrics.recordUsageCheckDuration).toHaveBeenCalled();
      expect(mockedAxios.post).toHaveBeenCalledWith(
        'http://localhost:8000/api/v1/billing/usage/check-and-increment',
        {
          tenant_id: 'tenant-1',
          channel: 'whatsapp',
          ai_turn_id: 'turn-1',
        },
        {
          headers: {
            'Content-Type': 'application/json',
            'X-Internal-Api-Key': 'test-s2s-token',
          },
          timeout: 1500,
        },
      );
    });

    it('should retry on failure and fallback to fail-open after all retries', async () => {
      mockedAxios.post.mockRejectedValue(new Error('Network error'));
      mockedAxios.isAxiosError = jest.fn().mockReturnValue(false);

      const result = await service.checkAndIncrement(
        'tenant-1',
        'whatsapp',
        'turn-1',
      );

      // 3 retries for check-and-increment + 1 for logFailure
      expect(mockedAxios.post).toHaveBeenCalledTimes(4);
      expect(result.allowed).toBe(true);
      expect(result.mode).toBe('fail_open');
      expect(mockMetrics.recordUsageCheckFailure).toHaveBeenCalledWith(
        'all_retries_exhausted',
      );
      expect(mockMetrics.recordAiMessage).toHaveBeenCalledWith(
        'tenant-1',
        'fail_open',
        true,
      );
    });

    it('should return fail-open with current=-1 on all retries exhausted', async () => {
      mockedAxios.post.mockRejectedValue(new Error('Timeout'));
      mockedAxios.isAxiosError = jest.fn().mockReturnValue(false);

      const result = await service.checkAndIncrement(
        'tenant-1',
        'whatsapp',
        'turn-1',
      );

      expect(result).toEqual({
        allowed: true,
        current: -1,
        limit: -1,
        mode: 'fail_open',
        is_overage: false,
      });
    });
  });

  describe('logFailure', () => {
    it('should log fail-open without throwing', async () => {
      mockedAxios.post.mockResolvedValueOnce({ data: {} });

      await service.logFailure({
        tenant_id: 'tenant-1',
        ai_turn_id: 'turn-1',
        channel: 'whatsapp',
        reason: 'timeout',
        attempted_at: new Date().toISOString(),
      });

      expect(mockedAxios.post).toHaveBeenCalledWith(
        'http://localhost:8000/api/v1/billing/usage/fail-open-log',
        expect.any(Object),
        {
          headers: {
            'Content-Type': 'application/json',
            'X-Internal-Api-Key': 'test-s2s-token',
          },
          timeout: 1500,
        },
      );
    });

    it('should not throw on failure', async () => {
      mockedAxios.post.mockRejectedValue(new Error('Network error'));

      await expect(
        service.logFailure({
          tenant_id: 'tenant-1',
          ai_turn_id: 'turn-1',
          channel: 'whatsapp',
          reason: 'timeout',
          attempted_at: new Date().toISOString(),
        }),
      ).resolves.not.toThrow();
    });
  });
});
