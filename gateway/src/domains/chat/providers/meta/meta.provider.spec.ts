import { MetaProvider } from './meta.provider';
import { MetaWebhookPayload } from '../../contracts/meta-provider.interface';
import {
  metaMultiEntryBatchPayload,
  metaQuotedMessagePayload,
  metaStatusWindow72hPayload,
  metaStatusWindow24hPayload,
  metaStatusFailedPayload,
} from './__fixtures__';

describe('MetaProvider.normalize', () => {
  let provider: MetaProvider;

  beforeEach(() => {
    provider = new MetaProvider();
  });

  const baseEntry = (
    field: string,
    value: MetaWebhookPayload['entry'][0]['changes'][0]['value'],
  ): MetaWebhookPayload => ({
    object: 'whatsapp_business_account',
    entry: [
      {
        id: 'WABA_123',
        time: 1700000000,
        changes: [{ field, value }],
      },
    ],
  });

  describe('messages', () => {
    it('normalizes incoming text message as inbound', () => {
      const payload = baseEntry('messages', {
        messaging_product: 'whatsapp',
        metadata: {
          display_phone_number: '5511999999999',
          phone_number_id: 'PHN_ID',
        },
        messages: [
          {
            from: '5511888888888',
            id: 'wamid.ABC',
            timestamp: '1700000000',
            type: 'text',
            text: { body: 'olá' },
          },
        ],
      });

      const result = provider.normalize(payload);

      expect(result.event_type).toBe('message_received');
      expect(result.direction).toBe('inbound');
      expect(result.phone_number_id).toBe('PHN_ID');
      expect(result.message?.text).toBe('olá');
      expect(result.template).toBeUndefined();
    });
  });

  describe('statuses', () => {
    it('normalizes status update', () => {
      const payload = baseEntry('messages', {
        messaging_product: 'whatsapp',
        metadata: {
          display_phone_number: '5511999999999',
          phone_number_id: 'PHN_ID',
        },
        statuses: [
          {
            id: 'wamid.STATUS',
            recipient_id: '5511888888888',
            status: 'delivered',
            timestamp: '1700000001',
          },
        ],
      });

      const result = provider.normalize(payload);

      expect(result.event_type).toBe('message_status');
      expect(result.direction).toBe('status');
      expect(result.status?.messageId).toBe('wamid.STATUS');
      expect(result.status?.status).toBe('delivered');
    });
  });

  describe('message_template_status_update', () => {
    const buildTemplatePayload = (
      overrides: Partial<{
        event: string;
        message_template_id: string | number;
        message_template_name: string;
        message_template_language: string;
        reason: string | null;
        mmlite_status: string | null;
      }> = {},
    ): MetaWebhookPayload => {
      const value = baseEntry('message_template_status_update', {
        // metadata is intentionally absent — template events don't carry it
        messaging_product: 'whatsapp',
        metadata: {
          display_phone_number: '',
          phone_number_id: '',
        },
      } as any).entry[0].changes[0].value as any;

      // Template events carry 'event' field directly on the value
      return {
        object: 'whatsapp_business_account',
        entry: [
          {
            id: 'WABA_123',
            time: 1700000000,
            changes: [
              {
                field: 'message_template_status_update',
                value: {
                  ...value,
                  event: overrides.event ?? 'APPROVED',
                  message_template_id:
                    overrides.message_template_id ?? '987654321012345',
                  message_template_name:
                    overrides.message_template_name ?? 'welcome_message',
                  message_template_language:
                    overrides.message_template_language ?? 'pt_BR',
                  reason: overrides.reason ?? null,
                  mmlite_status: overrides.mmlite_status ?? null,
                },
              },
            ],
          },
        ],
      };
    };

    it('maps APPROVED → approved with structured template payload', () => {
      const result = provider.normalize(
        buildTemplatePayload({
          event: 'APPROVED',
          message_template_id: '111',
          message_template_name: 'order_confirmed',
          message_template_language: 'pt_BR',
        }),
      );

      expect(result.template).toEqual({
        external_id: '111',
        name: 'order_confirmed',
        language: 'pt_BR',
        event: 'APPROVED',
        status: 'approved',
        reason: null,
        mmlite_status: null,
      });
    });

    it.each([
      ['REJECTED', 'rejected'],
      ['PENDING', 'pending'],
      ['DISABLED', 'disabled'],
    ])('maps %s → %s', (rawEvent, expectedStatus) => {
      const result = provider.normalize(
        buildTemplatePayload({ event: rawEvent }),
      );
      expect(result.template?.status).toBe(expectedStatus);
      expect(result.template?.event).toBe(rawEvent);
    });

    it('falls back to status=unknown for unexpected event values', () => {
      const result = provider.normalize(
        buildTemplatePayload({ event: 'WHATEVER' }),
      );
      expect(result.template?.status).toBe('unknown');
    });

    it('preserves reason when REJECTED carries one', () => {
      const result = provider.normalize(
        buildTemplatePayload({
          event: 'REJECTED',
          reason: 'INVALID_FORMAT',
        }),
      );
      expect(result.template?.status).toBe('rejected');
      expect(result.template?.reason).toBe('INVALID_FORMAT');
    });

    it('handles missing reason as null', () => {
      const result = provider.normalize(
        buildTemplatePayload({ event: 'APPROVED' }),
      );
      expect(result.template?.reason).toBeNull();
    });

    it('coerces numeric message_template_id to string', () => {
      const result = provider.normalize(
        buildTemplatePayload({
          message_template_id: 123456789012345,
        }),
      );
      expect(result.template?.external_id).toBe('123456789012345');
    });

    it('does not populate message or status fields for template events', () => {
      const result = provider.normalize(buildTemplatePayload());
      expect(result.message).toBeUndefined();
      expect(result.status).toBeUndefined();
    });
  });

  describe('normalizeAll', () => {
    it('produces one event per message when a change carries multiple messages', () => {
      const payload = baseEntry('messages', {
        messaging_product: 'whatsapp',
        metadata: {
          display_phone_number: '5511999999999',
          phone_number_id: 'PHN_ID',
        },
        messages: [
          {
            from: '5511888888888',
            id: 'wamid.1',
            timestamp: '1700000000',
            type: 'text',
            text: { body: 'primeira' },
          },
          {
            from: '5511888888889',
            id: 'wamid.2',
            timestamp: '1700000001',
            type: 'text',
            text: { body: 'segunda' },
          },
        ],
      });

      const events = provider.normalizeAll(payload);

      expect(events).toHaveLength(2);
      expect(events[0].message?.id).toBe('wamid.1');
      expect(events[1].message?.id).toBe('wamid.2');
    });

    it('produces one event per entry across a multi-entry batch (fixture real da Meta: 2 entries x 2 messages)', () => {
      const events = provider.normalizeAll(metaMultiEntryBatchPayload);

      expect(events).toHaveLength(4);
      expect(events.map((e) => e.message?.id)).toEqual([
        'wamid.BATCH_A',
        'wamid.BATCH_B',
        'wamid.BATCH_C',
        'wamid.BATCH_D',
      ]);
    });

    it('extracts quotedMessageId from messages[].context.id', () => {
      const [event] = provider.normalizeAll(metaQuotedMessagePayload);

      expect(event.message?.quotedMessageId).toBe('wamid.ORIGINAL_1');
    });

    it('marks window as 72h from status.conversation.origin.type === referral_conversion', () => {
      const [event] = provider.normalizeAll(metaStatusWindow72hPayload);

      expect(event.status?.window).toEqual({
        expiresAt: new Date(1700259200 * 1000),
        type: '72h',
      });
    });

    it('marks window as 24h when origin.type is not referral_conversion', () => {
      const [event] = provider.normalizeAll(metaStatusWindow24hPayload);

      expect(event.status?.window).toEqual({
        expiresAt: new Date(1700086400 * 1000),
        type: '24h',
      });
    });

    it('propagates failed status with errors, never masking as sent', () => {
      const [event] = provider.normalizeAll(metaStatusFailedPayload);

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
    });
  });
});
