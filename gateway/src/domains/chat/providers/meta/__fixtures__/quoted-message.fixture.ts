import { MetaWebhookPayload } from '../../../contracts/meta-provider.interface';

/**
 * Inbound que responde/cita outra mensagem (`messages[].context.id`) — deve
 * virar `quotedMessageId` no evento normalizado (`MessagePayload.quotedMessageId`).
 */
export const metaQuotedMessagePayload: MetaWebhookPayload = {
  object: 'whatsapp_business_account',
  entry: [
    {
      id: 'WABA_QUOTED',
      time: 1700000000,
      changes: [
        {
          field: 'messages',
          value: {
            messaging_product: 'whatsapp',
            metadata: {
              display_phone_number: '5511999999999',
              phone_number_id: 'PHN_QUOTED',
            },
            messages: [
              {
                from: '5511888888888',
                id: 'wamid.REPLY_1',
                timestamp: '1700000000',
                type: 'text',
                text: { body: 'Confirmado, obrigado!' },
                context: { id: 'wamid.ORIGINAL_1', from: '5511999999999' },
              },
            ],
          },
        },
      ],
    },
  ],
};
