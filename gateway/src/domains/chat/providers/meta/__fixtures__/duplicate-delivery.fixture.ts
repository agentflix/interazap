import { MetaWebhookPayload } from '../../../contracts/meta-provider.interface';

/**
 * Payload de entrega única, usado para provar o comportamento de reentrega
 * da Meta: chamar `MetaAdapter.normalizeWebhookBatch` duas vezes com este
 * MESMO payload (mesmo objeto, simulando o retry da Meta com o corpo idêntico)
 * deve produzir a MESMA `idempotencyKey` nas duas chamadas.
 *
 * Regressão travada: a `idempotencyKey` costumava incluir `Date.now()`,
 * gerando uma chave nova a cada reentrega e quebrando a deduplicação.
 */
export const metaDuplicateDeliveryPayload: MetaWebhookPayload = {
  object: 'whatsapp_business_account',
  entry: [
    {
      id: 'WABA_DUP',
      time: 1700000000,
      changes: [
        {
          field: 'messages',
          value: {
            messaging_product: 'whatsapp',
            metadata: {
              display_phone_number: '5511999999999',
              phone_number_id: 'PHN_DUP',
            },
            messages: [
              {
                from: '5511888888888',
                id: 'wamid.DUP_RETRY',
                timestamp: '1700000000',
                type: 'text',
                text: { body: 'oi, tudo bem?' },
              },
            ],
          },
        },
      ],
    },
  ],
};
