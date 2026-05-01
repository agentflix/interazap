import { Test, TestingModule } from '@nestjs/testing';
import { MetaAdapter } from './meta.adapter';
import { MetaClient } from './meta.client';
import { MetaProvider } from './meta.provider';
import { MetaLookupService } from '../../http/meta-lookup.service';
import { RedisService } from '../../../../infrastructure/redis/redis.service';
import { MetaTemplate } from '../../contracts/meta-provider.interface';
import type { MetaTemplateCreatePayload } from './meta.dto';

describe('MetaAdapter', () => {
  let adapter: MetaAdapter;
  let client: {
    getTemplates: jest.Mock;
    sendTemplate: jest.Mock;
    createTemplate: jest.Mock;
    deleteTemplate: jest.Mock;
  };
  let redisClient: {
    get: jest.Mock;
    setex: jest.Mock;
    del: jest.Mock;
  };
  let redisService: { getClient: jest.Mock };
  let lookupService: {
    resolvePhoneNumberId: jest.Mock;
    resolveWabaId: jest.Mock;
  };
  let provider: { normalize: jest.Mock };

  const token = 'phoneId:accessToken';
  const approvedTemplate: MetaTemplate = {
    name: 'welcome',
    status: 'APPROVED',
    category: 'MARKETING',
    language: 'pt_BR',
    components: [],
  };
  const pendingTemplate: MetaTemplate = {
    name: 'wip',
    status: 'PENDING',
    category: 'UTILITY',
    language: 'pt_BR',
    components: [],
  };

  beforeEach(async () => {
    client = {
      getTemplates: jest.fn(),
      sendTemplate: jest.fn(),
      createTemplate: jest.fn(),
      deleteTemplate: jest.fn(),
    };
    redisClient = {
      get: jest.fn().mockResolvedValue(null),
      setex: jest.fn().mockResolvedValue('OK'),
      del: jest.fn().mockResolvedValue(2),
    };
    redisService = {
      getClient: jest.fn().mockReturnValue(redisClient),
    };

    lookupService = {
      resolvePhoneNumberId: jest.fn(),
      resolveWabaId: jest.fn(),
    };
    provider = { normalize: jest.fn() };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        MetaAdapter,
        { provide: MetaClient, useValue: client },
        { provide: RedisService, useValue: redisService },
        {
          provide: MetaLookupService,
          useValue: lookupService,
        },
        { provide: MetaProvider, useValue: provider },
      ],
    }).compile();

    adapter = module.get<MetaAdapter>(MetaAdapter);
  });

  describe('listTemplates', () => {
    it('uses APPROVED filter and approved cache key by default', async () => {
      client.getTemplates.mockResolvedValue([approvedTemplate]);

      const result = await adapter.listTemplates(token);

      expect(result).toEqual([approvedTemplate]);
      expect(client.getTemplates).toHaveBeenCalledWith(token, {
        status: 'APPROVED',
      });
      expect(redisClient.get).toHaveBeenCalledWith(
        `meta:templates:approved:${token}`,
      );
      expect(redisClient.setex).toHaveBeenCalledWith(
        `meta:templates:approved:${token}`,
        900,
        JSON.stringify([approvedTemplate]),
      );
    });

    it('returns all statuses and uses all cache key when includeAll=true', async () => {
      client.getTemplates.mockResolvedValue([
        approvedTemplate,
        pendingTemplate,
      ]);

      const result = await adapter.listTemplates(token, true);

      expect(result).toEqual([approvedTemplate, pendingTemplate]);
      expect(client.getTemplates).toHaveBeenCalledWith(token, {
        status: undefined,
      });
      expect(redisClient.get).toHaveBeenCalledWith(
        `meta:templates:all:${token}`,
      );
      expect(redisClient.setex).toHaveBeenCalledWith(
        `meta:templates:all:${token}`,
        900,
        JSON.stringify([approvedTemplate, pendingTemplate]),
      );
    });

    it('returns cached approved templates without hitting client', async () => {
      redisClient.get.mockResolvedValueOnce(JSON.stringify([approvedTemplate]));

      const result = await adapter.listTemplates(token);

      expect(result).toEqual([approvedTemplate]);
      expect(client.getTemplates).not.toHaveBeenCalled();
    });

    it('keeps approved and all caches independent', async () => {
      // approved cache hits, all cache must still go to client
      redisClient.get.mockImplementation((key: string) => {
        if (key === `meta:templates:approved:${token}`) {
          return Promise.resolve(JSON.stringify([approvedTemplate]));
        }
        return Promise.resolve(null);
      });
      client.getTemplates.mockResolvedValue([
        approvedTemplate,
        pendingTemplate,
      ]);

      const cached = await adapter.listTemplates(token);
      const all = await adapter.listTemplates(token, true);

      expect(cached).toEqual([approvedTemplate]);
      expect(all).toEqual([approvedTemplate, pendingTemplate]);
      expect(client.getTemplates).toHaveBeenCalledTimes(1);
      expect(client.getTemplates).toHaveBeenCalledWith(token, {
        status: undefined,
      });
    });
  });

  describe('invalidateTemplatesCache', () => {
    it('deletes both approved and all cache keys', async () => {
      await adapter.invalidateTemplatesCache(token);

      expect(redisClient.del).toHaveBeenCalledWith(
        `meta:templates:approved:${token}`,
        `meta:templates:all:${token}`,
      );
    });

    it('does not throw when redis fails', async () => {
      redisClient.del.mockRejectedValueOnce(new Error('redis down'));

      await expect(
        adapter.invalidateTemplatesCache(token),
      ).resolves.toBeUndefined();
    });
  });

  describe('createTemplate', () => {
    const wabaToken = 'waba-123:access-xyz';
    const payload: MetaTemplateCreatePayload = {
      name: 'welcome',
      language: 'pt_BR',
      category: 'MARKETING',
      components: [{ type: 'BODY', text: 'Hello {{1}}' }],
    };

    it('parses waba token, calls client and invalidates cache', async () => {
      client.createTemplate.mockResolvedValue({
        id: 'tpl-1',
        status: 'PENDING',
        category: 'MARKETING',
      });
      const invalidateSpy = jest.spyOn(adapter, 'invalidateTemplatesCache');

      const result = await adapter.createTemplate(wabaToken, payload);

      expect(result).toEqual({
        id: 'tpl-1',
        status: 'PENDING',
        category: 'MARKETING',
      });
      expect(client.createTemplate).toHaveBeenCalledWith(
        'waba-123',
        'access-xyz',
        payload,
      );
      expect(invalidateSpy).toHaveBeenCalledWith(wabaToken);
    });

    it('throws when token is malformed (missing accessToken)', async () => {
      await expect(
        adapter.createTemplate('only-waba-id', payload),
      ).rejects.toThrow(/Invalid instance token format/);
      expect(client.createTemplate).not.toHaveBeenCalled();
    });

    it('throws when token has empty wabaId', async () => {
      await expect(
        adapter.createTemplate(':access-only', payload),
      ).rejects.toThrow(/Invalid instance token format/);
    });
  });

  describe('deleteTemplate', () => {
    const wabaToken = 'waba-123:access-xyz';

    it('parses waba token, calls client and invalidates cache', async () => {
      client.deleteTemplate.mockResolvedValue({ success: true });
      const invalidateSpy = jest.spyOn(adapter, 'invalidateTemplatesCache');

      const result = await adapter.deleteTemplate(wabaToken, 'welcome');

      expect(result).toEqual({ success: true });
      expect(client.deleteTemplate).toHaveBeenCalledWith(
        'waba-123',
        'access-xyz',
        'welcome',
      );
      expect(invalidateSpy).toHaveBeenCalledWith(wabaToken);
    });

    it('throws when token is malformed', async () => {
      await expect(
        adapter.deleteTemplate('bad-token', 'welcome'),
      ).rejects.toThrow(/Invalid instance token format/);
      expect(client.deleteTemplate).not.toHaveBeenCalled();
    });
  });

  describe('normalizeWebhook', () => {
    const webhookToken = 'whk_abc';
    const wabaId = 'WABA_999';

    const buildTemplatePayload = () => ({
      object: 'whatsapp_business_account',
      entry: [
        {
          id: wabaId,
          time: 1700000000,
          changes: [
            {
              field: 'message_template_status_update',
              value: {
                event: 'APPROVED',
                message_template_id: '111',
                message_template_name: 'welcome',
                message_template_language: 'pt_BR',
                reason: null,
                mmlite_status: null,
              },
            },
          ],
        },
      ],
    });

    const buildMessagesPayload = () => ({
      object: 'whatsapp_business_account',
      entry: [
        {
          id: wabaId,
          time: 1700000000,
          changes: [
            {
              field: 'messages',
              value: {
                messaging_product: 'whatsapp',
                metadata: {
                  display_phone_number: '5511999999999',
                  phone_number_id: 'PHN_ID',
                },
                messages: [
                  {
                    from: '5511888888888',
                    id: 'wamid.X',
                    timestamp: '1700000000',
                    type: 'text',
                    text: { body: 'olá' },
                  },
                ],
              },
            },
          ],
        },
      ],
    });

    it('routes template_status_update via wabaId lookup (no phone_number_id required)', async () => {
      lookupService.resolveWabaId.mockResolvedValue({
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
      });
      provider.normalize.mockReturnValue({
        provider: 'meta',
        event_type: 'meta.template.status_updated',
        direction: 'template_status',
        phone_number_id: '',
        display_phone_number: '',
        template: {
          external_id: '111',
          name: 'welcome',
          language: 'pt_BR',
          event: 'APPROVED',
          status: 'approved',
          reason: null,
          mmlite_status: null,
        },
        raw: buildTemplatePayload(),
      });

      const result = await adapter.normalizeWebhook(
        webhookToken,
        buildTemplatePayload(),
      );

      expect(lookupService.resolveWabaId).toHaveBeenCalledWith(wabaId);
      expect(lookupService.resolvePhoneNumberId).not.toHaveBeenCalled();
      expect(result).toMatchObject({
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        instanceWebhookToken: webhookToken,
        provider: 'meta',
        eventType: 'meta.template.status_updated',
        direction: 'template_status',
        template: {
          external_id: '111',
          name: 'welcome',
          language: 'pt_BR',
          event: 'APPROVED',
          status: 'approved',
          reason: null,
          mmlite_status: null,
        },
      });
      expect(result.idempotencyKey).toBe(
        `meta:template:${wabaId}:111:APPROVED`,
      );
    });

    it('throws when wabaId lookup returns null for template event', async () => {
      lookupService.resolveWabaId.mockResolvedValue(null);

      await expect(
        adapter.normalizeWebhook(webhookToken, buildTemplatePayload()),
      ).rejects.toThrow(/Instance not found for waba_id/);
    });

    it('throws when entry.id is missing on template event', async () => {
      const payload = buildTemplatePayload();
      payload.entry[0].id = '';

      await expect(
        adapter.normalizeWebhook(webhookToken, payload),
      ).rejects.toThrow(/waba_id .* not found/);
      expect(lookupService.resolveWabaId).not.toHaveBeenCalled();
    });

    it('routes regular messages via phone_number_id lookup', async () => {
      lookupService.resolvePhoneNumberId.mockResolvedValue({
        tenantId: 'tenant-2',
        instanceId: 'inst-2',
        webhookToken,
      });
      provider.normalize.mockReturnValue({
        provider: 'meta',
        event_type: 'message_received',
        direction: 'inbound',
        phone_number_id: 'PHN_ID',
        display_phone_number: '5511999999999',
        message: {
          id: 'wamid.X',
          from: '5511888888888',
          to: '5511999999999',
          type: 'text',
          text: 'olá',
          timestamp: new Date(1700000000000),
          isFromMe: false,
          isGroup: false,
        },
        raw: buildMessagesPayload(),
      });

      const result = await adapter.normalizeWebhook(
        webhookToken,
        buildMessagesPayload(),
      );

      expect(lookupService.resolvePhoneNumberId).toHaveBeenCalledWith('PHN_ID');
      expect(lookupService.resolveWabaId).not.toHaveBeenCalled();
      expect(result.direction).toBe('inbound');
      expect(result.template).toBeUndefined();
    });
  });
});
