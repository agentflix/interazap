import { MetaWebhookPayload } from '../../../contracts/meta-provider.interface';

/**
 * Payload real da Meta em lote: 2 `entry`, cada uma com 1 `change` contendo
 * 2 `messages`.
 *
 * Reproduz o Bug B do diagnóstico da feature `meta-window-webhook`
 * (`.context/DOCS/FEATURES/meta-window-webhook.md`) — o normalizer antigo
 * lia apenas `entry[0].changes[0].messages[0]` e descartava silenciosamente
 * as outras 3 mensagens do lote. `normalizeAll`/`normalizeWebhookBatch`
 * devem produzir exatamente 4 eventos a partir deste payload.
 */
export const metaMultiEntryBatchPayload: MetaWebhookPayload = {
  object: 'whatsapp_business_account',
  entry: [
    {
      id: 'WABA_BATCH_1',
      time: 1700000000,
      changes: [
        {
          field: 'messages',
          value: {
            messaging_product: 'whatsapp',
            metadata: {
              display_phone_number: '5511999999999',
              phone_number_id: 'PHN_BATCH',
            },
            contacts: [
              { wa_id: '5511888888881', profile: { name: 'Cliente 1' } },
              { wa_id: '5511888888882', profile: { name: 'Cliente 2' } },
            ],
            messages: [
              {
                from: '5511888888881',
                id: 'wamid.BATCH_A',
                timestamp: '1700000000',
                type: 'text',
                text: { body: 'primeira mensagem do lote' },
              },
              {
                from: '5511888888882',
                id: 'wamid.BATCH_B',
                timestamp: '1700000001',
                type: 'text',
                text: { body: 'segunda mensagem do lote' },
              },
            ],
          },
        },
      ],
    },
    {
      id: 'WABA_BATCH_1',
      time: 1700000002,
      changes: [
        {
          field: 'messages',
          value: {
            messaging_product: 'whatsapp',
            metadata: {
              display_phone_number: '5511999999999',
              phone_number_id: 'PHN_BATCH',
            },
            contacts: [
              { wa_id: '5511888888883', profile: { name: 'Cliente 3' } },
              { wa_id: '5511888888884', profile: { name: 'Cliente 4' } },
            ],
            messages: [
              {
                from: '5511888888883',
                id: 'wamid.BATCH_C',
                timestamp: '1700000002',
                type: 'text',
                text: { body: 'terceira mensagem do lote' },
              },
              {
                from: '5511888888884',
                id: 'wamid.BATCH_D',
                timestamp: '1700000003',
                type: 'text',
                text: { body: 'quarta mensagem do lote' },
              },
            ],
          },
        },
      ],
    },
  ],
};
