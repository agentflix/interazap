import { MetaWebhookPayload } from '../../../contracts/meta-provider.interface';

/**
 * Status `failed` real da Meta com `errors[]` (código 131047 — re-engagement:
 * mensagem enviada fora da janela de atendimento de 24h). O status `failed`
 * nunca deve ser mascarado como `sent`, e os `errors[]` devem ser propagados
 * no evento normalizado.
 */
export const metaStatusFailedPayload: MetaWebhookPayload = {
  object: 'whatsapp_business_account',
  entry: [
    {
      id: 'WABA_FAILED',
      time: 1700000000,
      changes: [
        {
          field: 'messages',
          value: {
            messaging_product: 'whatsapp',
            metadata: {
              display_phone_number: '5511999999999',
              phone_number_id: 'PHN_FAILED',
            },
            statuses: [
              {
                id: 'wamid.STATUS_FAILED',
                recipient_id: '5511888888888',
                status: 'failed',
                timestamp: '1700000000',
                errors: [
                  {
                    code: 131047,
                    title: 'Re-engagement message',
                    message:
                      'Message failed to send because more than 24 hours have passed since the customer last replied to this number.',
                    error_data: {
                      details: 'Outside the 24-hour customer service window',
                    },
                  },
                ],
              },
            ],
          },
        },
      ],
    },
  ],
};
