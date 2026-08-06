import { Test, TestingModule } from '@nestjs/testing';
import { MetaAdapter } from './meta.adapter';
import { MetaClient } from './meta.client';
import { MetaProvider } from './meta.provider';
import { MetaLookupService } from '../../http/meta-lookup.service';
import { RedisService } from '../../../../infrastructure/redis/redis.service';
import { MetaTemplate } from '../../contracts/meta-provider.interface';
import type { MetaTemplateCreatePayload } from './meta.dto';
import {
  metaMultiEntryBatchPayload,
  metaCtwaReferralPayload,
  metaStatusWindow72hPayload,
  metaStatusWindow24hPayload,
  metaStatusFailedPayload,
  metaDuplicateDeliveryPayload,
} from './__fixtures__';

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
  let provider: { normalize: jest.Mock; normalizeAll: jest.Mock };

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

  /** Chave de cache esperada (hash sha256 do ACCESS token, 32 chars). */
  const cacheKeyFor = (includeAll: boolean) => {
    const { createHash } = require('crypto') as typeof import('crypto');
    const identifier = createHash('sha256')
      .update('accessToken')
      .digest('hex')
      .slice(0, 32);
    return includeAll
      ? `meta:templates:all:${identifier}`
      : `meta:templates:approved:${identifier}`;
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
    provider = { normalize: jest.fn(), normalizeAll: jest.fn() };

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
      expect(client.getTemplates).toHaveBeenCalledWith('accessToken', {
        status: 'APPROVED',
      });
      expect(redisClient.get).toHaveBeenCalledWith(cacheKeyFor(false));
      expect(redisClient.setex).toHaveBeenCalledWith(
        cacheKeyFor(false),
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
      expect(client.getTemplates).toHaveBeenCalledWith('accessToken', {
        status: undefined,
      });
      expect(redisClient.get).toHaveBeenCalledWith(cacheKeyFor(true));
      expect(redisClient.setex).toHaveBeenCalledWith(
        cacheKeyFor(true),
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
        if (key === cacheKeyFor(false)) {
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
      expect(client.getTemplates).toHaveBeenCalledWith('accessToken', {
        status: undefined,
      });
    });

    it('does not expose the access token in redis cache keys', async () => {
      client.getTemplates.mockResolvedValue([approvedTemplate]);

      await adapter.listTemplates(token);

      for (const call of [
        ...redisClient.get.mock.calls,
        ...redisClient.setex.mock.calls,
      ]) {
        expect(JSON.stringify(call)).not.toContain('accessToken');
      }
      expect(redisClient.get.mock.calls[0][0]).toContain('meta:templates:');
    });

    it('returns empty list when token is empty', async () => {
      const result = await adapter.listTemplates('');

      expect(result).toEqual([]);
      expect(client.getTemplates).not.toHaveBeenCalled();
      expect(redisClient.get).not.toHaveBeenCalled();
    });

    it('accepts a raw access token (settings.access_token sem phoneNumberId:)', async () => {
      client.getTemplates.mockResolvedValue([approvedTemplate]);

      const result = await adapter.listTemplates('raw-access-token');

      expect(result).toEqual([approvedTemplate]);
      expect(client.getTemplates).toHaveBeenCalledWith('raw-access-token', {
        status: 'APPROVED',
      });
      // A chave de cache usa hash do access token cru — nunca o token literal.
      const cacheKey = redisClient.get.mock.calls[0][0];
      expect(cacheKey).toContain('meta:templates:approved:');
      expect(cacheKey).not.toContain('raw-access-token');
    });

    it('hashes list and invalidate caches from the same access token (raw vs wabaId:token)', async () => {
      client.getTemplates.mockResolvedValue([approvedTemplate]);

      await adapter.listTemplates('same-access-token');
      const listKey = redisClient.get.mock.calls[0][0];

      await adapter.invalidateTemplatesCache('waba-123:same-access-token');
      const [approvedKey, allKey] = redisClient.del.mock.calls[0];

      expect(approvedKey).toBe(listKey);
      expect(approvedKey).not.toContain('waba-123');
      expect(approvedKey).not.toContain('same-access-token');
      expect(allKey).toContain('meta:templates:all:');
    });
  });

  describe('invalidateTemplatesCache', () => {
    it('deletes both approved and all cache keys', async () => {
      await adapter.invalidateTemplatesCache(token);

      expect(redisClient.del).toHaveBeenCalledWith(
        cacheKeyFor(false),
        cacheKeyFor(true),
      );
      expect(JSON.stringify(redisClient.del.mock.calls)).not.toContain(
        'accessToken',
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
      provider.normalizeAll.mockReturnValue([
        {
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
        },
      ]);

      const result = await adapter.normalizeWebhook(
        webhookToken,
        buildTemplatePayload(),
      );

      expect(lookupService.resolveWabaId).toHaveBeenCalledWith(wabaId);
      expect(lookupService.resolvePhoneNumberId).not.toHaveBeenCalled();
      expect(result).toMatchObject({
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
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

    it('discards event (no throw) when wabaId lookup returns null for template event', async () => {
      lookupService.resolveWabaId.mockResolvedValue(null);

      await expect(
        adapter.normalizeWebhook(webhookToken, buildTemplatePayload()),
      ).rejects.toThrow(/No resolvable event produced/);
    });

    it('discards event (no throw) when entry.id is missing on template event', async () => {
      const payload = buildTemplatePayload();
      payload.entry[0].id = '';

      await expect(
        adapter.normalizeWebhook(webhookToken, payload),
      ).rejects.toThrow(/No resolvable event produced/);
      expect(lookupService.resolveWabaId).not.toHaveBeenCalled();
    });

    it('routes regular messages via phone_number_id lookup and never validates webhookToken against it', async () => {
      lookupService.resolvePhoneNumberId.mockResolvedValue({
        tenantId: 'tenant-2',
        instanceId: 'inst-2',
        webhookToken: 'real-webhook-token-from-instance',
      });
      provider.normalizeAll.mockReturnValue([
        {
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
        },
      ]);

      // O `webhookToken` recebido (na verdade um phone_number_id, per bug
      // histórico) NÃO é comparado contra a instância resolvida — apenas
      // usado como parâmetro legado do contrato `MetaWhatsAppProvider`.
      const result = await adapter.normalizeWebhook(
        'PHN_ID', // phone_number_id, nao um webhookToken real — nao deve causar mismatch
        buildMessagesPayload(),
      );

      expect(lookupService.resolvePhoneNumberId).toHaveBeenCalledWith('PHN_ID');
      expect(lookupService.resolveWabaId).not.toHaveBeenCalled();
      expect(result.direction).toBe('inbound');
      expect(result.template).toBeUndefined();
      // instanceWebhookToken vem da instância resolvida, não do parâmetro de entrada
      expect(result.instanceWebhookToken).toBe(
        'real-webhook-token-from-instance',
      );
      expect(result.idempotencyKey).toBe('meta:inst-2:wamid.X');
    });
  });

  describe('normalizeWebhookBatch (integração com MetaProvider real)', () => {
    let realAdapter: MetaAdapter;

    const buildMessageChange = (
      phoneNumberId: string,
      messages: Array<Record<string, unknown>>,
    ) => ({
      field: 'messages',
      value: {
        messaging_product: 'whatsapp',
        metadata: {
          display_phone_number: '5511999999999',
          phone_number_id: phoneNumberId,
        },
        messages,
      },
    });

    beforeEach(async () => {
      const module: TestingModule = await Test.createTestingModule({
        providers: [
          MetaAdapter,
          { provide: MetaClient, useValue: client },
          { provide: RedisService, useValue: redisService },
          { provide: MetaLookupService, useValue: lookupService },
          MetaProvider,
        ],
      }).compile();

      realAdapter = module.get<MetaAdapter>(MetaAdapter);
    });

    it('processes a batch of 2 entries x 2 messages into 4 events (fixture real da Meta)', async () => {
      lookupService.resolvePhoneNumberId.mockResolvedValue({
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        webhookToken: 'whk-1',
      });

      const events = await realAdapter.normalizeWebhookBatch(
        metaMultiEntryBatchPayload,
      );

      expect(events).toHaveLength(4);
      expect(events.map((e) => e.message?.id)).toEqual([
        'wamid.BATCH_A',
        'wamid.BATCH_B',
        'wamid.BATCH_C',
        'wamid.BATCH_D',
      ]);
      expect(events.every((e) => e.tenantId === 'tenant-1')).toBe(true);
    });

    it('produces a stable idempotencyKey across two identical calls (reentrega duplicada da Meta)', async () => {
      lookupService.resolvePhoneNumberId.mockResolvedValue({
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        webhookToken: 'whk-1',
      });

      const first = await realAdapter.normalizeWebhookBatch(
        metaDuplicateDeliveryPayload,
      );
      const second = await realAdapter.normalizeWebhookBatch(
        metaDuplicateDeliveryPayload,
      );

      expect(first[0].idempotencyKey).toBe(second[0].idempotencyKey);
      expect(first[0].idempotencyKey).toBe('meta:inst-1:wamid.DUP_RETRY');
    });

    it('marks window.type as 72h when inbound message carries referral (CTWA fixture)', async () => {
      lookupService.resolvePhoneNumberId.mockResolvedValue({
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        webhookToken: 'whk-1',
      });

      const [event] = await realAdapter.normalizeWebhookBatch(
        metaCtwaReferralPayload,
      );

      expect(event.message?.referral).toEqual({
        source_id: '120210000000000',
        source_type: 'ad',
        headline: 'Promoção de Verão',
        ctwa_clid: 'AfeI3clidExemplo',
      });
    });

    it('captures window.expiresAt from status.conversation.expiration_timestamp (72h for referral_conversion)', async () => {
      lookupService.resolvePhoneNumberId.mockResolvedValue({
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        webhookToken: 'whk-1',
      });

      const [event] = await realAdapter.normalizeWebhookBatch(
        metaStatusWindow72hPayload,
      );

      expect(event.status?.window).toEqual({
        expiresAt: new Date(1700259200 * 1000),
        type: '72h',
      });
    });

    it('captures window.expiresAt from status.conversation.expiration_timestamp (24h without referral_conversion)', async () => {
      lookupService.resolvePhoneNumberId.mockResolvedValue({
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        webhookToken: 'whk-1',
      });

      const [event] = await realAdapter.normalizeWebhookBatch(
        metaStatusWindow24hPayload,
      );

      expect(event.status?.window).toEqual({
        expiresAt: new Date(1700086400 * 1000),
        type: '24h',
      });
    });

    it('propagates failed status as failed (never masked as sent) with errors', async () => {
      lookupService.resolvePhoneNumberId.mockResolvedValue({
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        webhookToken: 'whk-1',
      });

      const [event] = await realAdapter.normalizeWebhookBatch(
        metaStatusFailedPayload,
      );

      expect(event.status?.status).toBe('failed');
      expect(event.status?.errors).toEqual([
        {
          code: 131047,
          title: 'Re-engagement message',
          message:
            'Message failed to send because more than 24 hours have passed since the customer last replied to this number.',
          details: 'Outside the 24-hour customer service window',
        },
      ]);
      expect(event.idempotencyKey).toBe(
        'meta:inst-1:wamid.STATUS_FAILED:failed',
      );
    });

    it('discards events (no 4xx) when phone_number_id is unknown, without aborting other entries', async () => {
      lookupService.resolvePhoneNumberId.mockImplementation(
        (phoneNumberId: string) =>
          Promise.resolve(
            phoneNumberId === 'KNOWN_PHN'
              ? {
                  tenantId: 'tenant-1',
                  instanceId: 'inst-1',
                  webhookToken: 'whk-1',
                }
              : null,
          ),
      );

      const payload = {
        object: 'whatsapp_business_account' as const,
        entry: [
          {
            id: 'WABA_1',
            time: 1700000000,
            changes: [
              buildMessageChange('UNKNOWN_PHN', [
                {
                  from: '5511888888881',
                  id: 'wamid.UNKNOWN',
                  timestamp: '1700000000',
                  type: 'text',
                  text: { body: 'ignored' },
                },
              ]),
            ],
          },
          {
            id: 'WABA_1',
            time: 1700000001,
            changes: [
              buildMessageChange('KNOWN_PHN', [
                {
                  from: '5511888888882',
                  id: 'wamid.KNOWN',
                  timestamp: '1700000001',
                  type: 'text',
                  text: { body: 'processed' },
                },
              ]),
            ],
          },
        ],
      };

      const events = await realAdapter.normalizeWebhookBatch(payload);

      expect(events).toHaveLength(1);
      expect(events[0].message?.id).toBe('wamid.KNOWN');
    });
  });
});
