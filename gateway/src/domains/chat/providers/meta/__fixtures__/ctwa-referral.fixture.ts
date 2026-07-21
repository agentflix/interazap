import { MetaWebhookPayload } from '../../../contracts/meta-provider.interface';

/**
 * Inbound originado de um anúncio Click-to-WhatsApp (CTWA).
 * `messages[].referral` marca a abertura da janela de atendimento de 72h
 * (ver `references/customer-service-window.md` da skill `meta-whatsapp-expert`).
 *
 * Apenas `source_id`, `source_type`, `headline` e `ctwa_clid` são propagados
 * pelo normalizer para `MessagePayload.referral` — os demais campos do
 * `referral` bruto (`source_url`, `body`, `media_type`, `image_url`) são
 * incluídos aqui para fidelidade ao payload real, mas descartados de propósito.
 */
export const metaCtwaReferralPayload: MetaWebhookPayload = {
  object: 'whatsapp_business_account',
  entry: [
    {
      id: 'WABA_CTWA',
      time: 1700000000,
      changes: [
        {
          field: 'messages',
          value: {
            messaging_product: 'whatsapp',
            metadata: {
              display_phone_number: '5511999999999',
              phone_number_id: 'PHN_CTWA',
            },
            contacts: [
              { wa_id: '5511888888888', profile: { name: 'Lead CTWA' } },
            ],
            messages: [
              {
                from: '5511888888888',
                id: 'wamid.CTWA_1',
                timestamp: '1700000000',
                type: 'text',
                text: { body: 'Vim do anúncio, quero saber mais' },
                referral: {
                  source_id: '120210000000000',
                  source_type: 'ad',
                  source_url: 'https://fb.me/ad-click',
                  headline: 'Promoção de Verão',
                  body: 'Aproveite 20% off',
                  media_type: 'image',
                  image_url: 'https://scontent.xx.fbcdn.net/ad-image.jpg',
                  ctwa_clid: 'AfeI3clidExemplo',
                },
              },
            ],
          },
        },
      ],
    },
  ],
};
