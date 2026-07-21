import { MetaWebhookPayload } from '../../../contracts/meta-provider.interface';

/**
 * Constrói um payload de status outbound com `conversation.expiration_timestamp`
 * (unix seconds) + `conversation.origin.type`, no formato real da Meta.
 */
const buildStatusWindowPayload = (overrides: {
  id: string;
  timestamp: string;
  originType: string;
  expirationTimestamp: string;
}): MetaWebhookPayload => ({
  object: 'whatsapp_business_account',
  entry: [
    {
      id: 'WABA_WINDOW',
      time: 1700000000,
      changes: [
        {
          field: 'messages',
          value: {
            messaging_product: 'whatsapp',
            metadata: {
              display_phone_number: '5511999999999',
              phone_number_id: 'PHN_WINDOW',
            },
            statuses: [
              {
                id: overrides.id,
                recipient_id: '5511888888888',
                status: 'sent',
                timestamp: overrides.timestamp,
                conversation: {
                  id: 'conv-window-1',
                  origin: { type: overrides.originType },
                  expiration_timestamp: overrides.expirationTimestamp,
                },
                pricing: {
                  billable: true,
                  pricing_model: 'CBP',
                  category:
                    overrides.originType === 'referral_conversion'
                      ? 'referral_conversion'
                      : 'service',
                },
              },
            ],
          },
        },
      ],
    },
  ],
});

/**
 * Status outbound com `conversation.origin.type === 'referral_conversion'`
 * (conversão originada de CTWA) → janela deve ser normalizada como `'72h'`.
 */
export const metaStatusWindow72hPayload = buildStatusWindowPayload({
  id: 'wamid.STATUS_72H',
  timestamp: '1700000000',
  originType: 'referral_conversion',
  expirationTimestamp: '1700259200', // 1700000000 + 72h
});

/**
 * Status outbound sem origem CTWA (`user_initiated`) → janela deve ser
 * normalizada como `'24h'` (padrão da Meta).
 */
export const metaStatusWindow24hPayload = buildStatusWindowPayload({
  id: 'wamid.STATUS_24H',
  timestamp: '1700000000',
  originType: 'user_initiated',
  expirationTimestamp: '1700086400', // 1700000000 + 24h
});
