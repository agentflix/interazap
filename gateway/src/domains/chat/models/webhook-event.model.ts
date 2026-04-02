/**
 * Modelos do dominio Chat/Webhook do gateway.
 * Tipos compartilhados de eventos normalizados recebidos de provedores.
 */

/**
 * Direcao do evento normalizado no fluxo de mensagens.
 */
export type EventDirection = 'inbound' | 'outbound' | 'status' | 'connection';

/**
 * Provedor de mensageria suportado no dominio de chat.
 */
export type ProviderType = 'uazapi' | 'zapi';

/**
 * Tipo de conteudo da mensagem normalizada.
 */
export type MessageType =
  | 'text'
  | 'image'
  | 'video'
  | 'audio'
  | 'document'
  | 'sticker'
  | 'location'
  | 'contact';

/**
 * Status de entrega de mensagem no fluxo normalizado.
 */
export type MessageStatus = 'sent' | 'delivered' | 'read' | 'failed';
