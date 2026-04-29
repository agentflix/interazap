/**
 * WhatsApp Provider Interface
 * Defines the contract for all WhatsApp provider implementations (UazAPI, Z-API)
 */

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

export interface StatusPayload {
  messageId: string;
  status: 'sent' | 'delivered' | 'read' | 'failed';
  timestamp: Date;
  error?: string;
}

export interface SendTextRequest {
  to: string;
  text: string;
  quotedMessageId?: string;
}

export interface SendMediaRequest {
  to: string;
  type: 'image' | 'video' | 'audio' | 'document';
  mediaUrl: string;
  caption?: string;
  fileName?: string;
  mimeType?: string;
}

export interface SendMessageResult {
  success: boolean;
  messageId?: string;
  error?: string;
}

export interface InstanceStatus {
  connected: boolean;
  loggedIn: boolean;
  qrCode?: string;
  pairCode?: string;
  phone?: string;
}

export interface WhatsAppProvider {
  /**
   * Provider identifier
   */
  readonly name: 'uazapi' | 'zapi' | 'meta' | 'telegram';

  /**
   * Send a text message
   */
  sendText(
    instanceToken: string,
    request: SendTextRequest,
  ): Promise<SendMessageResult>;

  /**
   * Send a media message (image, video, audio, document)
   */
  sendMedia(
    instanceToken: string,
    request: SendMediaRequest,
  ): Promise<SendMessageResult>;

  /**
   * Get instance connection status
   */
  getStatus(instanceToken: string): Promise<InstanceStatus>;

  /**
   * Disconnect instance
   */
  disconnect(instanceToken: string): Promise<void>;

  /**
   * Get QR Code for connection
   */
  getQrCode(instanceToken: string): Promise<string | null>;

  /**
   * Normalize raw webhook payload to standard format
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
