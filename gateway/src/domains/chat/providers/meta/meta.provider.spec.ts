import { MetaProvider } from './meta.provider';
import { MetaWebhookPayload } from '../../contracts/meta-provider.interface';

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
    ): MetaWebhookPayload =>
      baseEntry('message_template_status_update', {
        // metadata is intentionally absent — template events don't carry it
        messaging_product: 'whatsapp',
        metadata: {
          display_phone_number: '',
          phone_number_id: '',
        },
        event: overrides.event ?? 'APPROVED',
        message_template_id: overrides.message_template_id ?? '987654321012345',
        message_template_name:
          overrides.message_template_name ?? 'welcome_message',
        message_template_language:
          overrides.message_template_language ?? 'pt_BR',
        reason: overrides.reason ?? null,
        mmlite_status: overrides.mmlite_status ?? null,
      });

    it('produces event_type=meta.template.status_updated and direction=template_status', () => {
      const result = provider.normalize(buildTemplatePayload());

      expect(result.event_type).toBe('meta.template.status_updated');
      expect(result.direction).toBe('template_status');
      expect(result.template).toBeDefined();
    });

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
});
