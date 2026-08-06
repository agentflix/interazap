import { ChatWebhookEventNormalizer } from './chat-webhook-event-normalizer.service';
import { PayloadSemanticsResolver } from './payload-semantics-resolver.service';
import { NormalizedWebhookEvent } from '../contracts/provider.interface';

describe('ChatWebhookEventNormalizer', () => {
  let normalizer: ChatWebhookEventNormalizer;

  beforeEach(() => {
    normalizer = new ChatWebhookEventNormalizer(
      new PayloadSemanticsResolver(),
    );
  });

  describe('mapNormalizedToStream', () => {
    it('preserves meta provider and adapter idempotency key', () => {
      const normalized: NormalizedWebhookEvent = {
        tenantId: 'tenant-1',
        instanceId: 'instance-1',
        instanceWebhookToken: 'token',
        provider: 'meta',
        eventType: 'messages',
        direction: 'inbound',
        message: {
          id: 'wamid.X',
          from: '5511999999999',
          to: '5511888888888',
          type: 'text',
          text: 'Olá',
          timestamp: new Date(),
          isFromMe: false,
          isGroup: false,
        },
        rawPayload: { object: 'whatsapp_business_account' },
        idempotencyKey: 'meta:instance-1:wamid.X',
        receivedAt: new Date(),
      };

      const streamPayload = normalizer.mapNormalizedToStream(normalized);

      expect(streamPayload.provider).toBe('meta');
      expect(streamPayload.idempotency_key).toBe('meta:instance-1:wamid.X');
      expect(streamPayload.message?.id).toBe('wamid.X');
      expect(streamPayload.direction).toBe('incoming');
    });

    it('omits idempotency_key when adapter does not provide one', () => {
      const normalized: NormalizedWebhookEvent = {
        tenantId: 'tenant-1',
        instanceId: 'instance-1',
        instanceWebhookToken: 'token',
        provider: 'zapi',
        eventType: 'message',
        direction: 'outbound',
        rawPayload: { messageId: 'zapi-1' },
        idempotencyKey: '',
        receivedAt: new Date(),
      };

      const streamPayload = normalizer.mapNormalizedToStream(normalized);

      expect(streamPayload.provider).toBe('zapi');
      expect(streamPayload.idempotency_key).toBeUndefined();
      expect(streamPayload.event_type).toBe('messages');
      expect(streamPayload.direction).toBe('outgoing');
    });

    it('maps template_status preserving meta template semantics', () => {
      const normalized: NormalizedWebhookEvent = {
        tenantId: 'tenant-1',
        instanceId: 'instance-1',
        instanceWebhookToken: '',
        provider: 'meta',
        eventType: 'meta.template.status_updated',
        direction: 'template_status',
        template: {
          external_id: 'tpl-1',
          name: 'Boas Vindas',
          language: 'pt_BR',
          event: 'APPROVED',
          status: 'approved',
          reason: null,
          mmlite_status: null,
        },
        rawPayload: {},
        idempotencyKey: 'meta:template:waba-1:tpl-1:APPROVED',
        receivedAt: new Date(),
      };

      const streamPayload = normalizer.mapNormalizedToStream(normalized);

      expect(streamPayload.provider).toBe('meta');
      expect(streamPayload.event_type).toBe('meta.template.status_updated');
      expect(streamPayload.direction).toBe('template_status');
      expect(streamPayload.template?.status).toBe('approved');
      expect(streamPayload.idempotency_key).toBe(
        'meta:template:waba-1:tpl-1:APPROVED',
      );
    });
  });

  describe('toStreamRecord', () => {
    it('propagates adapter idempotency_key into the stream record', () => {
      const normalized: NormalizedWebhookEvent = {
        tenantId: 'tenant-1',
        instanceId: 'instance-1',
        instanceWebhookToken: 'token',
        provider: 'meta',
        eventType: 'messages',
        direction: 'inbound',
        message: {
          id: 'wamid.Y',
          from: '5511999999999',
          to: '5511888888888',
          type: 'text',
          timestamp: new Date(),
          isFromMe: false,
          isGroup: false,
        },
        rawPayload: {},
        idempotencyKey: 'meta:instance-1:wamid.Y',
        receivedAt: new Date(),
      };

      const streamPayload = normalizer.mapNormalizedToStream(normalized);
      const record = normalizer.toStreamRecord(streamPayload);

      expect(record.provider).toBe('meta');
      expect(record.idempotency_key).toBe('meta:instance-1:wamid.Y');
    });

    it('keeps provider neutral (zapi) when mapping zapi normalized events', () => {
      const normalized: NormalizedWebhookEvent = {
        tenantId: 'tenant-1',
        instanceId: 'instance-1',
        instanceWebhookToken: 'token',
        provider: 'zapi',
        eventType: 'message',
        direction: 'outbound',
        rawPayload: {},
        idempotencyKey: 'zapi-key-1',
        receivedAt: new Date(),
      };

      const streamPayload = normalizer.mapNormalizedToStream(normalized);
      const record = normalizer.toStreamRecord(streamPayload);

      expect(record.provider).toBe('zapi');
      expect(record.idempotency_key).toBe('zapi-key-1');
    });
  });
});
