import { Test, TestingModule } from '@nestjs/testing';
import { ProviderFactory } from './provider.factory';
import { UazapiAdapter } from './uazapi/uazapi.adapter';
import { ZapiAdapter } from './zapi/zapi.adapter';
import { MetaAdapter } from './meta/meta.adapter';

describe('ProviderFactory', () => {
  let factory: ProviderFactory;
  let mockUazapiAdapter: Partial<UazapiAdapter>;
  let mockZapiAdapter: Partial<ZapiAdapter>;
  let mockMetaAdapter: Partial<MetaAdapter>;

  beforeEach(async () => {
    mockUazapiAdapter = {
      name: 'uazapi',
      sendText: jest.fn(),
      sendMedia: jest.fn(),
      getStatus: jest.fn(),
      disconnect: jest.fn(),
      getQrCode: jest.fn(),
      normalizeWebhook: jest.fn(),
    } as unknown as Partial<UazapiAdapter>;

    mockZapiAdapter = {
      name: 'zapi',
      sendText: jest.fn(),
      sendMedia: jest.fn(),
      getStatus: jest.fn(),
      disconnect: jest.fn(),
      getQrCode: jest.fn(),
      normalizeWebhook: jest.fn(),
    };

    mockMetaAdapter = {
      name: 'meta',
      sendText: jest.fn(),
      sendMedia: jest.fn(),
      getStatus: jest.fn(),
      disconnect: jest.fn(),
      getQrCode: jest.fn(),
      normalizeWebhook: jest.fn(),
      listTemplates: jest.fn(),
      sendTemplate: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        ProviderFactory,
        { provide: UazapiAdapter, useValue: mockUazapiAdapter },
        { provide: ZapiAdapter, useValue: mockZapiAdapter },
        { provide: MetaAdapter, useValue: mockMetaAdapter },
      ],
    }).compile();

    factory = module.get<ProviderFactory>(ProviderFactory);
  });

  describe('getProvider', () => {
    it('should return uazapi provider', () => {
      const provider = factory.getProvider('uazapi');
      expect(provider).toBeDefined();
    });

    it('should return zapi provider', () => {
      const provider = factory.getProvider('zapi');
      expect(provider).toBeDefined();
      expect(provider).toBe(mockZapiAdapter);
    });

    it('should return meta provider', () => {
      const provider = factory.getProvider('meta');
      expect(provider).toBeDefined();
      expect(provider).toBe(mockMetaAdapter);
    });

    it('should throw for unknown provider', () => {
      expect(() => factory.getProvider('unknown' as 'uazapi')).toThrow(
        'Provider not found: unknown',
      );
    });
  });

  describe('hasProvider', () => {
    it('should return true for uazapi', () => {
      expect(factory.hasProvider('uazapi')).toBe(true);
    });

    it('should return true for zapi', () => {
      expect(factory.hasProvider('zapi')).toBe(true);
    });

    it('should return true for meta', () => {
      expect(factory.hasProvider('meta')).toBe(true);
    });

    it('should return false for unknown provider', () => {
      expect(factory.hasProvider('unknown')).toBe(false);
    });
  });

  describe('getProviderNames', () => {
    it('should return all provider names', () => {
      const names = factory.getProviderNames();
      expect(names).toContain('uazapi');
      expect(names).toContain('zapi');
      expect(names).toContain('meta');
      expect(names).toHaveLength(3);
    });
  });
});
