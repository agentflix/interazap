import { WhatsAppProvider } from './provider.interface';
import type {
  SendMessageResult,
  NormalizedWebhookEvent,
} from './provider.interface';

// Re-export types for convenience
export type {
  SendMessageResult,
  NormalizedWebhookEvent,
  MessagePayload,
} from './provider.interface';

/**
 * Interface dedicada para Meta WhatsApp Business API.
 * Estende WhatsAppProvider com metodos especificos que só a Meta suporta.
 */
export interface MetaWhatsAppProvider extends WhatsAppProvider {
  readonly name: 'meta';

  /**
   * Lista templates aprovados da conta Business (usa cache Redis TTL 15min).
   */
  listTemplates(instanceToken: string): Promise<MetaTemplate[]>;

  /**
   * Envia mensagem via template aprovado (obrigatorio fora da janela 24h).
   */
  sendTemplate(
    instanceToken: string,
    request: SendTemplateRequest,
  ): Promise<SendMessageResult>;

  /**
   * Normaliza payload do webhook da Meta.
   * Chama Backend via HTTP para resolver phone_number_id -> ChatInstance.
   * METODO ASSINCRONO.
   */
  normalizeWebhook(
    webhookToken: string,
    rawPayload: unknown,
  ): Promise<NormalizedWebhookEvent>;
}

export interface SendTemplateRequest {
  to: string;
  templateName: string;
  templateParams?: string[];
  language?: string; // default: 'pt_BR'
}

export interface MetaTemplate {
  name: string;
  status: 'APPROVED' | 'PENDING' | 'REJECTED';
  category: string;
  language: string;
  components: TemplateComponent[];
}

export interface TemplateComponent {
  type: 'HEADER' | 'BODY' | 'FOOTER' | 'BUTTONS';
  params?: string[];
}

/**
 * Payload completo do webhook da Meta WhatsApp Business API.
 * Ref: https://developers.facebook.com/docs/whatsapp/webhooks/webhooks-payload
 */
export interface MetaWebhookPayload {
  object: 'whatsapp_business_account';
  entry: Array<{
    id: string;
    time: number;
    changes: Array<{
      value: {
        messaging_product: 'whatsapp';
        metadata: {
          display_phone_number: string;
          phone_number_id: string;
        };
        contacts?: Array<{
          wa_id: string;
          profile: { name: string };
        }>;
        messages?: Array<{
          from: string;
          id: string;
          timestamp: string;
          type: string;
          text?: { body: string };
          image?: {
            id: string;
            mime_type: string;
            sha256: string;
            url?: string;
          };
          audio?: { id: string; mime_type: string; voice: boolean };
          video?: { id: string; mime_type: string; sha256: string };
          document?: {
            id: string;
            mime_type: string;
            sha256: string;
            filename: string;
          };
          location?: {
            latitude: number;
            longitude: number;
            name?: string;
            address?: string;
          };
          contacts?: Array<{
            wa_id: string;
            profile: { name: string };
            phones: Array<{ phone: string; type?: string }>;
          }>;
        }>;
        statuses?: Array<{
          id: string;
          recipient_id: string;
          status: string;
          timestamp: string;
          conversation?: {
            id: string;
            origin: { type: string };
            expiry?: string;
          };
          pricing?: {
            billable: boolean;
            pricing_model: string;
            category: string;
          };
        }>;
      };
      field: string;
    }>;
  }>;
}
