/**
 * Contrato base para todas as implementacoes de provedor WhatsApp (UazAPI, Z-API, Meta).
 * Define as operacoes obrigatorias de envio, status e normalizacao de webhook.
 */

/** Payload normalizado de mensagem WhatsApp extraido de qualquer provedor suportado. */
export interface MessagePayload {
  id: string;
  from: string;
  to: string;
  type:
    | 'text'
    | 'image'
    | 'video'
    | 'audio'
    | 'document'
    | 'sticker'
    | 'location'
    | 'contact';
  text?: string;
  caption?: string;
  mediaUrl?: string;
  mimeType?: string;
  fileName?: string;
  latitude?: number;
  longitude?: number;
  contact?: {
    name: string;
    phone: string;
  };
  timestamp: Date;
  isFromMe: boolean;
  isGroup: boolean;
  quotedMessageId?: string;
  senderPhoto?: string;
}

/** Payload normalizado de status de entrega de mensagem. */
export interface StatusPayload {
  messageId: string;
  status: 'sent' | 'delivered' | 'read' | 'failed';
  timestamp: Date;
  error?: string;
}

/** Requisicao de envio de mensagem de texto para um provedor WhatsApp. */
export interface SendTextRequest {
  to: string;
  text: string;
  quotedMessageId?: string;
}

/** Requisicao de envio de midia (imagem, video, audio, documento) para um provedor WhatsApp. */
export interface SendMediaRequest {
  to: string;
  type: 'image' | 'video' | 'audio' | 'document';
  mediaUrl: string;
  caption?: string;
  fileName?: string;
  mimeType?: string;
}

/** Resultado normalizado de uma operacao de envio de mensagem. */
export interface SendMessageResult {
  success: boolean;
  messageId?: string;
  error?: string;
}

/** Status normalizado de conexao de uma instancia WhatsApp. */
export interface InstanceStatus {
  connected: boolean;
  loggedIn: boolean;
  qrCode?: string;
  pairCode?: string;
  phone?: string;
}

export interface WhatsAppProvider {
  /** Identificador canonico do provedor. */
  readonly name: 'uazapi' | 'zapi' | 'meta' | 'telegram';

  /**
   * Envia mensagem de texto para o destinatario.
   * @param instanceToken Token de autenticacao da instancia no provedor
   * @param request Dados da mensagem de texto
   * @returns Resultado normalizado do envio
   */
  sendText(
    instanceToken: string,
    request: SendTextRequest,
  ): Promise<SendMessageResult>;

  /**
   * Envia mensagem de midia (imagem, video, audio, documento).
   * @param instanceToken Token de autenticacao da instancia no provedor
   * @param request Dados da mensagem de midia
   * @returns Resultado normalizado do envio
   */
  sendMedia(
    instanceToken: string,
    request: SendMediaRequest,
  ): Promise<SendMessageResult>;

  /**
   * Consulta o status de conexao da instancia.
   * @param instanceToken Token de autenticacao da instancia no provedor
   * @returns Status de conexao normalizado
   */
  getStatus(instanceToken: string): Promise<InstanceStatus>;

  /**
   * Desconecta a instancia do provedor.
   * @param instanceToken Token de autenticacao da instancia no provedor
   */
  disconnect(instanceToken: string): Promise<void>;

  /**
   * Recupera o QR code para pareamento da instancia.
   * @param instanceToken Token de autenticacao da instancia no provedor
   * @returns String do QR code ou null quando nao disponivel
   */
  getQrCode(instanceToken: string): Promise<string | null>;

  /**
   * Normaliza o payload bruto de webhook para o formato interno padronizado.
   * @param token Token do webhook da instancia
   * @param rawPayload Payload bruto recebido do provedor
   * @returns Evento normalizado no contrato interno
   */
  normalizeWebhook(
    token: string,
    rawPayload: unknown,
  ): Promise<NormalizedWebhookEvent>;
}

/**
 * Status normalizado de um template Meta (evento `message_template_status_update`).
 *
 * `event` preserva o valor cru recebido da Meta (APPROVED/REJECTED/PENDING/DISABLED).
 * `status` é a forma minúscula esperada pelo Backend.
 */
export interface NormalizedTemplateStatusPayload {
  external_id: string;
  name: string;
  language: string;
  event: string;
  status: 'approved' | 'rejected' | 'pending' | 'disabled' | 'unknown';
  reason: string | null;
  mmlite_status: string | null;
}

export interface NormalizedWebhookEvent {
  tenantId: string;
  instanceId: string;
  instanceWebhookToken: string;
  provider: 'uazapi' | 'zapi' | 'meta';
  eventType: string;
  direction:
    | 'inbound'
    | 'outbound'
    | 'status'
    | 'connection'
    | 'template_status';
  message?: MessagePayload;
  status?: StatusPayload;
  connection?: {
    status: string;
    connected: boolean;
    qrCode?: string;
    pairCode?: string;
  };
  /**
   * Presente apenas para eventos da Meta `message_template_status_update`.
   * Quando definido, `direction === 'template_status'` e
   * `eventType === 'meta.template.status_updated'`.
   */
  template?: NormalizedTemplateStatusPayload;
  rawPayload: Record<string, unknown>;
  idempotencyKey: string;
  receivedAt: Date;
}
