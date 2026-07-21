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
 * Erro reportado pela Meta Graph API no corpo de uma resposta de envio.
 * A Meta pode retornar HTTP 200 com `errors[]` preenchido no corpo.
 */
export interface MetaApiError {
  code: number;
  title: string;
  message?: string;
  error_data?: { details?: string };
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
  /** Presente quando a Meta retorna HTTP 200 com erro no corpo (ex.: 131047). */
  errors?: MetaApiError[];
}

/**
 * Payload para envio de mensagem de texto livre via API Graph.
 */
export interface MetaSendTextPayload {
  messaging_product: 'whatsapp';
  to: string;
  type: 'text';
  text: { body: string };
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
 * Resultado do lookup de WABA ID via Backend.
 * Usado para eventos de template (`message_template_status_update`)
 * que não trazem `phone_number_id` no payload.
 */
export interface WabaLookupResult {
  tenantId: string;
  instanceId: string;
}

/**
 * Filtros para busca de templates na API Graph.
 */
export interface ListTemplatesFilters {
  status?: 'APPROVED' | 'PENDING' | 'REJECTED';
  limit?: number;
}

/**
 * Payload para criacao de template na API Graph da Meta.
 */
export interface MetaTemplateCreatePayload {
  name: string;
  language: string;
  category: string;
  components: unknown[];
}

/**
 * Resultado do envio de mensagem.
 */
export interface SendResult {
  success: boolean;
  messageId?: string;
  error?: string;
}
