import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import axios from 'axios';
import { Registry } from 'prom-client';
import { InternalApiClientService } from './internal-api-client.service';
import { MetricsService } from '../../metrics/metrics.service';

jest.mock('axios');

const mockAxiosInstance = {
  get: jest.fn(),
  post: jest.fn(),
  patch: jest.fn(),
};

(axios.create as jest.Mock).mockReturnValue(mockAxiosInstance);

describe('InternalApiClientService', () => {
  let service: InternalApiClientService;
  let registry: Registry;

  beforeEach(async () => {
    registry = new Registry();

    const configService = {
      get: jest.fn((key: string) => {
        if (key === 'internal.apiUrl') return 'http://api.internal';
        if (key === 'internal.apiKey') return 'test-api-key';
        if (key === 'internal.timeoutMs') return 5000;
        return undefined;
      }),
    };

    const metricsService = {
      getRegistry: jest.fn().mockReturnValue(registry),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        InternalApiClientService,
        { provide: ConfigService, useValue: configService },
        { provide: MetricsService, useValue: metricsService },
      ],
    }).compile();

    service = module.get<InternalApiClientService>(InternalApiClientService);
    service.onModuleInit();

    mockAxiosInstance.get.mockReset();
    mockAxiosInstance.post.mockReset();
    mockAxiosInstance.patch.mockReset();
  });

  describe('get()', () => {
    it('retorna dados tipados em caso de sucesso', async () => {
      const payload = { id: '123', name: 'test' };
      mockAxiosInstance.get.mockResolvedValue({ status: 200, data: payload });

      const result = await service.get<typeof payload>(
        '/tenants/123',
        'get-tenant',
      );

      expect(result).toEqual(payload);
      expect(mockAxiosInstance.get).toHaveBeenCalledTimes(1);
    });

    it('registra histograma gateway_internal_api_duration_seconds no registry', () => {
      const names = registry.getMetricsAsArray().map((m) => m.name);
      expect(names).toContain('gateway_internal_api_duration_seconds');
    });
  });

  describe('post()', () => {
    it('retorna dados tipados em caso de sucesso', async () => {
      const body = { foo: 'bar' };
      const responseData = { id: 'new-id' };
      mockAxiosInstance.post.mockResolvedValue({
        status: 201,
        data: responseData,
      });

      const result = await service.post<typeof responseData>(
        '/messages',
        body,
        'create-message',
      );

      expect(result).toEqual(responseData);
      expect(mockAxiosInstance.post).toHaveBeenCalledWith('/messages', body);
    });
  });

  describe('patch()', () => {
    it('retorna dados tipados em caso de sucesso', async () => {
      const body = { status: 'active' };
      const responseData = { updated: true };
      mockAxiosInstance.patch.mockResolvedValue({
        status: 200,
        data: responseData,
      });

      const result = await service.patch<typeof responseData>(
        '/tenants/123',
        body,
        'update-tenant',
      );

      expect(result).toEqual(responseData);
      expect(mockAxiosInstance.patch).toHaveBeenCalledWith(
        '/tenants/123',
        body,
      );
    });
  });

  describe('não faz retry em erros 4xx (exceto 429)', () => {
    it('falha imediatamente em erro 400', async () => {
      const error400 = Object.assign(new Error('Bad Request'), {
        response: { status: 400 },
        isAxiosError: true,
      });
      mockAxiosInstance.get.mockRejectedValue(error400);

      await expect(service.get('/path', 'op')).rejects.toThrow();
      expect(mockAxiosInstance.get).toHaveBeenCalledTimes(1);
    });
  });

  describe('circuit breaker', () => {
    it('abre após 5 erros consecutivos e rejeita sem chamar axios', async () => {
      const error400 = Object.assign(new Error('Bad Request'), {
        response: { status: 400 },
        isAxiosError: true,
      });
      mockAxiosInstance.get.mockRejectedValue(error400);

      for (let i = 0; i < 5; i++) {
        await expect(service.get('/path', 'op')).rejects.toThrow();
      }

      expect(mockAxiosInstance.get).toHaveBeenCalledTimes(5);

      await expect(service.get('/path', 'op')).rejects.toThrow(
        'circuit breaker OPEN',
      );
      expect(mockAxiosInstance.get).toHaveBeenCalledTimes(5);
    });

    it('reseta após o cooldown de 30s', async () => {
      jest.useFakeTimers();

      const error400 = Object.assign(new Error('Bad Request'), {
        response: { status: 400 },
        isAxiosError: true,
      });
      mockAxiosInstance.get.mockRejectedValue(error400);

      for (let i = 0; i < 5; i++) {
        await expect(service.get('/path', 'op')).rejects.toThrow();
      }

      jest.advanceTimersByTime(31_000);

      mockAxiosInstance.get.mockResolvedValue({
        status: 200,
        data: { ok: true },
      });

      const result = await service.get('/path', 'op');
      expect(result).toEqual({ ok: true });

      jest.useRealTimers();
    });
  });
});
