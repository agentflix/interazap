/**
 * Modelos do dominio Chat/Z-API do gateway.
 * Tipos de request/response do client e payload de webhook normalizado.
 */

/**
 * Payload de envio de texto para a API da Z-API.
 */
export interface ZapiSendTextRequest {
  /** Numero de telefone do destinatario. */
  phone: string;
  /** Conteudo textual da mensagem. */
  message: string;
}

/**
 * Payload de envio de midia para a API da Z-API.
 */
export interface ZapiSendMediaRequest {
  /** Numero de telefone do destinatario. */
  phone: string;
  /** URL de imagem para envio. */
  image?: string;
  /** URL de video para envio. */
  video?: string;
  /** URL de audio para envio. */
  audio?: string;
  /** URL de documento para envio. */
  document?: string;
  /** Legenda opcional da midia. */
  caption?: string;
  /** Nome de arquivo opcional para documentos. */
  fileName?: string;
}

/**
 * Resposta de envio retornada pela Z-API.
 */
export interface ZapiSendResponse {
  /** Identificador da mensagem no formato especifico da Z-API. */
  zapiMessageId?: string;
  /** Identificador de mensagem em formato generico. */
  messageId?: string;
  /** Campo alternativo de identificador de mensagem. */
  id?: string;
}

/**
 * Resposta de status de conexao da instancia na Z-API.
 */
export interface ZapiStatusResponse {
  /** Indica se a instancia esta conectada no WhatsApp. */
  connected: boolean;
  /** Identificador de sessao retornado pela API. */
  session?: string;
  /** Indica se o smartphone esta conectado. */
  smartphoneConnected?: boolean;
}

/**
 * Estrutura de payload de webhook recebida da Z-API.
 */
export interface ZapiWebhookPayload {
  instanceId?: string;
  phone?: string;
  isStatusReply?: boolean;
  chatLid?: string;
  connectedPhone?: string;
  waitingMessage?: boolean;
  isEdit?: boolean;
  isGroup?: boolean;
  isNewsletter?: boolean;
  fromMe?: boolean;
  mompiked?: boolean;
  status?: 'PENDING' | 'SENT' | 'RECEIVED' | 'READ' | 'PLAYED' | 'ERROR';
  broadcast?: boolean;
  participantPhone?: string;
  messageId?: string;
  senderPhoto?: string;
  senderName?: string;
  photo?: string;
  text?: {
    message?: string;
  };
  image?: {
    mimeType?: string;
    imageUrl?: string;
    thumbnailUrl?: string;
    caption?: string;
  };
  video?: {
    mimeType?: string;
    videoUrl?: string;
    caption?: string;
  };
  audio?: {
    mimeType?: string;
    audioUrl?: string;
    ptt?: boolean;
  };
  document?: {
    mimeType?: string;
    documentUrl?: string;
    fileName?: string;
    caption?: string;
  };
  sticker?: {
    mimeType?: string;
    stickerUrl?: string;
  };
  location?: {
    latitude?: number;
    longitude?: number;
    name?: string;
    address?: string;
  };
  contact?: {
    displayName?: string;
    vCard?: string;
  };
  connected?: boolean;
  smartphoneConnected?: boolean;
  ids?: string[];
  type?:
    | 'MessageStatusCallback'
    | 'ReceivedCallback'
    | 'DisconnectedCallback'
    | 'ConnectedCallback';
}
