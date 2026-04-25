/**
 * DTOs especificos para o provider Meta WhatsApp Business API.
 */

/**
 * Resposta da API Graph da Meta para listagem de templates.
 */
export interface MetaGraphTemplatesResponse {
  data: Array<{
    name: string;
    status: string;
    category: string;
    language: string;
    components: Array<{
      type: string;
      text?: string;
      format?: string;
      example?: {
        header_text?: string[];
        body_text?: string[][];
      };
      buttons?: Array<{
        type: string;
        text: string;
      }>;
    }>;
  }>;
  paging?: {
    cursors: {
      before: string;
      after: string;
    };
    next?: string;
  };
}

/**
 * Resposta da API Graph da Meta para envio de mensagem via template.
 */
export interface MetaSendTemplateResponse {
  messaging_product: 'whatsapp';
  contacts: Array<{
    wa_id: string;
    input_number: string;
  }>;
  messages: Array<{
    id: string;
  }>;
}

/**
 * Resposta da API Graph da Meta para download de midia.
 */
export interface MetaMediaResponse {
  id: string;
  mime_type: string;
  sha256: string;
  url?: string;
  messaging_product: 'whatsapp';
}

/**
 * Payload para envio de template via API Graph.
 */
export interface MetaSendTemplatePayload {
  messaging_product: 'whatsapp';
  to: string;
  type: 'template';
  template: {
    name: string;
    language: {
      code: string;
    };
    components?: Array<{
      type: string;
      parameters: Array<{
        type: string;
        text?: string;
      }>;
    }>;
  };
}

/**
 * Resultado do lookup de phone_number_id via Backend.
 */
export interface InstanceLookupResult {
  instanceId: string;
  tenantId: string;
  webhookToken: string;
}

/**
 * Filtros para busca de templates na API Graph.
 */
export interface ListTemplatesFilters {
  status?: 'APPROVED' | 'PENDING' | 'REJECTED';
  limit?: number;
}

/**
 * Resultado do envio de mensagem.
 */
export interface SendResult {
  success: boolean;
  messageId?: string;
  error?: string;
}
