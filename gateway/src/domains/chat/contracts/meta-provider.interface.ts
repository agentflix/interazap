import { WhatsAppProvider } from './provider.interface';
import type {
  SendMessageResult,
  NormalizedWebhookEvent,
} from './provider.interface';

// Re-exporta tipos para uso externo
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

/** Requisicao de envio de mensagem via template aprovado da Meta. */
export interface SendTemplateRequest {
  /** Numero do destinatario da mensagem. */
  to: string;
  /** Nome do template aprovado a ser enviado. */
  templateName: string;
  /** Parametros a substituir no corpo do template. */
  templateParams?: string[];
  /** Codigo de idioma do template (padrao: 'pt_BR'). */
  language?: string;
}

/** Template de mensagem aprovado na conta Business da Meta. */
export interface MetaTemplate {
  /** Nome unico do template. */
  name: string;
  /** Status de aprovacao do template. */
  status: 'APPROVED' | 'PENDING' | 'REJECTED';
  /** Categoria do template conforme classificacao da Meta. */
  category: string;
  /** Codigo de idioma do template. */
  language: string;
  /** Componentes que compoem o template. */
  components: TemplateComponent[];
}

/** Componente de um template de mensagem da Meta. */
export interface TemplateComponent {
  /** Tipo do componente no template. */
  type: 'HEADER' | 'BODY' | 'FOOTER' | 'BUTTONS';
  /** Parametros de exemplo do componente. */
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
